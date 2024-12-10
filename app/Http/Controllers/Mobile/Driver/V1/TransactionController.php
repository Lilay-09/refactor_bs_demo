<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
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
            // $payment->payment_time = Helper::formatCustomDateTime($payment->payment_datetime,'h:i:s A');
            // $payment->breakdown_notes .= '|ABA: USD 20$|ABA: KHR 50000';
            $paymentDetails = $this->getPaymentMethods($paymentDetails,$payment->id);
            $payment->breakdown_notes = $paymentDetails->method;//TransactionService::strReplaceCurrencySymbols($payment->breakdown_notes);
            if($payment->is_settled) $paidTrx[] = $payment;
            else {
                $count += 1;
                $total += 1;
            }
            unset($payment->is_settled,$payment->payment_datetime,$payment->id);
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
        foreach($details as $d){
            if($d->payment_id == $pmtId){
                if(!$method) $method .= $d->method;
                else $method .= '|'.$d->method;
            }
        }
        return (object)[
            'method' => $method,
        ];
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
