<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function getDashboardSummary(Request $req){
        $obj = [
            'monthly' => $this->getEarning(),
            'top_rider' => ''
        ];

        return ApiResponse::JsonResult($obj);
    }

    private function getEarning(){
        $data = [
            [
                'title' => 'Total Earning',
                'total' => ''
            ],
            [
                'title' => 'Average Daily Earning',
                'total' => ''
            ],
            [
                'title' => 'Total Packages',
                'total' => ''
            ],
            [
                'title' => 'Delivered Count',
                'total' => ''
            ],
            [
                'title' => 'Returned count',
                'total' => ''
            ],
            [
                'title' => 'Total Registrations',
                'total' => ''
            ],
            [
                'title' => 'Total Active Drivers',
                'total' => ''
            ],
            [
                'title' => 'Total Merchants',
                'total' => ''
            ]
        ];

        return $data;
    }
}
