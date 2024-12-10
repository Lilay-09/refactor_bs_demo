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
        // $balanceInfo = Package::where('driver_id',$user->id)
        // ->with(['driver_payment'])
        // ->get();
        $balanceDue = Package::where('driver_id',$user->id)->where('is_deleted',1)->whereIn('status_id',[9,19])->sum('driver_total');
        $count = 0;
        $total = 0;
        $paidTrx = [];
        $paymentTrx = Payment::where('payer_id',$user->id)
        ->where('payments.is_deleted',0)
        ->where('payments.is_settled',1)
        ->join('users as c','c.id','payments.settled_uid')
        ->selectRaw('payments.id,payments.payment_datetime,payments.payable_amount,payments.is_settled,payments.breakdown_notes,c.user_name as cashier_name,payments.remarks')
        ->get();
        $paymentDetails = PaymentDetail::selectRaw('id,payment_id,method,currency_code')->get();
        foreach($paymentTrx as $payment){
            $payment->payment_date = Helper::formatCustomDateTime($payment->payment_datetime,'d-M-Y');
            if($payment->is_settled) {
                $paymentDetails = $this->getPaymentMethods($paymentDetails,$payment->id);
                $payment->breakdown_notes = $paymentDetails->method;
                $paidTrx[] = $payment;
            }
            else {
                $count += 1;
                $total += 1;
            }
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
                    $method = $d->method;
                } else {
                    $method .= '|' . $d->method;
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
        ->where('status_id',9)
        ->where('driver_id',$driverId)
        ->where('driver_disbursement_id',$driverId);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qP->whereBetween('delivered_datetime',[$startDate,$endDate])->orWhereDate('delivered_datetime',$endDate);
        }
        $deliveredCount = $qP->count();
        $qO = Order::where('is_deleted',0)->where('status_id',5)
        ->where('driver_disbursement_id',$driverId)
        ->where('driver_id',$driverId);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qO->whereBetween('order_datetime',[$startDate,$endDate])->orWhereDate('order_datetime',$endDate);
        }
        $pickUpCount = $qO->sum('qty');
        $driverCommissions = DriverCommission::where('is_deleted',0)->where('driver_id',$driverId)->get();
        $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        $total = $pickUpCount * $driverCommissionInfo->normal_pickup_commission + $deliveredCount * $driverCommissionInfo->normal_delivery_commission;
        $report = [
            "total" => $total,
            "details" => [
                [
                    'category' => 'Pickup',
                    'count' => $pickUpCount
                ],
                [
                    'category' => 'Delivered',
                    'count' => $deliveredCount
                ]
            ]
        ];

        return ApiResponse::JsonResult($report);
    }

    public function getCommissionTrx(){
        $user = UserService::getAuthUser('driver');
        $disbursement = Disbursement::where('payee_type','driver')->where('payee_id',$user->id)->get();
        return $disbursement;
    }

    public function getCommissonTranxAndReport(Request $req){
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $obj = (object)[
            'report' => [
                [
                    'category' => '',
                    'count' => 250,
                    'unit' => 0.5,
                    'total' => 0,
                    'remarks' =>  ''
                ]
            ],
            'transaction' => [
                    [
                        'payment_date' => '',
                        'payable_amount' => 0,
                        'method' => '',
                        'payer_name' => '',
                    ]
            ],
        ];

        return ApiResponse::JsonResult($obj);
    }
}
