<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\TransactionService;
use Carbon\Carbon;
use DB;
use Helper;
use Illuminate\Http\Request;
use Kreait\Firebase\Database\Transaction;

class DashboardController extends Controller
{
    //
    protected $days;
    public function __construct(){
        $this->days = 90;
    }
    public function getDashboardSummary(Request $req){
        $obj = [
            'monthly' => $this->getMonthlyEarning(),
            'top_rider' => $this->topRiders(),
            'unpaid_rider' => $this->unpaidRiders(),
            'merchant_payable' => $this->merchantPayable(),
            'driver_daily_collection' => $this->driverDailyCollection(),
            'merchants_by_category' => $this->merchantsByCategory(),
            'bar_charts' => $this->barChart()
        ];

        return ApiResponse::JsonResult($obj);
    }

    private function getMonthlyEarning(){

        // $payments = Payment::where('is_deleted',0)
        // ->where('payment_datetime', '>=', Carbon::now()->subDays($this->days))->get();
        $deliveredCount = 0;
        $returnedCount = 0;
        $results = User::selectRaw("
            SUM(CASE WHEN account_type = 'merchant' AND register_channel = 'mobile' THEN 1 ELSE 0 END) as register_count,
            SUM(CASE WHEN is_deleted = FALSE AND account_type = 'driver' AND lock = FALSE AND has_account = TRUE THEN 1 ELSE 0 END) as total_active_driver,
            SUM(CASE WHEN is_deleted = FALSE AND account_type = 'merchant' THEN 1 ELSE 0 END) as total_merchant
        ")->first();

        $registeredCount = $results->register_count;
        $totalActiveDrivers = $results->total_active_driver;
        $totalMerchant = $results->total_merchant;

        $packages = Package::where('is_deleted',0)
        ->where('outstanding',0)
        ->selectRaw('id,status_id,delivery_fee,extra_charge,failed_datetime,delivered_datetime')
        ->where('updated_at', '>=', Carbon::now()->subDays($this->days))->get();
        foreach ($packages as $p){
            if($p->status_id == 9) {
                $deliveredCount += 1;
                $p->finished_date = Helper::dateYMD($p->delivered_datetime);
            }
            if($p->status_id == 19) $p->finished_date = Helper::dateYMD($p->failed_datetime);
            if($p->status_id == 11) $returnedCount += 1;
        }
        $earningData = $this->getEarning($packages);
        $items = [
            [
                'title' => 'Total Earning',
                'total' => $earningData['total_earning'],
                'currency' => 'USD'
            ],
            [
                'title' => 'Average Daily Earning',
                'total' => $earningData['average_daily_earning'],
                'currency' => 'USD'
            ],
            [
                'title' => 'Total Packages',
                'total' => count($packages)
            ],
            [
                'title' => 'Delivered Count',
                'total' => $deliveredCount
            ],
            [
                'title' => 'Returned count',
                'total' => $returnedCount
            ],
            [
                'title' => 'Total Registrations',
                'total' => $registeredCount
            ],
            [
                'title' => 'Total Active Drivers',
                'total' => $totalActiveDrivers
            ],
            [
                'title' => 'Total Merchants',
                'total' => $totalMerchant
            ]
        ];

        return [
            'days' => $this->days,
            'items' => $items
        ];
    }

    private function merchantsByCategory(){
        $packageCount = Package::where('is_deleted',0)->where('outstanding',0)
        ->where('updated_at', '>=', Carbon::now()->subDays($this->days))
        ->selectRaw('
            SUM(CASE WHEN status_id = 5 THEN 1 ELSE 0 END) as at_warehouse_count,
            SUM(CASE WHEN status_id = 6 THEN 1 ELSE 0 END) as on_delivery_count,
            SUM(CASE WHEN status_id = 9 THEN 1 ELSE 0 END) as delivered_count,
            SUM(CASE WHEN status_id = 11 THEN 1 ELSE 0 END) as returned_count,
            SUM(CASE WHEN status_id = 10 THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN status_id = 19 THEN 1 ELSE 0 END) as failed_with_fee_count
        ')
        ->first();
        return $packageCount;

    }

    private function driverDailyCollection(){
        $pkgPayments = Package::from('packages as p')->join('payments as pmt','pmt.id','p.driver_payment_id')->where('pmt.approved',1)
        ->where('p.updated_at', '>=', Carbon::now()->subDays($this->days))
        ->selectRaw('pmt.exchange_rate,pmt.id as payment_id,DATE(payment_datetime) as payment_date,COUNT(DISTINCT(p.driver_id)) as total_driver,COUNT(DISTINCT(p.merchant_id)) as total_merchant')
        ->groupBy('payment_id')
        ->orderByDesc('payment_datetime')
        ->get();
        // $payments = Payment::where('is_deleted',0)
        // ->selectRaw('id as payment_id,DATE(payment_datetime) as payment_date,COUNT(payer_id) as total_driver')
        // ->groupBy('id')
        // ->get();
        // $disbursements = Disbursement::where('is_deleted',0)->where('payee_type','driver')->where('type','payment')->get();
        $paymentDetails = PaymentDetail::get();
        $paymentList = [];
        foreach($pkgPayments as $pmt){
            $pmtDetails = $this->getPaymentDetails($pmt->payment_id,$paymentDetails);

            if($pmtDetails){
                // \Log::info($paymentDetails[0]);
                $amountConverted = TransactionService::amountToOneCurrency('USD',$pmtDetails['cash_usd'],$pmtDetails['cash_khr'],$pmtDetails['bank_usd'],$pmtDetails['bank_khr'],$pmt->exchange_rate);
                $pmt->cash = $amountConverted['cash'];
                $pmt->bank_amount = $amountConverted['bank'];
                $pmt->total = $amountConverted['total'];
            }
            $paymentList[] = $pmt;
        }
        return $paymentList;


    }

    private function getPaymentDetails($paymentId,$rows){
        $bankKhr = 0;
        $bankUsd = 0;
        $cashKhr = 0;
        $cashUsd = 0;
        foreach($rows as $row) {
            if($row->payment_id == $paymentId){
                // \Log::info($row);
                // return $row;
                if($row->method == 'cash' && $row->currency_code == 'KHR'){
                    $cashKhr += $row->amount;
                }else if($row->method == 'cash' && $row->currency_code == 'USD'){
                    $cashUsd += $row->amount;
                }else if($row->method !== 'cash' && $row->currency_code == 'KHR'){
                    $bankKhr += $row->amount;
                }else if($row->method !== 'cash' && $row->currency_code == 'USD'){
                    $bankUsd += $row->amount;
                }
            }
        }

        return [
            'bank_khr' => $bankKhr,
            'bank_usd' => $bankUsd,
            'cash_khr' => $cashKhr,
            'cash_usd' => $cashUsd
        ];
    }

    private function getEarning($rows)
    {
        // Ensure $rows is always an array
        $rows = !is_array($rows) ? iterator_to_array($rows) : $rows;

        $totalEarning = array_reduce($rows, function ($sum, $p) {
            // Only include rows where status_id is 9 or 19
            if (in_array($p->status_id, [9, 19])) {
                return $sum + $p->delivery_fee + $p->extra_charge;
            }
            return $sum; // If status_id is not 9 or 19, don't add anything
        }, 0);

        $filteredRowsForDates = array_filter($rows, fn($p) => in_array($p->status_id, [9, 19]));
        $uniqueDates = array_unique(array_map(fn($p) => date('Y-m-d', strtotime($p->finished_date)), $filteredRowsForDates));
        $daysCount = count($uniqueDates) ?: 1; // ADvoid division by zero

        // Calculate average daily earning
        $averageDailyEarning = $totalEarning / $daysCount;

        return [
            'total_earning' => Helper::getNumber($totalEarning,2,true),
            'average_daily_earning' => Helper::getNumber($averageDailyEarning,2,true),
        ];
    }



    // private function getDaysPackages($rows){
    //     $obj = [
    //         'total_earning' => 0,
    //         'average_daily_earning' => 0,
    //     ];
    //     foreach($rows as $p){

    //     }
    // }

    private function topRiders($top=5){
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $topRiders = DB::table('packages as p')
        ->where('p.outstanding',0)
        ->where('p.updated_at', '>=', Carbon::now()->subDays($this->days))
        ->whereIn('p.status_id',[9,10,19])
        ->join('users as r', 'p.driver_id', '=', 'r.id')
        ->where('r.account_type','driver') // Updated column name
        ->select('r.id', 'r.user_name as driver_name', DB::raw('COUNT(p.id) as total_packages'))
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
        ->where('p.updated_at', '>=', Carbon::now()->subDays($this->days))
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
        $totalAmount = 0;
        $dailyCollection = Package::fromRaw('packages as p')
        ->whereIn('p.status_id', [9, 19])
        ->where('p.updated_at', '>=', Carbon::now()->subDays($this->days))
        ->join('tracking_statuses as ts', 'ts.id', '=', 'p.status_id')
        ->join('users as m', 'm.id', '=', 'p.merchant_id')
        // ->leftJoin('payments as pmt', function ($join) {
        //     $join->on('p.driver_payment_id', '=', 'pmt.id')
        //         ->where('pmt.approved', '=', 1);
        // })
        // ->leftJoin('disbursements as dis', function ($join) {
        //     $join->on('p.merchant_disbursement_id', '=', 'dis.id')
        //         ->where('dis.approved', '=', 1);
        // })
        ->selectRaw('
            m.user_name as merchant_name,
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime::DATE
                ELSE p.failed_datetime::DATE
            END AS finished_date,
            SUM(
                CASE
                    WHEN p.cod = TRUE AND p.status_id = 9 THEN p.price
                    ELSE 0
                END
            ) AS cod_amount,
            SUM(
                CASE
                    WHEN (p.merchant_payment_id IS NULL AND p.merchant_disbursement_id IS NULL AND p.payer = \'sender\')
                    THEN p.taxi_fee
                    ELSE 0
                END
            ) AS taxi_fee,
            SUM(
                CASE
                    WHEN (p.merchant_payment_id IS NULL AND p.merchant_disbursement_id IS NULL AND p.payer = \'sender\')
                    THEN p.delivery_fee + p.extra_charge
                    ELSE 0
                END
            ) AS fees,
            \'unpaid\' AS payment_status,
            SUM(
                CASE
                    WHEN (p.merchant_payment_id IS NULL AND p.merchant_disbursement_id IS NULL) THEN 1
                    ELSE 0
                END
            ) AS package_count
        ')
        ->whereNull('p.merchant_payment_id')
        ->whereNull('p.merchant_disbursement_id')
        ->groupByRaw('
            m.id,
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime::DATE
                ELSE p.failed_datetime::DATE
            END
        ')

        ->get();
        foreach($dailyCollection as $d){
            $d->amount = Helper::getNumber($d->cod_amount - $d->taxi_fee - $d->fees);
            $totalAmount += $d->amount;
        }
        return [
            'total_amount' => Helper::getNumber($totalAmount,2,true),
            'list' => $dailyCollection
        ];
    }

    private function barChart()
    {

        $packagesData = Package::where('is_deleted', 0)
            ->where('is_deleted', 0)
            ->whereYear('created_at', now()->year)
            ->selectRaw('EXTRACT(MONTH FROM created_at) AS month, COUNT(*) AS count, COUNT(DISTINCT merchant_id) AS merchant_count')
            ->groupByRaw('EXTRACT(MONTH FROM created_at)')
            ->orderByRaw('month')
            ->get();

        // Extract counts separately
        $packagesByDate = $packagesData->pluck('count', 'month')->toArray();
        $merchantsByDate = $packagesData->pluck('merchant_count', 'month')->toArray();

        // Fill missing months with 0
        $packagesByDate = array_replace(array_fill(1, 12, 0), $packagesByDate);
        $merchantsByDate = array_replace(array_fill(1, 12, 0), $merchantsByDate);

        $earningByDate = Package::where('is_deleted', 0)
            ->whereYear('created_at', now()->year)
            ->whereIn('status_id', [9, 19])
            ->selectRaw('EXTRACT(MONTH FROM created_at) AS month, SUM(delivery_fee + extra_charge) AS total_fee')
            ->groupByRaw('EXTRACT(MONTH FROM created_at)')
            ->orderByRaw('month')
            ->get()
            ->pluck('total_fee', 'month')
            ->map(function ($value) {
                return (float) Helper::getNumber($value);  // Cast the total_fee to float
            })
            ->toArray();

        // Fill missing months with 0 and ensure correct order
        $earningByDate = array_replace(array_fill(1, 12, 0), $earningByDate);

        return [
            'merchants' => array_values($merchantsByDate),
            'packages' => array_values($packagesByDate),
            'earning' => array_values($earningByDate),
        ];


        // Ensure all months (1-12) are present with default values of 0
        // $fullYearData = collect(range(1, 12))->map(function ($month) use ($merchantsByRegisterDate, $packagesByDate,$earningByDate) {
        //     return [
        //         'merchant_count' => $merchantsByRegisterDate[$month] ?? 0,
        //         'package_count' => $packagesByDate[$month] ?? 0,
        //         'earning' => (float) Helper::getNumber($earningByDate[$month] ?? 0),
        //     ];
        // });

        // return $fullYearData;
    }


    private function fillMissingMonths($data)
    {
        $fullYear = collect(range(1, 12))->mapWithKeys(function ($month) use ($data) {
            return [$month => $data[$month] ?? 0];
        });

        return $fullYear;
    }



}
