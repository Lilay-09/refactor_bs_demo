<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\ExchangeRate;
use App\Models\FeedBack;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Models\UserBank;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use DB;
use Helper;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    //** BEGIN::COMPANY REPORT */

    public function getPickupReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qO = Order::whereNotNull('driver_id')->with(['merchant','driver'])
        ->where('status_id',5)
        ->where('company_id',$user->company_id)
        ->selectRaw('merchant_id,code,product_type,pickup_address,qty,vehicle_type,driver_id');
        $orders = $qO->orderByDesc('id')->get();
        foreach($orders as $order){
            $order->product_type = $order->product_type ? $order->product_type : 'Others';
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_phone = $order->merchant->phone;
            $order->driver_name = $order->driver?->user_name;
            unset($order->driver,$order->merchant);
        }
        $groupedPackages = collect($orders)->map(function ($item) {
            $item->groupDate = date('d-M-Y',strtotime($item->created_at));
            // $item->actionDate = $actionDate;
            return $item;
        })->groupBy('groupDate')
        ->map(function ($group, $date) {
            $group->each(function ($item) {
                unset($item->groupDate);
            });
            return [
                'date' => $date,
                'details' => $group->toArray(),
                'total' => [
                    'package' => $group->sum('qty'),
                ],
            ];
        })->values();
        $obj =(object)[
            'title' => 'Daily Packages',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];
        return ApiResponse::JsonResult($obj,'Get Pickup List');
    }

    public function getDailyPackageReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $lang = $req->lang;
        $statusId = $req->status_id;
        $qP = Package::where('is_deleted',0)
        ->with(['status','driver','merchant'])
        ->where('outstanding',0)
        ->selectRaw('qr_code,merchant_id,driver_id,payer,product_type,receiver_address,remarks,receiver_phone,cod,price,delivery_fee,additional_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,assign_driver_datetime,updated_at,failed_datetime,returned_datetime,delivered_datetime,extra_charge,created_at');

        if($statusId) $qP->where('status_id',$statusId);
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qP->where(function($q) use ($startDatetime, $endDatetime) {
                $q->where(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 19, query only failed_datetime
                    $q->whereBetween('failed_datetime', [$startDatetime, $endDatetime])
                    ->whereIn('status_id', [10,19]);
                })
                ->orWhere(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('delivered_datetime', [$startDatetime, $endDatetime])
                    ->where('status_id', 9);
                })
                ->orWhere(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime])
                    ->where('status_id', 5);
                })
                ->orWhere(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('assign_driver_datetime', [$startDatetime, $endDatetime])
                    ->where('status_id', 6);
                })

                ->orWhere(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 11, query only returned_datetime
                    $q->whereBetween('returned_datetime', [$startDatetime, $endDatetime])
                    ->where('status_id', 11);
                });
            });
        }

        $packages = $qP->orderByDesc('created_at')->get();
        $groupedPackages = collect($packages)->map(function ($pkg) {
            $actionDate = Helper::formatCustomDateTime($pkg->created_at);
            if ($pkg->status_id == 5) $actionDate = Helper::formatCustomDateTime($pkg->arrive_warehouse_datetime,'d-M-Y');
            if ($pkg->status_id == 6) $actionDate = Helper::formatCustomDateTime($pkg->assign_driver_datetime,'d-M-Y');
            if ($pkg->status_id == 10) $actionDate = Helper::formatCustomDateTime($pkg->failed_datetime,'d-M-Y');
            if ($pkg->status_id == 9) $actionDate = Helper::formatCustomDateTime($pkg->delivered_datetime,'d-M-Y');
            if ($pkg->status_id == 19) $actionDate = Helper::formatCustomDateTime($pkg->failed_datetime,'d-M-Y');
            if ($pkg->status_id == 11) $actionDate = Helper::formatCustomDateTime($pkg->returned_datetime,'d-M-Y');
            // Return the modified package with the actionDate
            // $pkg->groupDate = date('d-M-Y',strtotime($pkg->created_at));
            $pkg->actionDate = $actionDate;
            return $pkg;
        })->groupBy('actionDate')
        ->map(function ($group, $date) use ($lang) {
            $group->each(function ($item) use ($lang) {
                if($lang == 'km'){
                    $item->status_code = GeneralSettingService::$statusCodeTrans[$item->status_id] ?? '';
                }else $item->status_code = $item->status->name;
                $item->merchant_name = $item->merchant->user_name;
                $item->merchant_phone = $item->merchant->phone;
                $item->driver_name = $item->driver?->user_name;
                $item->driver_phone = $item->driver?->phone;
                $item->cod_fee = $item->cod ? $item->price:0;
                $item->fee = $item->delivery_fee + $item->extra_charge;//PickupCenterService::getFees($item->cod,$item->delivery_fee,$item->additional_fee,$item->extra_charge);
                unset(
                    $item->status,$item->cod,$item->merchant,$item->driver,$item->driver_id,
                    $item->merchant_id,$item->arrive_warehouse_datetime,$item->assign_driver_datetime,
                    $item->failed_datetime,$item->delivered_datetime
                );
            });
            return [
                'date' => $date,
                'details' => $group->toArray(),
                'total' => [
                    'cod_fee' => Helper::getNumber($group->sum('cod_fee'),2), // Replace 'cod' with the actual property name
                    'fee' => Helper::getNumber($group->sum('fee'),2),
                    'driver' => Helper::getNumber($group->sum('driver_total'),2), // Add any other total calculations
                    'merchant' => Helper::getNumber($group->sum('merchant_total'),2),
                ],
            ];
        })->values();

        $obj =(object)[
            'title' => 'Daily Packages',
            'sub_title' => 'Arrivate Date:',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];

        return ApiResponse::JsonResult($obj,'Get Pickup List');
    }

    public function getDailyPackageSummaryReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $merchantId = $req->merchant_id;
        $qP = Package::where('is_deleted',0)
        ->where('outstanding',0)
        ->with(['merchant']);
        if($merchantId) $qP->where('merchant_id',$merchantId);
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
            $qP->where(function ($q) use ($startDatetime,$endDatetime){
                $q->whereRaw(
                    "(status_id = 5 AND arrive_warehouse_datetime BETWEEN ? AND ?)
                    OR (status_id = 6 AND assign_driver_datetime BETWEEN ? AND ?)
                    OR (status_id = 10 AND failed_datetime BETWEEN ? AND ?)
                    OR (status_id = 19 AND failed_datetime BETWEEN ? AND ?)
                    OR (status_id = 9 AND delivered_datetime BETWEEN ? AND ?)
                    OR (status_id = 11 AND returned_datetime BETWEEN ? AND ?)",
                    [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
                );
            });
        }
        $packages = $qP->selectRaw('DATE(created_at) as created_date,merchant_id,status_id,delivery_fee,cod')
        ->whereIn('status_id',[5,6,9,10,11,19])
        ->orderByDesc('created_date')
        ->get();
        $groupedPackages = collect($packages)->map(function ($pkg) {
            $pkg->groupKey = date('d-M-Y',strtotime($pkg->created_date));
            return $pkg;
        })
        ->groupBy('groupKey')
        ->map(function ($group, $date) {
            $uniqueMerchants = $group->unique('merchant_id');
            $uniqueMerchants->each(function ($item) use ($group) {
                $item->merchant_name = $item->merchant->user_name;
                $item->merchant_phone = $item->merchant->phone;
                $item->merchant_address = $item->merchant->address;
                $item->merchant_code = $item->merchant->code;
                $item->driver_name = $item->driver?->user_name;
                $item->driver_phone = $item->driver?->phone;
                $item->delivered_count = $group->where('status_id', 9)->where('merchant_id', $item->merchant_id)->count();
                $item->returned_count = $group->where('status_id',11)->where('merchant_id', $item->merchant_id)->count();
                $item->outstanding_count += $group->whereIn('status_id', [5,6,10,19])->where('merchant_id', $item->merchant_id)->count(); //$group->whereIn('status_id', [10,19])->count();
                $item->package_count = $item->delivered_count + $item->returned_count + $item->outstanding_count;
                unset($status_id, $item->merchant, $item->driver,$item->cod,$item->cod);
            });
            return [
                'date' => $date,
                'details' => $uniqueMerchants->values()->toArray(),
                'total' => [
                    'pacakge' => $group->sum('package_count'),
                    'delivered' => $group->sum('delivered_count'),
                    'returned' => $group->sum('returned_count'),
                    'outstanding' => $group->sum('outstanding_count'),
                ],
            ];
        })->values();

        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'sub_title' => 'Arrivate Date:',
            'date' => $startDate.' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];

        return ApiResponse::JsonResult($obj,'Get Daily Packages Summary');

    }

    public function getSettleStatementReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = Payment::fromRaw('payments as p')
        ->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',1)
        ->join('users as ap','ap.id','p.approved_uid')
        ->join('users as st','st.id','p.settled_uid')
        ->selectRaw('p.payable_amount,p.id as payment_id,d.user_name as payer_name,ap.user_name as receiver_name,p.exchange_rate,p.taxi_fee,p.approved,p.payment_datetime,p.is_settled,st.user_name as settlement_user,p.payer_id')
        ->orderByDesc('payment_datetime');

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->whereBetween('payment_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
        }

        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = TransactionService::preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = Helper::displayMoney($totalUSD + $totalKHR_to_USD,'USD');
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $pmt->confirmed_user = $pmt->settlement_user ?? $pmt->approved_user;
            $pmt->status_code = $pmt->is_settled ? 'Completed':'Pending';
            unset($pmt->payment_datetime);
        }
        // $groupedPackages = collect($payments)->map(function ($pkg) {
        //     $pkg->groupKey = date('d-M-Y',strtotime($pkg->created_date));
        //     return $pkg;
        // })
        // ->groupBy('groupKey')
        // ->map(function ($group, $date) {
        //     $uniqueMerchants = $group->unique('merchant_id');
        //         $uniqueMerchants->each(function ($item) use ($group) {
        //         $item->merchant_name = $item->merchant->user_name;
        //         $item->merchant_phone = $item->merchant->phone;
        //         $item->merchant_address = $item->merchant->address;
        //         $item->merchant_code = $item->merchant->code;
        //         $item->driver_name = $item->driver?->user_name;
        //         $item->driver_phone = $item->driver?->phone;
        //         $item->package_count = $group->where('merchant_id', $item->merchant_id)->count();
        //         $item->delivered_count = $group->where('status_id', 9)->count(); // Count packages for this merchant
        //         $item->returned_count = $group->where('status_id', 11)->count();
        //         $item->outstanding_count = $group->whereIn('status_id', [10,19])->count();
        //         unset($status_id, $item->merchant, $item->driver,$item->cod,$item->cod);
        //     });
        //     return [
        //         'date' => $date,
        //         'details' => $uniqueMerchants->values()->toArray(),
        //         'total' => [
        //             'pacakge' => $group->sum('package_count'),
        //             'delivered' => $group->sum('delivered_count'),
        //             'returned' => $group->sum('returned_count'),
        //             'outstanding' => $group->sum('outstanding_count'),
        //         ],
        //     ];
        // })->values();
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'sub_title' => 'Arrivate Date:',
            'status' => 'All Drivers',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $payments
        ];
        return ApiResponse::JsonResult($obj,'Get Settle Statement');
    }

    public function getOperationSummaryReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $summary = $this->getOperationSummary($startDate, $endDate);
        $operationSummary = $summary->operation;
        $financialSummary = $summary->financial;

        $qPmt = Payment::from('payments as pmt')->where('pmt.is_deleted',0)->where('pmt.is_settled',1)->join('payment_details as pd','pmt.id','pd.payment_id')->selectRaw('SUM(pd.amount) as total,pd.currency_code,CASE WHEN pd.method != \'cash\' THEN \'bank\' ELSE pd.method END as method_group')->groupBy(DB::raw("CASE WHEN pd.method != 'cash' THEN 'bank' ELSE pd.method END"),'pd.currency_code');
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00'; //
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qPmt->whereBetween('payment_datetime',[$startDatetime,$endDatetime]);
        }
        $payments = $qPmt->get();
        $closedFinancialSummary = [
            [
                'title' => 'Total collective payment by Bank(USD)',
                'amount' => 0
            ],
            [
                'title' => 'Total collective payment by Bank(KHR)',
                'amount' => 0
            ],
            [
                'title' => 'Total collective payment by Cash(USD)',
                'amount' => 0
            ],
            [
                'title' => 'Total collective payment by Cash(KHR)',
                'amount' => 0
            ],
        ];
        foreach ($payments as $payment) {
            if ($payment->method_group == 'bank') {
                if ($payment->currency_code == 'USD') {
                    $closedFinancialSummary[0]['amount'] += $payment->total;  // Bank USD
                } elseif ($payment->currency_code == 'KHR') {
                    $closedFinancialSummary[1]['amount'] += $payment->total;  // Bank KHR
                }
            } elseif ($payment->method_group == 'cash') {
                if ($payment->currency_code == 'USD') {
                    $closedFinancialSummary[2]['amount'] += $payment->total;  // Cash USD
                } elseif ($payment->currency_code == 'KHR') {
                    $closedFinancialSummary[3]['amount'] += $payment->total;  // Cash KHR
                }
            }
        }
        $obj =(object)[
            'title' => 'Summary Report',
            'sub_title' => 'Arrivate Date:',
            'exchange_rate' => 4100,
            'driver_count' => 0,
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'operation_summary' => $operationSummary,
            'financial_summary' => $financialSummary,
            'closed_financial_summary' => $closedFinancialSummary
        ];
        return ApiResponse::JsonResult($obj,'Get Settle Statement');
    }

    private function getOperationSummary($startDate,$endDate){
        $startDate = $startDate ? Helper::dateYMD($startDate):null;
        $endDate = $endDate ? Helper::dateYMD($endDate):null;
        $qP = Package::where('is_deleted',0)
        ->where('outstanding',0);
        $clonePkg = clone $qP;
        $clonePkg->whereIn('status_id',[9,19]);

        $qO = Order::where('status_id',5)->where('is_deleted',0);
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate) . ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate) . ' 23:59:59';
            $qO->whereBetween('pickup_datetime', [$startDatetime,$endDatetime]);
            $qP->whereRaw(
                "(status_id = 5 AND arrive_warehouse_datetime BETWEEN ? AND ?)
                OR (status_id IN (2,3,4) AND pickup_datetime BETWEEN ? AND ?)
                OR (status_id = 6 AND assign_driver_datetime BETWEEN ? AND ?)
                OR (status_id = 10 AND failed_datetime BETWEEN ? AND ?)
                OR (status_id = 19 AND failed_datetime BETWEEN ? AND ?)
                OR (status_id = 9 AND delivered_datetime BETWEEN ? AND ?)
                OR (status_id = 11 AND returned_datetime BETWEEN ? AND ?)",
                [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
            );
            $clonePkg->whereRaw(
                "(status_id = 9 AND delivered_datetime BETWEEN ? AND ?)
                OR (status_id = 19 AND failed_datetime BETWEEN ? AND ?)",
                [$startDatetime, $endDatetime, $startDatetime, $endDatetime]
            );
        }


        $packages = $qP->selectRaw('id,merchant_id,driver_id,status_id')->get();
        $pickupCount = $qO->sum('qty');
        $operationSum = [
            'merchantCount' => 0,
            'deliveredCount' => 0,
            'atWarehouseCount' => 0,
            'onDeliveryCount' => 0,
            'failedCount' => 0,
            'returnedCount' => 0,
            'failedWithFeeCount' => 0,
        ];

        // $operationSum = [
        //     'merchantCount' => 0,
        //     'deliveredCount' => 0,
        //     'atWarehouseCount' => 0,
        //     'onDeliveryCount' => 0,
        //     'failedCount' => 0,
        //     'returnedCount' => 0,
        //     'failedWithFeeCount' => 0,
        // ];

        $seenMerchants = [];

        foreach ($packages as $p) {
            if (!isset($seenMerchants[$p->merchant_id])) {
                $seenMerchants[$p->merchant_id] = true;
                $operationSum['merchantCount']++;
            }

            switch ($p->status_id) {
                case 9:
                    $operationSum['deliveredCount']+=1;
                    break;
                case 6:
                    $operationSum['onDeliveryCount']++;
                    break;
                case 5:
                    $operationSum['atWarehouseCount']++;
                    break;
                case 10:
                    $operationSum['failedCount']++;
                    break;
                case 11:
                    $operationSum['returnedCount']++;
                    break;
                case 19:
                    $operationSum['failedWithFeeCount']++;
                    break;
            }
        }

        $obj = (object)[
            'operation' => [
                [
                    'title' => 'Count merchants',
                    'count' => $operationSum['merchantCount']
                ],
                [
                    'title' => 'Total pacakge \'Pickup\'',
                    'count' => $pickupCount
                ],
                [
                    'title' => 'Total package \'At Warehouse\'',
                    'count' => $operationSum['atWarehouseCount']
                ],
                [
                    'title' => 'Total package \'On Delivery\'',
                    'count' => $operationSum['onDeliveryCount']
                ],
                [
                    'title' => 'Total Package \'Delivered\'',
                    'count' => $operationSum['deliveredCount']
                ],
                [
                    'title' => 'Total Package \'Failed\'',
                    'count' => $operationSum['failedCount']
                ],
                [
                    'title' => 'Total Package \'Fail With Fee\'',
                    'count' => $operationSum['failedWithFeeCount']
                ],
                [
                    'title' => 'Total Package \'Returned\'',
                    'count' => $operationSum['returnedCount']
                ]
            ],
            'financial' => [
                [
                    'title' => 'Total collective COD',
                    'amount' => 0,
                    'amount_kh' => 0
                ],
                [
                    'title' => 'Total Fees',
                    'amount' => 0,
                    'amount_kh' => 0
                ],
                [
                    'title' => 'Total Fees owned by \'Merchants\'' ,
                    'amount' => 0,
                    'amount_kh' => 0
                ],
                [
                    'title' => 'Total money to pay back merchants',
                    'amount' => 0,
                    'amount_kh' => 0
                ],
                [
                    'title' => 'Total received fees',
                    'amount' => 0,
                    'amount_kh' => 0
                ],
            ]
        ];

        return $obj;
    }


    public function getReviewAndFeedBackReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = FeedBack::where('is_deleted',0)
        ->selectRaw('id,create_uid,rate,created_at,comments')
        ->with('merchant');

        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qP->whereBetween('created_at',[$startDatetime,$endDatetime]);
        }

        $feedBack = $qP->orderByDesc('id')->get();
        $total = 0;
        foreach($feedBack as $fd){
            $fd->name = $fd->merchant->user_name;
            $fd->date = Helper::formatCustomDateTime($fd->created_at);
            $fd->address = $fd->merchant->address;
            unset($fd->merchant,$fd->created_at,$fd->create_uid);
            $total += 1;
        }
        $obj =(object)[
            'title' => 'Review And Feedback',
            'sub_title' => 'Arrivate Date:',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => $total,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $feedBack
        ];

        return ApiResponse::JsonResult($obj,'Get Daily Packages Summary');
    }

    //** option */

    public function getSettleStatementReportOption(){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'operators' => GeneralSettingService::optionsOperator($user),
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getDailyPackageReportOption(Request $req){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'statuses' => GeneralSettingService::optionsTrackingStatus($user,[],[5,6,9,10,11,19]),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPickupReportOption(){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'merchants' => GeneralSettingService::optionsMerchant($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getDailyPackageSummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'merchants' => GeneralSettingService::optionsMerchant($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    //** END::COMPANY REPORT */




    // **BEGIN::DRIVER REPORT
    public function getDriverListReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qD = User::where('account_type','driver')
        ->selectRaw('code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock,employment_date');
        $drivers = $qD->get();
        foreach($drivers as $driver){
            $driver->status_code = $driver->lock ? 'Inactive' : 'Active';
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => 1,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $drivers
        ];
        return ApiResponse::JsonResult($obj,'Driver List');
    }

    public function driverDeliverySummaryReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $driverId = $req->driver_id;
        $qP = Package::where('is_deleted',0)
        ->whereIn('status_id',[9,10,11,19]);
        $qD = User::where('account_type','driver')
        ->selectRaw('id,code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $oD = Order::where('status_id',5)->where('is_deleted',0)->selectRaw('qty,driver_id');
        if($driverId){
            // $qP->where('driver_id',$driverId);
            if ($driverId) {
                $qP->where(function ($query) use ($driverId) {
                    $query->where(function ($subQuery) use ($driverId) {
                        $subQuery->where('status_id', '!=', 11)
                                ->where('driver_id', $driverId);
                    })->orWhere(function ($subQuery) use ($driverId) {
                        $subQuery->where('status_id', 11)
                                ->where('returned_uid', $driverId);
                    });
                });
            }
            $qD->where('id',$driverId);
            $oD->where('driver_id',$driverId);
        }

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $startDatetime = $startDate.' 00:00:00';
            $endDatetime = $endDate.' 23:59:59';
            $qP->where(function($q) use ($startDatetime, $endDatetime,$driverId) {
                $q->where(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 19, query only failed_datetime
                    $q->whereBetween('failed_datetime', [$startDatetime, $endDatetime])
                    ->whereIn('status_id', [19,10]);
                })
                ->orWhere(function($q) use ($startDatetime, $endDatetime) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('delivered_datetime', [$startDatetime, $endDatetime])
                    ->where('status_id', 9);
                })
                ->orWhere(function($q) use ($startDatetime, $endDatetime,$driverId) {
                    // For status_id 11, query only returned_datetime
                    $q->whereBetween('returned_datetime', [$startDatetime, $endDatetime])
                    ->where('status_id', 11);
                    if($driverId) $q->where('returned_uid',$driverId);
                });
            });

            // $oD->whereRaw('updated_at::DATE >= ? AND updated_at::DATE <= ?', [$startDate, $endDate]);
            $oD->whereBetween('updated_at', [$startDatetime, $endDatetime]);
        }
        $drivers = $qD->get();
        $packages = $qP->get();
        $orders = $oD->get();
        $totalPickUpCount = 0;
        $totalDeliveredCount = 0;
        $totalFaileWithFeeCount = 0;
        $totalReturnedCount = 0;
        foreach($drivers as $d){
            $deliveryDetails = $this->getDriverDeliveryPackageDetails($packages,$d->id);
            $pickupDetails = $this->getDriverPickupDetails($orders,$d->id);
            $d->delivered_count = $deliveryDetails->delivered_count;
            $d->status_code = $d->lock ? 'Inactive' : 'Active';
            $d->failed_with_fee_count = $deliveryDetails->failed_with_fee_count;
            $d->returned_count = $deliveryDetails->returned_count;
            $d->pickup_count = $pickupDetails->pickup_count;

            $totalPickUpCount += $d->pickup_count;
            $totalDeliveredCount += $d->delivered_count;
            $totalFaileWithFeeCount += $d->failed_with_fee_count;
            $totalReturnedCount += $d->returned_count;
        }

        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => count($drivers),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'total_package' => (object)[
                'pickup' => $totalPickUpCount,
                'delivered' => $totalDeliveredCount,
                'fail_with_fee' => $totalFaileWithFeeCount,
                'returned' => $totalReturnedCount
            ],
            'list' => $drivers
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getDailyMerchantActivities(Request $req){
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $merchantId = $req->merchant_id;
        $mP = Package::from('packages as p')->where('p.is_deleted',0)->where('p.outstanding',0)->join('users as m','m.id','p.merchant_id');
        // $merchantPackages =
    }

    public function getDriverPaymentReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $driverId = $req->driver_id;
        $allPayments = [];
        $amount = 0;
        $amountKh = 0;
        $total = 0;
        $xRate = GeneralSettingService::getLatestXRate()->buy_rate;
        $qP = Payment::with(['driver:id,user_name,code','cashier:id,user_name'])->where('is_deleted',0)->where('payer_type','driver')->selectRaw('id,payer_id,payment_datetime,breakdown_notes,exchange_rate,payable_amount as amount,approved_uid');
        $qD = Disbursement::with(['driver:id,user_name,code','cashier:id,user_name'])->where('is_deleted',0)->where('payee_type','driver')->where('type','payment')->selectRaw('id,payee_id,payment_datetime,breakdown_notes,exchange_rate,payable_amount as amount,approved_uid');
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('payment_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            });

            $qD->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('payment_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            });
        }
        if($driverId){
            $qD->where('payee_id',$driverId);
            $qP->where('payer_id',$driverId);
        }
        $payments = $qP->where('approved',1)->get();
        $disbursements = $qD->where('approved',1)->get();
        $paymentDetails = DisbursementDetails::selectRaw('disbursement_id,method,amount,original_amount,currency_code')->get();
        $paymentDetails = PaymentDetail::selectRaw('payment_id,method,amount,original_amount,currency_code')->get();
        foreach($payments as $p){
            $p->driver_name = $p->driver?->user_name;
            $p->code = $p->driver?->code;
            $p->booked_by = $p->cashier?->user_name;
            $pmtDetails = $this->getPaymentDetails($paymentDetails,$p->id);
            $p->amount_usd = Helper::getNumber($pmtDetails->amount_usd,2);
            $p->amount_khr = Helper::getNumber($pmtDetails->amount_khr,2);
            $amtKhrToUsd = $p->amount_khr / $xRate;
            $amount += $pmtDetails->amount_usd;
            $amountKh += $pmtDetails->amount_khr;
            $total += $p->amount_usd + $amtKhrToUsd;
            $p->payment_type = 'receive';
            unset($p->driver,$p->cashier);
            $allPayments[] = $p;
        }
        foreach($disbursements as $p){
            $p->driver_name = $p->driver?->user_name;
            $p->code = $p->driver?->code;
            $p->booked_by = $p->cashier?->user_name;
            $pmtDetails = $this->getPaymentDetails($paymentDetails,$p->id);
            $p->amount_usd = Helper::getNumber($pmtDetails->amount_usd,2);
            $p->amount_khr = Helper::getNumber($pmtDetails->amount_khr,2);
            $amtKhrToUsd = $p->amount_khr / $xRate;
            $amount += $pmtDetails->amount_usd;
            $amountKh += $pmtDetails->amount_khr;
            $total += $p->amount_usd + $amtKhrToUsd;
            $p->payment_type = 'disbursement';
            unset($p->driver,$p->cashier);
            $allPayments[] = $p;
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => 1,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $allPayments,
            'grand' => [
                'amount' => Helper::getNumber($amount,2),
                'amount_kh' => Helper::getNumber($amountKh,2),
                'total' => Helper::getNumber($total,2)
            ]
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPackageDetailReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateYMD($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateYMD($req->endDate) : null;
        $driverId = $req->driver_id;
        $isKm = $req->lang == 'km';
        $qP = Package::where('is_deleted',0)->where('outstanding',0)
        ->with(['merchant:id,user_name','status:id,name'])
        ->orderByDesc('id')
        ->selectRaw('status_id,qr_code,merchant_id,receiver_phone,receiver_name,receiver_address,cod,delivery_fee,taxi_fee,driver_total,remarks,zone_code,zone_name,payer,driver_id,price');
        if($driverId){
            $qP->where('driver_id',$driverId);
        }
        if($startDate && $endDate){
            $startDateTime = "$startDate 00:00:00";
            $endDateTime = "$endDate 23:59:59";

            $qP->where(function($q) use ($startDateTime, $endDateTime) {
                $q->where(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 10 or 19, query only failed_datetime
                    $q->whereBetween('failed_datetime', [$startDateTime, $endDateTime])
                    ->whereIn('status_id', [10, 19]);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('delivered_datetime', [$startDateTime, $endDateTime])
                    ->where('status_id', 9);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 6, query only assign_driver_datetime
                    $q->whereBetween('assign_driver_datetime', [$startDateTime, $endDateTime])
                    ->where('status_id', 6);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 5, query only arrive_warehouse_datetime
                    $q->whereBetween('arrive_warehouse_datetime', [$startDateTime, $endDateTime])
                    ->where('status_id', 5);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 11, query only returned_datetime
                    $q->whereBetween('returned_datetime', [$startDateTime, $endDateTime])
                    ->where('status_id', 11);
                });
            });
        }
        $uniqueDrivers = [];
        $distinctDriverCount = 0;
        $packages = $qP->get();
        foreach ($packages as $p){
            $p->merchant_name = $p->merchant->user_name;
            $p->merchant_phone = $p->merchant->phone;
            $p->status_code = $p->status->name;
            $cod = $p->cod;
            if($isKm) $p->payer = GeneralSettingService::$payerTrans[$p->payer] ?? '';
            $p->price = $cod ? $p->price:0;
            $p->base_fee = $p->delivery_fee;
            if ($p->driver_id !== null && empty($uniqueDrivers[$p->driver_id])) {
                $uniqueDrivers[$p->driver_id] = true; // Mark this driver_id as seen
                $distinctDriverCount+=1; // Increment the distinct count
            }
            unset($p->merchant,$p->status);
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => $distinctDriverCount,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $packages
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getDriverCommissionPayment(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qD = User::from('users as d')->where('d.account_type','driver')
        ->join('disbursements as dis','dis.payee_id','d.id')
        ->join('users as r','r.id','dis.receiptionist_uid')
        ->orderByDesc('dis.id')
        ->selectRaw('d.user_name as driver_name,d.code,dis.pickup_rate,dis.delivery_rate,dis.failed_with_fee_count,dis.delivered_package_count,dis.pickup_package_count,dis.payable_amount,dis.payment_datetime,dis.breakdown_notes,r.user_name as paid_by')
        ->where('dis.type','commission');
        if ($startDate && $endDate) {
            $startDateTime = Helper::dateYMD($startDate) . ' 00:00:00';
            $endDateTime = Helper::dateYMD($endDate) . ' 23:59:59'; // Corrected here
            $qD->whereBetween('dis.payment_datetime', [$startDateTime, $endDateTime]);
        }

        $drivers = $qD->get()->map(function($d){
            $d->payment_datetime = Helper::formatCustomDateTime($d->payment_datetime);
            return $d;
        });
        $obj =(object)[
            'title' => 'Driver Commission',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => 1,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $drivers
        ];
        return ApiResponse::JsonResult($obj);

    }


    //** GET DETAILS */

    private function getPaymentDetails($rows,$pmtId){
        $details = (object)[
            'amount_usd' => 0,
            'amount_khr' => 0,
        ];
        foreach($rows as $d){
            if($d->payment_id == $pmtId){
                if($d->currency_code == 'USD'){
                    $details->amount_usd += $d->amount;
                }else if($d->currency_code == 'KHR'){
                    $details->amount_khr += $d->amount;
                }
            }
        }
        return $details;
    }
    private function getDriverPickupDetails($rows,$driverId){
        $c = null;
        $i = 0;
        $pickupCount = 0;
        do{
            if(!isset($rows[$i])) break;
            $c = $rows[$i];
            if($c->driver_id == $driverId){
                $pickupCount += $c->qty;
            }
            $i++;
        }while($c);

        return (object)[
            'pickup_count' => $pickupCount,
        ];
    }
    private function getDriverDeliveryPackageDetails($rows,$driverId){
        $c = null;
        $i = 0;
        $delivered_count = 0;
        $failed_with_fee_count = 0;
        $returned_count = 0;
        do{
            if(!isset($rows[$i])) break;
            $c = $rows[$i];
            if($c->driver_id == $driverId){
                if($c->status_id == 9) $delivered_count +=1;
                if($c->status_id == 19) $failed_with_fee_count +=1;
            }
            if($c->returned_uid == $driverId) $returned_count +=1;
            $i++;
        }while($c);

        return (object)[
            'delivered_count' => $delivered_count,
            'failed_with_fee_count' => $failed_with_fee_count,
            'returned_count' => $returned_count
        ];
    }





    //** OPTION */

    // public function driverDeliverySummaryReportOption(){
    //     $user = UserService::getAuthUser();
    //     $obj =(object)[
    //         'warehouses' => GeneralSettingService::optionsWarehouse($user),
    //         'drivers' => GeneralSettingService::optionsDriver($user)
    //     ];
    //     return ApiResponse::JsonResult($obj);
    // }

    //** END::DRIVER REPORT */


    //** BEGIN::MERCHANT REPORT */

    public function getMerchantListReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $totalMerchant = 0;
        $q = User::where('is_deleted',0)
        ->where('account_type','merchant')
        ->with('bank_accounts:user_id,bank_name,bank_number,account_name')
        ->selectRaw('user_name,business_type,phone,created_at,address,code,lock,id');
        $merchants = $q->get();
        foreach($merchants as $m){
            $m->registered_date = Helper::dateDMY($m->created_at);
            $m->status_code = $m->lock ? 'Inactive' : 'Active';
            foreach($m->bank_accounts as $b){
                if($b->is_primary) {
                    $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                    $m->bank_name = $b->bank_name;
                    $m->bank_number = $b->bank_number;
                    $m->account_name = $b->account_name;
                }
                if(!$b->bank_account) {
                    $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                    $m->bank_name = $b->bank_name;
                    $m->bank_number = $b->bank_number;
                    $m->account_name = $b->account_name;
                }
            }
            $totalMerchant +=1;
            unset($m->created_at,$m->bank_accounts);
        }
        $obj =(object)[
            'title' => 'Merchant List',
            'status' => 'All Merchant',
            'date' => $startDate.' to '.$endDate,
            'total' => $totalMerchant,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $merchants
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getMerchantSummaryReport(Request $req){
        $user = UserService::getAuthUser();
        $isKm = $req->lang != 'en';
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $merchantId = $req->merchant_id;
        $merchantInfo = User::where('is_deleted',0)->where('account_type','merchant')
        ->selectRaw('id,user_name as merchant_name,phone as merchant_phone,address')->find($merchantId);
        if(!$merchantInfo) return ApiResponse::NotFound('Please select a merchant to view this report');
        foreach($merchantInfo->bank_accounts as $b){
            if($b->is_primary){
                $merchantInfo->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                $merchantInfo->bank_name = $b->bank_name;
                $merchantInfo->bank_number = $b->bank_number;
                $merchantInfo->account_name = $b->account_name;
            }
            if(!$b->bank_account) {
                $merchantInfo->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                $merchantInfo->bank_name = $b->bank_name;
                $merchantInfo->bank_number = $b->bank_number;
                $merchantInfo->account_name = $b->account_name;
            }
        }
        unset($merchantInfo->bank_accounts);
        $xRate = ExchangeRate::whereRaw('DATE(x_date) >= ? AND DATE(x_date) <= ?', [$startDate, $endDate])
            ->orderBy('x_date', 'desc') // Ensures the latest rate in the range is prioritized
            ->take(1)->value('buy_rate');

        if (!$xRate) {
            $xRate = GeneralSettingService::getLatestXRate()->buy_rate;
        }

        $merchantInfo->exchange_rate = $xRate;
        $pmtCase = ',CASE WHEN p.merchant_disbursement_id IS NOT NULL THEN dis.is_settled WHEN p.merchant_payment_id IS NOT NULL THEN pmt.is_settled ELSE FALSE END AS approved';
        $qP = Package::from('packages as p')->where('p.is_deleted',0)
        ->where('p.merchant_id',$merchantId)
        ->whereIn('p.status_id',[5,6,9,10,11,19])
        ->with('status')
        ->leftJoin('payments as pmt', function ($join) use($merchantId) {
            $join->on('p.merchant_payment_id', '=', 'pmt.id')
                ->where('pmt.payer_id',$merchantId)
                ->where('pmt.payer_type', '=', 'merchant'); // Add merchant filter
        })
        ->leftJoin('disbursements as dis', function ($join) use($merchantId) {
            $join->on('p.merchant_disbursement_id', '=', 'dis.id')
                ->where('dis.payee_id',$merchantId)
                ->where('dis.payee_type', '=', 'merchant')->where('dis.type','payment'); // Add merchant filter
        })
        ->selectRaw('p.order_id,p.merchant_total,p.merchant_id,p.remarks,p.delivery_remarks,p.status_id,p.id,p.qr_code,p.delivered_datetime,p.failed_datetime,p.delivery_remarks,p.remarks,p.taxi_fee,p.extra_charge,p.delivery_fee,p.cod,p.price,p.payer,
        p.returned_datetime,p.arrive_warehouse_datetime,p.assign_driver_datetime,p.receiver_phone,p.receiver_name,p.receiver_address,p.zone_name,p.delivery_remarks,p.merchant_disbursement_id,p.merchant_payment_id'.$pmtCase);

        if ($startDate && $endDate) {
            // Concatenate start and end dates with the times
            $startDateTime = "$startDate 00:00:00";
            $endDateTime = "$endDate 23:59:59";

            // Prepare your query
            $qP->where(function($q) use ($startDateTime, $endDateTime) {
                // Combine status checks into fewer OR clauses, grouped by datetime fields
                $q->where(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 10 or 19, query failed_datetime
                    $q->whereIn('p.status_id', [10, 19])
                    ->whereBetween('p.failed_datetime', [$startDateTime, $endDateTime]);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 9, query delivered_datetime
                    $q->where('p.status_id', 9)
                    ->whereBetween('p.delivered_datetime', [$startDateTime, $endDateTime]);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 6, query assign_driver_datetime
                    $q->where('p.status_id', 6)
                    ->whereBetween('p.assign_driver_datetime', [$startDateTime, $endDateTime]);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 5, query arrive_warehouse_datetime
                    $q->where('p.status_id', 5)
                    ->whereBetween('p.arrive_warehouse_datetime', [$startDateTime, $endDateTime]);
                })
                ->orWhere(function($q) use ($startDateTime, $endDateTime) {
                    // For status_id 11, query returned_datetime
                    $q->where('p.status_id', 11)
                    ->whereBetween('p.returned_datetime', [$startDateTime, $endDateTime]);
                });
            });

        }

        $qP->orderByRaw('
            CASE
                WHEN p.status_id = ? THEN 1
                WHEN p.status_id = ? THEN 2
                WHEN p.status_id = ? THEN 3
                WHEN p.status_id = ? THEN 4
                WHEN p.status_id = ? THEN 5
                WHEN p.status_id = ? THEN 6
                ELSE 7
            END', [9, 19, 11, 6, 10, 5]
        )->orderByRaw('DATE(p.failed_datetime) DESC,DATE(p.delivered_datetime) DESC');
        $clonePkg = clone $qP;
        $packages = $qP->get();
        // return $packages;
        // foreach($packages as $p){

        // }
        // return $packages;
        $headerSummary = $this->getMerchantSummaryHeader($clonePkg,$merchantId,$startDate,$endDate);
        $summary = $headerSummary->package_info;
        $groupedPackages = collect($packages)->map(function ($item) use (&$grand)  {
            $item->arrive_warehouse_datetime = Helper::formatCustomDateTime($item->arrive_warehouse_datetime);
            $finishDate = $item->failed_datetime;
            if($item->status_id == 9 || $item->status_id == 11) {
                $finishDate = $item->delivered_datetime;
                // $item->delivery_remarks = '';
            }
            if($item->status_id == 5) $finishDate = $item->arrive_warehouse_datetime;
            if($item->status_id == 6) {
                $finishDate = $item->assign_driver_datetime;
                $item->failed_datetime = '';
                // $item->delivery_remarks = '';
            }
            if($item->status_id == 10 || $item->status_id == 19) $finishDate = $item->failed_datetime;
            if($item->status_id == 11) $finishDate = $item->returned_datetime;
            $item->groupDate = Helper::dateDMY($finishDate);
            $item->makeHidden('delivery_remarks');
            unset($item->status);
            return $item;
        })->groupBy('groupDate')
        ->map(function ($group, $date) use ($isKm){
            $group->each(function ($item) use (&$grand,$isKm,&$totalDeliveryFee) {
                unset($item->groupDate);

                $item->finished_date = $item->failed_datetime ? Helper::dateDMY($item->failed_datetime): Helper::dateDMY($item->delivered_datetime);
                $finished_time = $item->failed_datetime ? Helper::formatCustomDateTime($item->failed_datetime,'h:i:s A'):Helper::formatCustomDateTime($item->delivered_datetime,'h:i:s A');
                $item->finished_time = $finished_time;
                $isCal = in_array($item->status_id,[9,19]);
                $item->price = $item->cod ? $item->price:0;
                $total = $item->cod && $item->status_id == 9 ? $item->price : 0;
                if($item->payer == 'sender') {
                    $item->delivery_fee = $isCal ? ($item->delivery_fee + $item->extra_charge) : 0;
                    $total -= $item->delivery_fee + $item->taxi_fee;
                }else $item->delivery_fee = 0;
                $totalDeliveryFee += $item->delivery_fee;
                $item->total = $isCal ? (float)Helper::getNumber($total) : 0;
                if(in_array($item->status_id,[9,19])) $grand += $total;
                if($isKm) {
                    $item->status_code = GeneralSettingService::$statusCodeTrans[$item->status_id] ?? '';
                    $item->payer = GeneralSettingService::$payerTrans[$item->payer] ?? '';
                    if(in_array($item->status_id,[9,19])){
                        $item->payment_status = GeneralSettingService::$pmtStatusTrans['unpaid'];
                        if($item->approved) $item->payment_status = GeneralSettingService::$pmtStatusTrans['paid'];
                    }else{
                        $item->payment_status = GeneralSettingService::$pmtStatusTrans['pending'];
                    }
                }
                else {
                    $item->payment_status = 'Pending';
                    if(in_array($item->status_id,[9,19])){
                        $item->payment_status = 'Unpaid';
                        if($item->approved) $item->payment_status = 'Paid';
                    }
                    $item->status_code = $item->status->name;
                }
            });
            return [
                'date' => $date,
                'details' => $group->toArray(),
                'total' => [
                    'cod' => Helper::getNumber($group->where('status_id','=',9)->where('cod',1)->sum('price')),
                    'taxi' => Helper::getNumber($group->where('status_id','=',9)->sum('taxi_fee')),
                    'delivery_fee' => Helper::getNumber($totalDeliveryFee,2),
                    'grand' => Helper::getNumber($grand,2)
                ]
            ];
        // })->values();

        })->sortKeysDesc()->values();

        //sortKeys()
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => $startDate.' to '.$endDate,
            'merchant' => $merchantInfo,
            'total_packages' => $headerSummary->total_count,
            'summary' => $summary,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];
        return ApiResponse::JsonResult($obj);
    }

    private function getMerchantSummaryHeader($clonePkg,$merchantId,$startDate,$endDate){
        $lastOrder = Package::from('packages as p')->where('p.is_deleted',0)
        ->whereRaw(
            "(p.status_id = 5 AND p.arrive_warehouse_datetime BETWEEN ? AND ?)
            OR (p.status_id = 6 AND p.assign_driver_datetime BETWEEN ? AND ?)
            OR (p.status_id = 10 AND p.failed_datetime BETWEEN ? AND ?)",
            [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]
        )
        ->joinSub(
        Order::select('id as order_id','code')
            ->where('merchant_id',$merchantId)
            ->where('status_id',5)
            ->orderByDesc('id') // Assuming 'id' defines the latest order
            ->limit(1),
        'o',
        'o.order_id',
        '=',
        'p.order_id'
        )
        ->whereIn('p.status_id',[5,6,10])
        ->selectRaw('p.id as package_id,p.order_id,p.qr_code,p.merchant_total')
        ->get();
        $packages = $clonePkg->get();
        $totalCount = 0;
        $pkgInfo = [
            5 => ['title' => 'ចំនួនកញ្ចប់ដែលនៅសល់ ', 'count' => 0,'total' => 0],
            "5.1" => ['title' => 'ចំនួនកញ្ចប់​ចូលថ្មី ', 'count' => 0, 'total' => 0],
            "5.2" => ['title' => 'ចំនួនកញ្ចប់សរុប ',"count" => 0, 'total' => 0],
            9 => ['title' => 'ជោគជ័យ ', 'count' => 0, 'total' => 0],
            6 => ['title' => 'កំពុងដឹក ', 'count' => 0, 'total' => 0],
            10 => ['title' => 'បរាជ័យ ', 'count' => 0, 'total' => 0],
            19 => ['title' => 'បរាជ័យគិតសេវា ', 'count' => 0, 'total' => 0],
            11 => ['title' => 'ត្រឡប់ទៅហាងវិញ ', 'count' => 0, 'total' => 0],
            'all' => ['title' => 'ត្រឡប់ទៅហាងវិញ ', 'count' => 0, 'total' => 0],
        ];
        foreach($lastOrder as $p){
            $pkgInfo['5.1']['count'] += 1;
            $pkgInfo['5.1']['total'] += -$p->merchant_total;
            $totalCount += 1;
        }
        // return $packages;
        foreach ($packages as $p) {
            $totalCount += 1;
            $statusId = $p->status_id;
            if(isset($pkgInfo[$statusId])){
                $pkgInfo[$statusId]['count'] += 1;
                $pkgInfo[$statusId]['total'] += -$p->merchant_total;
            }
            if(in_array($statusId,[5,6,10])){
                $pkgInfo[5]['count'] += 1;
                $pkgInfo[5]['total'] += -$p->merchant_total;
                $pkgInfo['5.2']['count'] = $pkgInfo['5.1']['count'] + $pkgInfo[5]['count'];
                $pkgInfo['5.2']['total'] = $pkgInfo['5.1']['total'] + $pkgInfo[5]['total'];
            }
            // if($p->status_id == 5){
            //     $statusId = $p->status_id.'.1';
            //     return $statusId;
            //     $pkgInfo[$statusId]['count'] += 1;
            // }
        }
        if(isset($pkgInfo[9])) $pkgInfo[9]['total'] = Helper::getNumber($pkgInfo[9]['total'],2);
        if(isset($pkgInfo[11])) $pkgInfo[11]['total'] = Helper::getNumber($pkgInfo[11]['total'],2);
        if(isset($pkgInfo[19])) $pkgInfo[19]['total'] = Helper::getNumber($pkgInfo[19]['total'],2);
        if(isset($pkgInfo[5])) $pkgInfo[5]['total'] = Helper::getNumber($pkgInfo[5]['total'],2);
        if(isset($pkgInfo[6])) $pkgInfo[6]['total'] = Helper::getNumber($pkgInfo[6]['total'],2);
        if(isset($pkgInfo[10])) $pkgInfo[10]['total'] = Helper::getNumber($pkgInfo[10]['total'],2);
        if(isset($pkgInfo['5.1'])) $pkgInfo['5.1']['total'] = Helper::getNumber($pkgInfo['5.1']['total'],2);
        if(isset($pkgInfo['5.2'])) $pkgInfo['5.2']['total'] = Helper::getNumber($pkgInfo['5.2']['total'],2);
        return (object)[
            'package_info' => array_values($pkgInfo),
            'total_count' => $totalCount
        ];
    }

    public function getMerchantPaymentReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $allPayments = [];
        $pQ = Payment::where('payments.is_deleted',0)->where('payments.is_settled',1)
        ->with(['merchant'])
        ->where('payer_type','merchant')
        ->join('users as b','payments.settled_uid','b.id')
        ->selectRaw('payments.id,payments.package_count,payments.payable_amount,payments.breakdown_notes,b.user_name as booked_user,payments.remarks,payments.payment_datetime,payer_id');
        $dQ = Disbursement::where('disbursements.is_deleted',0)->where('disbursements.is_settled',1)
        ->with(['merchant'])
        ->where('payee_type','merchant')
        ->join('users as b','disbursements.settled_uid','b.id')
        ->selectRaw('disbursements.id,disbursements.package_count,disbursements.payable_amount,disbursements.breakdown_notes,b.user_name as booked_user,disbursements.remarks,disbursements.payment_datetime,payee_id');

        if($startDate && $endDate){
            $startDateTime = Helper::dateYMD($startDate).' 00:00:00';
            $endDateTime = Helper::dateYMD($endDate).' 23:59:59';
            $dQ->whereBetween('payment_datetime',[$startDateTime,$endDateTime]);
            $pQ->whereBetween('payment_datetime',[$startDateTime,$endDateTime]);
        }

        $payments = $pQ->get();
        $bankAccounts = UserBank::get();
        foreach($payments as $p){
            $p->bank_account = $this->userBankAccount($bankAccounts,$p->payer_id);
            $p->payment_date = Helper::dateDMY($p->payment_datetime);
            $p->trx_type = 'Receive';
            $p->merchant_name = $p->merchant?->user_name;
            unset($p->merchant);
            $allPayments[] = $p;
        }

        $disbursements = $dQ->get();
        foreach($disbursements as $p){
            $p->bank_account = $this->userBankAccount($bankAccounts,$p->payee_id);
            $p->payment_date = Helper::dateDMY($p->payment_datetime);
            $p->merchant_name = $p->merchant?->user_name;
            $p->trx_type = 'Disbursement';
            unset($p->merchant);
            $allPayments[] = $p;
        }

        usort($allPayments, function ($a, $b) {
            return strtotime($b['payment_datetime']) <=> strtotime($a['payment_datetime']);
        });

        $obj =(object)[
            'title' => 'Merchant Payment',
            'status' => 'All Merchant',
            'total_merchant' => 0,
            'date' => $startDate.' to '.$endDate,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'grand' => [
                'total' => 0
            ],
            'list' => $allPayments
        ];
        return ApiResponse::JsonResult($obj);
    }

    private function userBankAccount($rows,$userId){
        foreach($rows as $row){
            if($row->user_id == $userId){
                if($row->is_primary){
                    return GeneralSettingService::concatBankInfo($row->bank_name,$row->bank_number,$row->account_name);
                }else{
                    return GeneralSettingService::concatBankInfo($row->bank_name,$row->bank_number,$row->account_name);
                }
            }
        }
        return null;
    }


    public function getMerchantOweFees(Request $req){
        $user = UserService::getAuthUser();
        $totalAmount = 0;
        $totalFees = 0;
        $packageCount = 0;
        $totalTaxiFee = 0;
        $totalMerchantCount = 0;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $sumAmount = 'SUM(CASE WHEN packages.payer = \'sender\' THEN packages.delivery_fee + packages.extra_charge + packages.taxi_fee ELSE packages.taxi_fee END) AS amount';
        $pQ = Package::where('packages.is_deleted', 0)
        ->whereIn('packages.status_id',[9,19])
        ->whereNull('packages.merchant_disbursement_id')
        ->whereNull('packages.merchant_payment_id')
        ->where('packages.cod', true) // Filter only COD packages
        ->whereRaw('packages.price - (packages.delivery_fee + packages.extra_charge + packages.taxi_fee) < 0')
        ->join('users as m', 'm.id', '=', 'packages.merchant_id')
        ->leftJoinSub(
            DB::table('user_bank_accounts as uba')
                ->selectRaw("
                    uba.user_id,
                    CONCAT(uba.bank_name, '|', uba.bank_number, '|', uba.account_name) as bank_info
                ")
                ->where('uba.is_primary', true) // Prefer primary account
                ->orWhereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('user_bank_accounts as uba2')
                        ->whereColumn('uba2.user_id', 'uba.user_id')
                        ->where('uba2.is_primary', true);
                })
                ->groupBy('uba.user_id', 'uba.bank_name', 'uba.bank_number', 'uba.account_name'),
            'uba',  // Use 'uba' as the alias for the subquery
            'uba.user_id',  // Join condition for the subquery
            'packages.merchant_id'
        )
        ->selectRaw('
            m.code,
            uba.bank_info,
            m.user_name as merchant_name,
            COUNT(packages.id) as total_package,
            SUM(packages.taxi_fee) as taxi_fee,
            SUM(CASE WHEN packages.payer = \'sender\' THEN packages.delivery_fee + packages.extra_charge ELSE 0 END) AS total_delivery_fee,
            ' . $sumAmount
        )
        ->groupByRaw('m.code, packages.merchant_id, m.user_name, uba.bank_info') ;
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
            $pQ->whereRaw(
            "(packages.status_id = 9 AND packages.arrive_warehouse_datetime BETWEEN ? AND ?)
                OR (packages.status_id = 19 AND packages.failed_datetime BETWEEN ? AND ?)",
            [$startDatetime, $endDatetime, $startDatetime, $endDatetime]
            );
        }
        $packages = $pQ->get()->map(function ($d) use(&$totalAmount,&$totalFees,&$packageCount,$totalTaxiFee,&$totalMerchantCount){
            $totalAmount += $d->amount;
            $totalFees += $d->total_delivery_fee;
            $packageCount += $d->total_package;
            $totalTaxiFee += $d->taxi_fee;
            $totalMerchantCount+= 1;
            return $d;
        });
        $obj =(object)[
            'title' => 'Merchant Payment',
            'status' => 'Total Merchant',
            'merchant_count' => $totalMerchantCount,
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total_amount' => $totalAmount,
            'package_count' => $packageCount,
            'total_fees' => $totalFees,
            'total_taxi_fee' => $totalTaxiFee,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $packages
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getMerchantDailyPackage(){

    }

    //** END MERCHANT REPORT */
    public function driverDeliverySummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'drivers' => GeneralSettingService::optionsDriver($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function merchantSummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            // 'merchants' => GeneralSettingService::optionsMerchant($user)
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getMerchatnPaymentReportOption(){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'transaction_types' => GeneralSettingService::optionsTransactionType()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formOptionUser (){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'statuses' => GeneralSettingService::optionsUserStatus()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function optionsWarehouse(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsWarehouse($user));
    }
}
