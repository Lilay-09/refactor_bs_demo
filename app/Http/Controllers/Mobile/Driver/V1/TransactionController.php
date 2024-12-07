<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
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
        $balanceDue = 0;
        $count = 0;
        $total = 0;
        $paidTrx = [];
        $paymentTrx = Payment::where('payer_id',$user->id)
        ->where('is_deleted',0)
        // ->where('is_settled',1)
        ->selectRaw('payment_datetime,payable_amount,is_settled,breakdown_notes')
        ->get();
        foreach($paymentTrx as $payment){
            $payment->payment_date = Helper::formatCustomDateTime($payment->payment_datetime);
            if($payment->is_settled) $paidTrx[] = $payment;
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


    public function getComissonTranxAndReport(){

        $obj = (object)[
            'report' => [
                [
                    'category' => '',
                    'count' => 250,
                    'unit' => '',
                    'total' => 0,
                    'remarks' =>  ''
                ]
            ],
            'transaction' => [
                [

                ]
            ],
        ];
    }
}
