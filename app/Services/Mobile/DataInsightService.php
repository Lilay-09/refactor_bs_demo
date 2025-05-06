<?php

namespace App\Services\Mobile;

use ApiResponse;
use Illuminate\Http\Request;

class DataInsightService
{
    // Your service methods go here
    public function getMerchantDataInsight(Request $req,$merchantId){
        $data = [
            'total_package_count' => 100,
            'balance_card' => [
                'balance' => "$1,054.00",
                'package_percent' => '100%'
            ],
            'delivered_card' => [
                'package_count' => 20,
            ],
            'on_trip_card' => [
                'package_count' => 20
            ],
            'returned_card' => [
                'package_count' => 20
            ],
            'average_income' => [
                'package_count' => '30PCS',
                'day' => '130Days',
                'amount' => '$15,050.00'
            ]
        ];

        return ApiResponse::JsonResult($data);
    }
}
