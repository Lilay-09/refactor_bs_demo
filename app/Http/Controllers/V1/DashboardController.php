<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
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
            'unpaid_rider' => $this->unpaidRiders(),
            'merchant_payable' => $this->merchantPayable(),
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
        $SUM = ',SUM(
                CASE
                    WHEN (p.cod = TRUE AND p.status_id != 19) THEN p.price
                    ELSE 0
                END
            ) + SUM(
                CASE
                    WHEN p.payer = \'receiver\' THEN (p.delivery_fee + p.extra_charge)
                    ELSE 0
                END
            ) - SUM(p.taxi_fee) AS amount';
        $balanceDues = Package::from('packages as p')
        ->join('users as d','d.id','p.driver_id')
        ->where('p.is_deleted', 0)
        ->whereIn('p.status_id', [9, 19])
        ->whereNull('p.driver_disbursement_id')
        ->leftJoin('payments', 'p.driver_payment_id', '=', 'payments.id')
        ->selectRaw('count(p.id) as qty,d.id,d.user_name as driver_name,d.code'.$SUM)
        ->where(function ($query) {
            $query->whereNull('payments.id') // Include rows without matching payments
                ->orWhere('payments.approved', 0); // Include rows where payments.approved = 0
        })
        ->groupBy('d.id')
        ->orderByDesc('amount')
        ->get();
        return $balanceDues;
    }

    private function merchantPayable(){
        $dailyCollection = Package::fromRaw('packages as p')
        ->whereIn('p.status_id',[9,19])
        ->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt', 'p.driver_payment_id', '=', 'pmt.id')
        ->leftJoin('disbursements as dis', 'p.merchant_disbursement_id', '=', 'dis.id')
        ->selectRaw('
            CASE WHEN p.status_id = 9 THEN p.delivered_datetime::DATE ELSE p.failed_datetime::DATE END AS finished_date,
            COUNT(DISTINCT p.merchant_id) as total_merchant,
            SUM(CASE
                WHEN p.cod = TRUE AND p.status_id != 19 THEN p.price
                ELSE 0
            END) AS cod_amount,
            CASE
                WHEN MAX(CASE WHEN p.merchant_payment_id IS NOT NULL AND pmt.approved = TRUE THEN 1 ELSE 0 END) = 1
                OR MAX(CASE WHEN p.merchant_disbursement_id IS NOT NULL AND dis.approved = TRUE THEN 1 ELSE 0 END) = 1
                THEN \'paid\'
                ELSE \'unpaid\'
            END AS payment_status
        ')
        ->groupByRaw('
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime::DATE
                ELSE p.failed_datetime::DATE
            END,
            CASE
                WHEN p.merchant_payment_id IS NOT NULL THEN pmt.id
                WHEN p.merchant_disbursement_id IS NOT NULL THEN dis.id
            END
        ')
        ->orderByDesc('finished_date')
        ->get();
        return $dailyCollection;
    }
}
