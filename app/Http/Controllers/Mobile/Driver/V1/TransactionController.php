<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Services\UserService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //

    public function getTransactionSummary(Request $req){
        $user = UserService::getAuthUser('driver');
        $balanceInfo = Package::where('driver_id',$user->id)
        ->with(['driver_payment'])
        ->get();

        foreach($balanceInfo as $balance){
            $hasPayment = $balance->driver_payment;
            if($hasPayment){

            }
        }

        $obj = (object)[
            'balance_due' => 0,
            'count' => 0,
            'total' => 0,
            // 'payment_transaction' => $paymentTrx
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
