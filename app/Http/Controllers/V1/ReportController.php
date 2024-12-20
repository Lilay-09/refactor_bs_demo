<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FeedBack;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    //** BEGIN::COMPANY REPORT */

    public function getPickupReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qORder = Order::whereNotNull('driver_id')->with(['merchant','driver'])
        ->where('status_id',5)
        ->where('company_id',$user->company_id)
        ->selectRaw('merchant_id,code,product_type,pickup_address,qty,vehicle_type,driver_id');
        $orders = $qORder->get();
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
        $qP = Package::where('is_deleted',0)
        ->with(['status','driver','merchant'])
        ->where('outstanding',0)
        ->selectRaw('qr_code,merchant_id,driver_id,payer,product_type,receiver_address,remarks,receiver_phone,cod,price,delivery_fee,additional_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,assign_driver_datetime,updated_at,failed_datetime,delivered_datetime,extra_charge,created_at');
        $packages = $qP->orderByDesc('created_at')->get();
        $groupedPackages = collect($packages)->map(function ($pkg) {
            $actionDate = Helper::formatCustomDateTime($pkg->created_at);
            if ($pkg->status_id == 5) $actionDate = Helper::formatCustomDateTime($pkg->arrive_warehouse_datetime);
            if ($pkg->status_id == 6) $actionDate = Helper::formatCustomDateTime($pkg->assign_driver_datetime);
            if ($pkg->status_id == 10) $actionDate = Helper::formatCustomDateTime($pkg->failed_datetime);
            if ($pkg->status_id == 9) $actionDate = Helper::formatCustomDateTime($pkg->delivered_datetime);

            // Return the modified package with the actionDate
            $pkg->groupDate = date('d-M-Y',strtotime($pkg->created_at));
            $pkg->actionDate = $actionDate;
            return $pkg;
        })->groupBy('groupDate')
        ->map(function ($group, $date) {
            $group->each(function ($item) {
                $item->status_code = $item->status->name;
                $item->merchant_name = $item->merchant->user_name;
                $item->merchant_phone = $item->merchant->phone;
                $item->driver_name = $item->driver?->user_name;
                $item->driver_phone = $item->driver?->phone;
                $item->cod_fee = $item->cod ? $item->delivery_fee : 0;
                $item->fee = PickupCenterService::getFees($item->cod,$item->payer,$item->price,$item->delivery_fee,$item->additional_fee,$item->extra_charge);
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
                    'cod_fee' => $group->sum('cod_fee'), // Replace 'cod' with the actual property name
                    'fee' => $group->sum('fee'),
                    'driver' => $group->sum('driver_total'), // Add any other total calculations
                    'merchant' => $group->sum('merchant_total'),
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
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = Package::where('is_deleted',0)
        ->with(['merchant']);
        $packages = $qP->selectRaw('DATE(created_at) as created_date,merchant_id,status_id,delivery_fee,cod')
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
                $item->package_count = $group->where('merchant_id', $item->merchant_id)->count();
                $item->delivered_count = $group->where('status_id', 9)->count(); // Count packages for this merchant
                $item->returned_count = $group->where('status_id', 11)->count();
                $item->outstanding_count = $group->whereIn('status_id', [10,19])->count();
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
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];

        return ApiResponse::JsonResult($obj,'Get Daily Packages Summary');

    }

    public function getSettleStatementReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',1)
        ->join('users as ap','ap.id','p.approved_uid')
        ->leftJoin('users as st','st.id','p.settled_uid')
        ->selectRaw('p.payable_amount,p.id as payment_id,d.user_name as payer_name,p.approved_uid,p.exchange_rate,p.taxi_fee,p.approved,p.payment_datetime,ap.user_name as approved_user,st.user_name as settlement_user,p.payer_id');
        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = TransactionService::preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = $totalUSD + $totalKHR_to_USD;
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
        $operationSummary = $this->getOperationSummary($startDate, $endDate);
        $financialSummary = [
            [
                'title' => 'Total collective money \'All Merchants\'',
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

        ];
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
        $obj =(object)[
            'title' => 'Summary Report',
            'sub_title' => 'Arrivate Date:',
            'exchange_rate' => 4100,
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
        $qP =Package::where('is_deleted',0)
        ->where('outstanding',0)
        ->selectRaw('id,merchant_id,driver_id,status_id');
        $packages = $qP->get();
        $qO = Order::where('status_id',5)->where('is_deleted',0);
        if($startDate && $endDate){
            $qO->whereBetween('pickup_datetime',[$startDate,$endDate])->orWhereDate('pickup_datetime','<=',$endDate);
            // $qO->whereBetween('updated_at',[$startDate,$endDate])->orWhereDate('updated_at',$endDate);
        }
        $pickupCount = $qO->sum('qty');
        $merchantCount = 0;
        $deliveredCount = 0;
        $atWarehouseCount = 0;
        $onDeliveryCount = 0;
        $failedCount = 0;
        $returnedCount = 0;
        $faileWithFeeCount = 0;
        $seenMerchants = [];
        foreach($packages as $p){
            if (!isset($seenMerchants[$p->merchant_id])) {
                $seenMerchants[$p->merchant_id] = true;
                $merchantCount++;
            }
            if($p->status_id == 9) $deliveredCount += 1;
            if($p->status_id == 6) $onDeliveryCount += 1;
            if($p->status_id == 5) $atWarehouseCount += 1;
            if($p->status_id == 10) $failedCount += 1;
            if($p->status_id == 11) $returnedCount +=1;
            if($p->status_id == 19) $faileWithFeeCount +=1;
        }

        return [
            [
                'title' => 'Count merchants',
                'count' => $merchantCount
            ],
            [
                'title' => 'Total pacakge \'Pickup\'',
                'count' => $pickupCount
            ],
            [
                'title' => 'Total package \'At Warehouse\'',
                'count' => $atWarehouseCount
            ],
            [
                'title' => 'Total package \'On Delivery\'',
                'count' => $onDeliveryCount
            ],
            [
                'title' => 'Total Package \'Delivered\'',
                'count' => $deliveredCount
            ],
            [
                'title' => 'Total Package \'Failed\'',
                'count' => $failedCount
            ],
            [
                'title' => 'Total Package \'Fail With Fee\'',
                'count' => $faileWithFeeCount
            ],
            [
                'title' => 'Total Package \'Returned\'',
                'count' => $returnedCount
            ]
        ];

    }


    public function getReviewAndFeedBackReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = FeedBack::where('is_deleted',0)
        ->selectRaw('id,create_uid,rate,created_at,comments')
        ->with('merchant');
        $feedBack = $qP->get();
        $total = 0;
        foreach($feedBack as $fd){
            $fd->name = $fd->merchant->user_name;
            $fd->date = Helper::formatCustomDateTime($fd->created_at);
            $fd->address = $fd->merchant->address;
            unset($fd->merchant,$fd->created_at,$fd->create_uid);
            $total += 1;
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
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
            'statuses' => GeneralSettingService::optionsTrackingStatus($user,[],[]),
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
        ->selectRaw('code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
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
        $packages = Package::where('is_deleted',0)->whereIn('status_id',[9,10,19])->get();
        $qD = User::where('account_type','driver')
        ->selectRaw('id,code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $drivers = $qD->get();
        $orders = Order::where('status_id',5)->where('is_deleted',0)->selectRaw('qty,driver_id')->get();

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
            'total' => 1,
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

    public function getDriverPaymentReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $payments = Payment::with(['driver:id,user_name','cashier'])->selectRaw('id,payer_id,payment_datetime,breakdown_notes,exchange_rate,payable_amount as amount,settled_uid')->where('is_settled',1)->get();
        $paymentDetails = PaymentDetail::selectRaw('payment_id,method,amount,original_amount,currency_code')->get();
        foreach($payments as $p){
            $p->driver_name = $p->driver->user_name;
            $p->booked_by = $p->cashier->user_name;
            $pmtDetails = $this->getPaymentDetails($paymentDetails,$p->id);
            $p->amount_usd = $pmtDetails->amount_usd;
            $p->amount_khr = $pmtDetails->amount_khr;
            unset($p->driver,$p->cashier);
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => 1,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $payments
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPackageDetailReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $packages = Package::where('is_deleted',0)->where('outstanding',0)
        ->with(['merchant:id,user_name','status:id,name'])
        ->selectRaw('status_id,qr_code,merchant_id,receiver_phone,receiver_name,cod,delivery_fee,taxi_fee,driver_total,remarks,zone_code,zone_name,payer')->get();
        foreach ($packages as $p){
            $p->merchant_name = $p->merchant->user_name;
            $p->merchant_phone = $p->merchant->phone;
            $p->status_code = $p->status->name;
            unset($p->merchant,$p->status);
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => $startDate.' to '.$endDate,
            'total' => 1,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $packages
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getDriverCommissionPayment(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $qD = User::fromRaw('users as d')->where('d.account_type','driver')
        ->join('disbursements as dis','dis.payee_id','d.id')
        ->join('users as r','r.id','dis.receiptionist_uid')
        ->selectRaw('d.code,dis.pickup_rate,dis.delivery_rate,dis.failed_with_fee_count,dis.delivered_package_count,dis.pickup_package_count,dis.payable_amount,dis.payment_datetime,dis.breakdown_notes,r.user_name as paid_by')
        ->where('dis.type','commission');
        $drivers = $qD->get();
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => $startDate.' to '.$endDate,
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
                if($c->status_id == 11) $returned_count +=1;
            }
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
        $q = User::where('is_deleted',0)
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
            unset($m->created_at,$m->bank_accounts);
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => $startDate.' to '.$endDate,
            'total' => 1,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $merchants
        ];
        return ApiResponse::JsonResult($obj);
    }


    public function getMerchantSummaryReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $summary = [];
        $qP = Package::where('is_deleted',0);
        $packages = $qP->whereIn('status_id',[9,10,19])->orderByRaw('DATE(failed_datetime) DESC,DATE(delivered_datetime) DESC')
        ->selectRaw('id,qr_code,delivered_datetime,failed_datetime,delivery_remarks,remarks,taxi_fee,extra_charge,delivery_fee,cod,price,payer,receiver_phone,receiver_name,receiver_address')->get();
        $groupedPackages = collect($packages)->map(function ($item) {
            $finishDate = $item->failed_datetime;
            if($item->status_id == 9) $finishDate = $item->delivered_datetime;
            $
            $item->groupDate = date('d-M-Y',strtotime($finishDate));
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
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => $startDate.' to '.$endDate,
            'total' => 1,
            'summary' => $summary,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];
        return ApiResponse::JsonResult($obj);
    }


    //** END MERCHANT REPORT */


    public function driverDeliverySummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'drivers' => GeneralSettingService::optionsDriver($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formOptionUser (){
        $user = UserService::getAuthUser();
        $obj =(object)[
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
