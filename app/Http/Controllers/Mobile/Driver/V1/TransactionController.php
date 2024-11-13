<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //

    public function summary(){
        $obj = (object)[
            'balance_due' => 0,
            'payment_transaction' => []
        ];

        // $payments =



        return ApiResponse::JsonResult($obj);
    }
}
