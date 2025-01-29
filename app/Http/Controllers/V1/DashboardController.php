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
    public function getDashboardSummary(Request $req){
        $obj = [
            'monthly' => $this->getMonthlyEarning(),
            'top_rider' => $this->topRiders(),
            'unpaid_rider' => $this->unpaidRiders(),
            'merchant_payable' => $this->merchantPayable(),
            'driver_daily_collection' => $this->driverDailyCollection(),
        ];

        return ApiResponse::JsonResult($obj);
    }

    private function getMonthlyEarning(){
        $days = 90;
        $payments = Payment::where('is_deleted',0)
        ->where('payment_datetime', '>=', Carbon::now()->subDays($days))->get();
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
        ->selectRaw('id,status_id')
        ->where('updated_at', '>=', Carbon::now()->subDays($days))->get();
        foreach ($packages as $p){
            if($p->status_id == 9) $deliveredCount += 1;
            if($p->status_id == 11) $returnedCount += 1;
        }
        $items = [
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
            'days' => $days,
            'items' => $items
        ];
    }

    private function driverDailyCollection(){
        $pkgPayments = Package::from('packages as p')->join('payments as pmt','pmt.id','p.driver_payment_id')->where('pmt.approved',1)
        ->selectRaw('pmt.exchange_rate,pmt.id as payment_id,DATE(payment_datetime) as payment_date,COUNT(DISTINCT(p.driver_id)) as total_driver,COUNT(DISTINCT(p.merchant_id)) as total_merchant')
        ->groupBy('payment_id')
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
                // return $row;
                if($row->method == 'cash' && $row->currency_code == 'KHR'){
                    $cashKhr = $row->amount;
                }else if($row->method == 'cash' && $row->currency_code == 'USD'){
                    $cashUsd = $row->amount;
                }else if($row->method == 'bank' && $row->currency_code == 'KHR'){
                    $bankKhr = $row->amount;
                }else if($row->method == 'bank' && $row->currency_code == 'USD'){
                    $bankUsd = $row->amount;
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

    private function getDaysEarning($rows){
        $obj = [
            'total_earning' => 0,
            'average_daily_earning' => 0,
        ];
        foreach($rows as $p){

        }
    }

    private function getDaysPackages($rows){
        $obj = [
            'total_earning' => 0,
            'average_daily_earning' => 0,
        ];
        foreach($rows as $p){

        }
    }

    private function topRiders($top=5){
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $topRiders = DB::table('packages as p')
        ->where('p.outstanding',0)
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
        ->join('tracking_statuses as ts', 'ts.id', '=', 'p.status_id')
        ->join('users as m', 'm.id', '=', 'p.merchant_id')
        ->leftJoin('payments as pmt', 'p.driver_payment_id', '=', 'pmt.id')
        ->leftJoin('disbursements as dis', 'p.merchant_disbursement_id', '=', 'dis.id')
        ->selectRaw('
            m.user_name as merchant_name,
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime::DATE
                ELSE p.failed_datetime::DATE
            END AS finished_date,
            SUM(
                CASE
                    WHEN p.cod = TRUE AND p.status_id != 19 THEN p.price
                    ELSE 0
                END
            ) AS cod_amount,
            SUM(
                p.taxi_fee
            ) AS taxi_fee,
            SUM(
                CASE
                    WHEN p.payer = \'sender\' THEN p.delivery_fee + p.extra_charge
                    ELSE 0
                END
            ) AS fees,
            CASE
                WHEN p.merchant_payment_id IS NOT NULL AND pmt.approved = TRUE THEN \'paid\'
                WHEN p.merchant_disbursement_id IS NOT NULL AND dis.approved = TRUE THEN \'paid\'
                ELSE \'unpaid\'
            END AS payment_status,
            SUM(COALESCE(pmt.package_count, 0) + COALESCE(dis.package_count, 0)) AS package_count
        ')
        ->groupByRaw('
            m.id,
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime::DATE
                ELSE p.failed_datetime::DATE
            END,
            CASE
                WHEN p.merchant_payment_id IS NOT NULL AND pmt.approved = TRUE THEN \'paid\'
                WHEN p.merchant_disbursement_id IS NOT NULL AND dis.approved = TRUE THEN \'paid\'
                ELSE \'unpaid\'
            END
        ')
        ->get();
        foreach($dailyCollection as $d){
            $d->amount = $d->cod_amount - $d->taxi_fee - $d->fees;
            $totalAmount += $d->amount;
        }
        return [
            'total_amount' => Helper::getNumber($totalAmount),
            'list' => $dailyCollection
        ];
    }
}
