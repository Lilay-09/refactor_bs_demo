<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function getDashboardSummary(Request $req){
        $obj = [
            'monthly' => $this->getEarning(),
            'top_rider' => $this->topRiders(),
            'unpaid_rider' => $this->unpaidRiders()
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

    private function topRiders($top=5){
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $topRiders = DB::table('packages as p')
        ->where('p.outstanding',0)
        ->whereIn('p.status_id',[9,10,19])
        ->join('users as r', 'p.driver_id', '=', 'r.id')
        ->where('r.account_type','driver') // Updated column name
        ->select('r.id', 'r.user_name', DB::raw('COUNT(p.id) as total_packages'))
        ->whereIn('p.driver_id', function ($query) {
            $query->select('driver_id')
                ->from('packages')
                ->groupBy('driver_id')
                ->havingRaw('COUNT(id) > 0'); // Drivers with packages
        })
        ->where(function ($query) use ($currentMonth, $currentYear) {
            $query->where(function ($q) use ($currentMonth, $currentYear) {
                $q->where('p.status_id', 9)
                ->whereRaw('EXTRACT(MONTH FROM p.delivered_datetime) = ?', [$currentMonth])
                ->whereRaw('EXTRACT(YEAR FROM p.delivered_datetime) = ?', [$currentYear]);
            })->orWhere(function ($q) use ($currentMonth, $currentYear) {
                $q->whereIn('p.status_id', [10, 19])
                ->whereRaw('EXTRACT(MONTH FROM p.failed_datetime) = ?', [$currentMonth])
                ->whereRaw('EXTRACT(YEAR FROM p.failed_datetime) = ?', [$currentYear]);
            });
        })
        ->groupBy('r.id', 'r.user_name')
        ->orderByDesc('total_packages')
        ->limit((int)$top)
        ->get();
       return $topRiders;
    }

    private function unpaidRiders(){

    }
}
