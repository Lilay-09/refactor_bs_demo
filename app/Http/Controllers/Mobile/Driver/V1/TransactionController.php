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
        // $paymentTrx = Payment::where('payer_id',$user->id)
        // ->where('payments.is_deleted',0)
        // ->where('payments.is_settled',1)
        // ->join('users as c','c.id','payments.settled_uid')
        // ->selectRaw('payments.id,payments.payment_datetime,payments.payable_amount,payments.is_settled,payments.breakdown_notes,c.user_name as cashier_name,payments.remarks')
        // ->get();
        $paymentTrx = Package::where('packages.is_deleted', 0)
        ->where('driver_id', $user->id)
        ->whereIn('packages.status_id', [9, 19])
        ->leftJoin('payments as p', 'p.id', 'packages.driver_payment_id')
        ->where('p.is_deleted',0)
        ->leftJoin('disbursements as dis', 'dis.id', 'packages.driver_disbursement_id')
        ->where('dis.is_deleted',0)
        ->leftJoin('users as c', 'c.id', 'p.settled_uid')
        ->select([
            'p.id as payment_id',
            'p.payment_datetime',
            'p.payable_amount',
            'p.is_settled',
            // 'p.breakdown_notes',
            'c.user_name as cashier_name',
            \DB::raw('SUM(packages.driver_total) as driver_total'), // Aggregate driver_total
            'p.remarks'
        ])
        ->groupBy([
            'p.id',
            'p.payment_datetime',
            'p.payable_amount',
            'p.is_settled',
            // 'p.breakdown_notes',
            'c.user_name',
            'p.remarks'
        ])
        ->get();

        // return $paymentTrx;
        $paymentDetails = PaymentDetail::selectRaw('id,payment_id,method,currency_code')->get();
        foreach($paymentTrx as $payment){
            $payment->payment_date = Helper::formatCustomDateTime($payment->payment_datetime,'d-M-Y');
            if($payment->is_settled) {
                $paymentDetails = $this->getPaymentMethods($paymentDetails,$payment->payment_id);
                $payment->breakdown_notes = $paymentDetails->method;
                if($payment->driver_payment_id) $payment->remarks = 'Transfered out';
                if($payment->driver_disbursement_id) $payment->remarks = 'Transferred in';
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
