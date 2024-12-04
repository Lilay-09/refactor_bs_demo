<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //

    public function getTransactionSummary(Request $req){
        $obj = (object)[
            'balance_due' => 0,
            'count' => 0,
            'total' => 0,
            'payment_transaction' => [
                [
                    'payment_date' => now(),
                    'amount' => '',
                    'method' => '',
                    'Payer Name' => ''
                ]
            ]
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
