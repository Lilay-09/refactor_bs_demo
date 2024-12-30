<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //

    public function getTransactionSummary(Request $req){
        $user = UserService::getAuthUser('driver');
        $balanceDue = Package::where('driver_id',$user->id)->where('is_deleted',1)->whereIn('status_id',[9,19])->sum('driver_total');
        $count = 0;
        $total = 0;
        $paidTrx = [];
        $paymentTrx = Package::where('packages.is_deleted', 0)
        ->where('driver_id', $user->id)
        ->whereIn('packages.status_id', [9, 19])
        ->leftJoin('payments as p', function ($join) {
            $join->on('p.id', '=', 'packages.driver_payment_id')
                ->where('p.is_deleted', '=', 0);
        })
        ->leftJoin('disbursements as dis', function ($join) {
            $join->on('dis.id', '=', 'packages.driver_disbursement_id')
                ->where('dis.is_deleted', '=', 0);
        })
        ->leftJoin('users as c', function ($join) {
            $join->on('c.id', '=', 'p.approved_uid')
                ->orOn('c.id', '=', 'dis.approved_uid');
        })
        ->select([
            'p.id as p_id',  // Group by payment_id to ensure proper aggregation
            'dis.id as dis_id',
            'p.payment_datetime as pay_datetime',
            'dis.payment_datetime as dis_datetime',
            'packages.driver_payment_id',
            'packages.driver_disbursement_id',
            'p.payable_amount',
            \DB::raw('CASE WHEN p.is_settled IS NOT NULL THEN p.is_settled ELSE dis.is_settled END as is_settled'),
            \DB::raw('CASE WHEN p.approved IS NOT NULL THEN p.approved ELSE dis.approved END as approved'),
            'c.user_name as cashier_name',
            \DB::raw('CASE WHEN dis.payable_amount IS NOT NULL THEN SUM(dis.payable_amount) ELSE SUM(p.payable_amount) END as driver_total'),
            'p.remarks',
        ])
        ->orderByDesc('p.payment_datetime')
        ->orderByDesc('dis.payment_datetime')
        ->groupBy([
            'p.id',  // Group by payment_id to ensure proper aggregation
            'dis.id', // Include dis.id in case it's selected when p.payable_amount is null
            'p.payment_datetime',
            'dis.payment_datetime',
            'packages.driver_payment_id',
            'packages.driver_disbursement_id',
            'p.payable_amount',
            'dis.payable_amount',
            'p.approved',  // Ensure both fields are included in GROUP BY
            'dis.approved',
            'c.user_name',
            'p.remarks',
        ])->get();

        $paymentDetails = PaymentDetail::selectRaw('id,payment_id,method,currency_code')->get();
        foreach($paymentTrx as $payment){
            $payment->id = $payment->dis_id ?? $payment->p_id;
            $pDate = $payment->pay_datetime ?? $payment->dis_datetime;
            $payment->payment_date = Helper::formatCustomDateTime($pDate,'d-M-Y');
            if($payment->approved) {
                $paymentDetails = $this->getPaymentMethods($paymentDetails,$payment->payment_id);
                $payment->breakdown_notes = $paymentDetails->method;
                if($payment->driver_payment_id) $payment->remarks = 'Disbursement';
                if($payment->driver_disbursement_id) $payment->remarks = 'Received';
                $paidTrx[] = $payment;
            }
            else {
                $count += 1;
                $total += $payment->driver_total;
            }
            $remarks = $payment->remarks;
            $payment->remarks = $remarks ? $remarks : '';
            unset($payment->is_settled,$payment->payment_datetime);
        }
        // foreach($balanceInfo as $balance){
        //     $hasPayment = $balance->driver_payment;
        //     if($hasPayment){
        //         if(!$hasPayment->is_settled) $count += 1;
        //     }
        // }
        $obj = (object)[
            'balance_due' => $balanceDue,
            'count' => $count,
            'total' => $total,
            'payment_transaction' => $paidTrx
        ];

        // $payments =

        return ApiResponse::JsonResult($obj);
    }



    public function getPaymentMethods($details,$pmtId){
        $method = null;
        foreach ($details as $d) {
            // Ensure $d is an object before accessing its properties
            if (is_object($d) && isset($d->payment_id) && $d->payment_id == $pmtId) {
                if (!$method) {
                    $pMtd = $d->method;
                    if($d->currency_code == 'KHR'){
                        $pMtd = $pMtd.':KHR';
                    }
                    $method = $pMtd;
                } else {
                    $pMtd = $d->method;
                    if($d->currency_code == 'KHR'){
                        $pMtd = $pMtd.':KHR';
                    }
                    $method .= '|' . $pMtd;
                }
            }
        }
        return (object)[
            'method' => $method,
        ];
    }



    public function getCommissionReport(Request $req){
        $user = UserService::getAuthUser('driver');
        $driverId = $user->id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = Package::selectRaw('status_id,driver_id')
        ->whereIn('status_id',[9,19])
        ->where('driver_id',$driverId);
        // ->where('driver_disbursement_id',$driverId);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qP->whereRaw('delivered_datetime::DATE >= ? AND delivered_datetime::DATE <= ?', [$startDate, $endDate]);
        }
        $deliveredCount = $qP->count();
        $qO = Order::where('is_deleted',0)->where('status_id',5)
        ->where('driver_disbursement_id',$driverId)
        ->where('driver_id',$driverId);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qP->whereRaw('order_datetime::DATE >= ? AND order_datetime::DATE <= ?', [$startDate, $endDate]);
        }
        $pickUpCount = $qO->sum('qty');
        $driverCommissions = DriverCommission::where('is_deleted',0)->where('driver_id',$driverId)->get();
        $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        $pickUpRate = $driverCommissionInfo->normal_pickup_commission;
        $deliveryRate = $driverCommissionInfo->normal_delivery_commission;
        $total = $pickUpCount * $pickUpRate + $deliveredCount * $deliveryRate;
        $report = [
            "total" => $total,
            "details" => [
                [
                    'category' => 'Pickup',
                    'count' => $pickUpCount,
                    'unit' => (float)$pickUpRate,
                    'total' => $pickUpCount * $pickUpRate,
                    'remarks' => null,
                ],
                [
                    'category' => 'Delivered',
                    'count' => $deliveredCount,
                    'unit' => (float)$deliveryRate,
                    'total' => $deliveryRate * $deliveredCount,
                    'remarks' => null,
                ]
            ]
        ];

        return ApiResponse::JsonResult($report);
    }

    public function getCommissionTrx(){
        $user = UserService::getAuthUser('driver');
        $disbursement = Disbursement::where('payee_type','driver')
        ->where('payee_id',$user->id)->get();
        $data = [
            [
                'payment_date' => '',
                'payable_amount' => 0,
                'method' => '',
                'payer_name' => '',
            ]
        ];
        return ApiResponse::JsonResult($data);
    }

    // public function getCommissonTranxAndReport(Request $req){
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $obj = (object)[
    //         'report' => [
    //             [
    //                 'category' => '',
    //                 'count' => 250,
    //                 'unit' => 0.5,
    //                 'total' => 0,
    //                 'remarks' =>  ''
    //             ]
    //         ],
    //         'transaction' => [
                    // [
                    //     'payment_date' => '',
                    //     'payable_amount' => 0,
                    //     'method' => '',
                    //     'payer_name' => '',
                    // ]
    //         ],
    //     ];

    //     return ApiResponse::JsonResult($obj);
    // }
}
