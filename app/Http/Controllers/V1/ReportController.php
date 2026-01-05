<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Enums\TrackingStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\ExchangeRate;
use App\Models\FeedBack;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
// use App\Models\TelegramSendLog;
use App\Models\User;
use App\Models\UserBank;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\PackageTrailServiceImpl;
use App\Services\TransactionService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{

    //** BEGIN::COMPANY REPORT */

    public function getPickupReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $merchantId = $req->merchant_id;
        $qO = Order::whereNotNull('driver_id')->with(['merchant','driver'])
        ->where('status_id',5)
        ->where('company_id',$user->company_id)
        ->selectRaw('merchant_id,code,product_type,pickup_address,qty,vehicle_type,driver_id');
        if($branchId){
            $qO->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qO->where('warehouse_id',$warehouseId);
        }
        if($startDate && $endDate){
            $qO->whereBetween('pickup_datetime',[
                Helper::dateYMD($startDate).' 00:00:00',
                Helper::dateYMD($endDate).' 23:59:59'
            ]);
        }
        if($merchantId){
            $qO->where('merchant_id',$merchantId);
        }
        $orders = $qO->orderByDesc('id')->get();
        foreach($orders as $order){
            $order->product_type = $order->product_type ? $order->product_type : 'Others';
            $order->merchant_name = $order->merchant->username;
            $order->merchant_phone = $order->merchant->phone;
            $order->driver_name = $order->driver?->username;
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
        $search = $req->query('search');
        $startDate = $req->startDate;
        $statusId = $req->status_id;
        $endDate = $req->endDate;
        $arriveStartDate = $req->arrive_start_date;
        $arriveEndDate = $req->arrive_end_date ?? $arriveStartDate;
        $lang = $req->lang;

        $appliedFilters = [];
        // Base query
        $qP = Package::query()
            ->where('is_deleted', 0)
            ->where('outstanding', 0)
            ->with([
                'status',
                'driver:id,code,username,phone',
                'merchant:id,code,username,phone',
                'returnUser:id,code,username,phone',
                'pickupDriver:id,code,username,phone',
                'merchant.merchantPriceList',
                'merchant.merchantPriceList.priceList.priceListName',
                'branchLocation:id,name_en'
            ])
            ->selectRaw('
                zone_name,zone_code,qr_code,merchant_id,driver_id,returned_uid,payer,price_khr,
                driver_cod_usd,driver_cod_khr,receiver_address,remarks,receiver_phone,cod,price,delivery_fee,
                additional_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,
                assign_driver_datetime,updated_at,failed_datetime,returned_datetime,delivered_datetime,
                other_fee,created_at,product_type,taxi_fee,pickup_uid,branch_id
            ');

        // Filters
        if($search){
            $searchCallback = function ($q) use ($search) {
                $q->where('qr_code','LIKE',"%{$search}%")
                ->orWhere('receiver_phone','LIKE',"%{$search}%")
                ->orWhere('zone_code','LIKE',"%{$search}%");
            };

            $appliedFilters[] = fn($q) => $q->where($searchCallback);
            $qP->where($searchCallback);
        } else {
            $branchId = $req->branch_id;
            $warehouseId = $req->warehouse_id;
            $merchantId = $req->merchant_id;
            $pickupDriverId = $req->query('pickup_driver_id');
            $hasRemarks = $req->query('has_remark');
            $priceListId = $req->query('price_list_id');
            $zoneCode = $req->query('zone_code');
            $driverId = $req->query('driver_id');

            if ($driverId) {
                $driverFilter = function ($q) use ($driverId) {
                    $q->where(function ($q2) use ($driverId) {
                        $q2->whereIn('status_id', [23,11])
                            ->where('returned_uid', $driverId);
                    })

                    ->orWhere(function ($q2) use ($driverId) {
                        $q2->whereNotIn('status_id', [23,11])
                            ->where('driver_id', $driverId);
                    });
                };
                $appliedFilters[] = $driverFilter;
                $qP->where($driverFilter);
            }
            if ($hasRemarks == 1) {
                $appliedFilters[] = fn($q) => $q->whereNull('delivery_remarks');
                $qP->whereNull('delivery_remarks');
            }
            if ($hasRemarks == 2) {
                $appliedFilters[] = fn($q) => $q->whereNotNull('delivery_remarks');
                $qP->whereNotNull('delivery_remarks');
            }
            if ($statusId) {
                $arr = explode(',', $statusId);
                $appliedFilters[] = fn($q) => $q->whereIn('status_id', $arr);
                $qP->whereIn('status_id', $arr);
            }

            if ($merchantId) {
                $appliedFilters[] = fn($q) => $q->where('merchant_id', $merchantId);
                $qP->where('merchant_id', $merchantId);
            }
            if ($branchId) {
                $appliedFilters[] = fn($q) => $q->where('branch_id', $branchId);
                $qP->where('branch_id', $branchId);
            }
            if ($warehouseId) {
                $appliedFilters[] = fn($q) => $q->where('warehouse_id', $warehouseId);
                $qP->where('warehouse_id', $warehouseId);
            }
            if ($pickupDriverId) {
                $appliedFilters[] = fn($q) => $q->where('pickup_uid', $pickupDriverId);
                $qP->where('pickup_uid', $pickupDriverId);
            }

            if ($priceListId) {
                $priceListCallback = function ($q) use ($priceListId) {
                    $q->whereHas('merchant.merchantPriceList', function($q2) use ($priceListId) {
                        $q2->where('price_list_id', $priceListId);
                    });
                };

                $appliedFilters[] = $priceListCallback;
                $qP->where($priceListCallback);
            }
            // if($statusId) $qP->whereIn('status_id', explode(',',$statusId));
            // if($merchantId) $qP->where('merchant_id', $merchantId);
            // if($branchId) $qP->where('branch_id', $branchId);
            // if($warehouseId) $qP->where('warehouse_id', $warehouseId);
            // if($pickupDriverId) $qP->where('pickup_uid', $pickupDriverId);
            // if($priceListId) {
            //     $qP->whereHas('merchant.merchantPriceList.priceList', function($q) use($priceListId){
            //         $q->where('price_list_name_id', $priceListId);
            //     });
            // }
            if($zoneCode) $qP->where('zone_code', $zoneCode);
            if ($zoneCode) {
                $appliedFilters[] = fn($q) => $q->where('zone_code', $zoneCode);
                $qP->where('zone_code', $zoneCode);
            }

            // Date filters
            if ($startDate && $endDate) {

                $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
                $endDatetime = Helper::dateYMD($endDate).' 23:59:59';

                $dateCallback = function ($q) use ($startDatetime,$endDatetime) {
                    $q->whereBetween('failed_datetime', [$startDatetime,$endDatetime])->whereIn('status_id',[10,19])
                    ->orWhereBetween('delivered_datetime', [$startDatetime,$endDatetime])->where('status_id',9)
                    ->orWhereBetween('returned_datetime', [$startDatetime,$endDatetime])->where('status_id',23);
                };

                $appliedFilters[] = $dateCallback;

                $qP->where($dateCallback);

            } elseif ($arriveStartDate && $arriveEndDate) {

                $arriveStart = Helper::dateYMD($arriveStartDate) . ' 00:00:00';
                $arriveEnd = Helper::dateYMD($arriveEndDate) . ' 23:59:59';

                $dateCallback = fn($q) => $q->whereBetween('arrive_warehouse_datetime', [$arriveStart, $arriveEnd]);

                $appliedFilters[] = $dateCallback;

                $qP->whereBetween('arrive_warehouse_datetime', [$arriveStart, $arriveEnd]);
            }
            // if($startDate && $endDate){
            //     $startDatetime = Helper::dateYMD($startDate) . ' 00:00:00';
            //     $endDatetime = Helper::dateYMD($endDate) . ' 23:59:59';
            //     $qP->where(function($q) use($startDatetime, $endDatetime){
            //         $q->whereBetween('failed_datetime', [$startDatetime, $endDatetime])->whereIn('status_id', [10,19])
            //         ->orWhereBetween('delivered_datetime', [$startDatetime, $endDatetime])->where('status_id', 9)
            //         ->orWhereBetween('returned_datetime', [$startDatetime, $endDatetime])->where('status_id', 23);
            //     });
            // } elseif ($arriveStartDate && $arriveEndDate){
            //     $arriveStartDateTime = Helper::dateYMD($arriveStartDate) . ' 00:00:00';
            //     $arriveEndDateTime = Helper::dateYMD($arriveEndDate) . ' 23:59:59';
            //     $qP->whereBetween('arrive_warehouse_datetime', [$arriveStartDateTime, $arriveEndDateTime]);
            // }
        }

        // Calculate grand totals using a fresh query (avoid group by issues)
        $grandTotalsQuery = Package::query()
            ->where('is_deleted',0)
            ->where('outstanding',0);

        foreach ($appliedFilters as $callback) {
            $grandTotalsQuery->where($callback);
        }

        if($statusId){
            $grandTotalsQuery->whereIn('status_id', explode(',',$statusId));
        }else{
            $grandTotalsQuery->whereIn('status_id',[5,6,9,10,11,19,23]);
        }

        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate) . ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate) . ' 23:59:59';
            $grandTotalsQuery->where(function($q) use($startDatetime, $endDatetime){
                $q->whereBetween('failed_datetime', [$startDatetime, $endDatetime])->whereIn('status_id', [10,19])
                    ->orWhereBetween('delivered_datetime', [$startDatetime, $endDatetime])->where('status_id', 9)
                    ->orWhereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime])->where('status_id', 5)
                    ->orWhereBetween('assign_driver_datetime', [$startDatetime, $endDatetime])->where('status_id', 6)
                    ->orWhereBetween('returned_datetime', [$startDatetime, $endDatetime])->where('status_id', 11);
            });
        } elseif($arriveStartDate && $arriveEndDate){
            $arriveStartDateTime = Helper::dateYMD($arriveStartDate) . ' 00:00:00';
            $arriveEndDateTime = Helper::dateYMD($arriveEndDate) . ' 23:59:59';
            $grandTotalsQuery->whereBetween('arrive_warehouse_datetime', [$arriveStartDateTime, $arriveEndDateTime]);
        }
        

        $grandTotals = $grandTotalsQuery->selectRaw("
            SUM(price) as price,
            SUM(price_khr) as price_khr,
            SUM(delivery_fee) as base_fee,
            SUM(other_fee) as other_fee,
            SUM(taxi_fee) as taxi_fee,
            SUM(price) as merchant_cod_usd,
            SUM(price_khr) as merchant_cod_khr,
            SUM(driver_cod_usd) as driver_cod_usd,
            SUM(driver_cod_khr) as driver_cod_khr
        ")->first();

        // Format grand totals
        $grand = [
            'price' => Helper::getNumber($grandTotals->price ?? 0, 2, true),
            'price_khr' => Helper::getNumber($grandTotals->price_khr ?? 0, 0, true),
            'base_fee' => Helper::getNumber($grandTotals->base_fee ?? 0, 2, true),
            'other_fee' => Helper::getNumber($grandTotals->other_fee ?? 0, 2, true),
            'taxi_fee' => Helper::getNumber($grandTotals->taxi_fee ?? 0, 2, true),
            'driver_cod_usd' => Helper::getNumber($grandTotals->driver_cod_usd ?? 0, 2, true),
            'driver_cod_khr' => Helper::getNumber($grandTotals->driver_cod_khr ?? 0, 0, true),
            'merchant_cod_usd' => Helper::getNumber($grandTotals->merchant_cod_usd ?? 0, 2, true),
            'merchant_cod_khr' => Helper::getNumber($grandTotals->merchant_cod_khr ?? 0, 2, true),
        ];

        // Transform callback
        $callback = function($q) use($lang) {
            $q->status_code = $lang == 'km' 
                ? GeneralSettingService::$statusCodeTrans[$q->status_id] ?? '' 
                : $q->status->name;
            $q->merchant_name = $q->merchant->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->driver_name = ($q->status_id == 11 || $q->status_id == 23) ? $q->returnUser?->username : $q->driver?->username;
            $q->driver_phone = $q->driver?->phone;
            $q->pickup_driver_name = $q->pickupDriver?->username;
            $q->pickup_driver_phone = $q->pickupDriver?->phone;
            $q->price_list = $q->merchant?->merchantPriceList?->priceListName->name ?? null;
            $q->branch_name = $q->branchLocation->name_en ?? null;
            // Log::info(json_encode($q->merchant?->merchantPriceList?->priceList,JSON_PRETTY_PRINT));

            $fees = (float)($q->delivery_fee + $q->other_fee); 
            $driverCodUsd = (float)($q->driver_cod_usd ?? 0);
            $driverCodKhr = (float)($q->driver_cod_khr ?? 0);
            $taxiFee = (float)$q->taxi_fee;

            $merchantTotal = PackageTrailServiceImpl::calculateCodAmtBothCurrencies(
                $driverCodUsd, $driverCodKhr, 'merchant', $q->payer, $q->status_id, $fees, $taxiFee
            );

            $q->driver_total = $driverCodUsd;
            $q->driver_total_khr = $driverCodKhr;
            $q->merchant_total = $merchantTotal['amount_usd'];
            $q->merchant_total_khr = $merchantTotal['amount_khr'];
            $q->merchant_cod_usd = $q->price;
            $q->merchant_cod_khr = $q->price_khr;
            $q->base_fee = $q->delivery_fee;
            $actionDate = match($q->status_id){
                10, 19 => $q->failed_datetime,
                9 => $q->delivered_datetime,
                23 => $q->returned_datetime,
                default => null
            };
            $q->finished_date = $actionDate ? Helper::formatCustomDateTime($actionDate,'d-M-Y h:i A') : null;
            $q->makeHidden(['status','merchant','branchLocation','driver','returnUser','pickupDriver']);
            return $q;
        };

        $qP->orderByDesc('created_at');

        $additionalKeys = [
            'title' => 'Daily Packages',
            'sub_title' => 'Arrive Date:',
            'date' => Helper::dateDMY($startDate ?? $arriveStartDate).' to '.Helper::dateDMY($endDate ?? $arriveEndDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'grand' => $grand,
        ];

        return ApiResponse::PaginationV1(
            query: $qP,
            filter: $req,
            transformCallback: $callback,
            additionalKey: $additionalKeys,
            limit: 500 // safe for large dataset, adjust as needed
        );
    }





    // public function getDailyPackageReport(Request $req){
    //     $user = UserService::getAuthUser();
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $lang = $req->lang;
    //     $statusId = $req->status_id;
    //     $branchId = $req->branch_id;
    //     $warehouseId = $req->warehouse_id;
    //     $merchantId = $req->merchant_id;
    //     $qP = Package::where('is_deleted',0)
    //     ->with([
    //         'status',
    //         'driver:id,code,username,phone',
    //         'merchant:id,code,username,phone',
    //         'returnUser:id,code,username,phone',
    //         'pickupDriver:id,code,username,phone',
    //         'merchant.merchantPriceList',
    //         'merchant.merchantPriceList.priceList.priceListName'
    //     ])
    //     ->where('outstanding',0)
    //     ->selectRaw('
    //         zone_name,qr_code,merchant_id,driver_id,returned_uid,payer,price_khr,
    //         driver_cod_usd,driver_cod_khr,receiver_address,remarks,receiver_phone,cod,price,delivery_fee,
    //         additional_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,
    //         assign_driver_datetime,updated_at,failed_datetime,returned_datetime,delivered_datetime,
    //         other_fee,created_at,product_type,taxi_fee,pickup_uid'
    //     );
    //     if($statusId){
    //         $qP->where('status_id',$statusId);
    //     }
    //     if($merchantId){
    //         $qP->where('merchant_id',$merchantId);
    //     }
    //     if($branchId){
    //         $qP->where('branch_id',$branchId);
    //     }
    //     if($warehouseId){
    //         $qP->where('warehouse_id',$warehouseId);
    //     }

    //     $grand = [
    //         'price' => 0,
    //         'price_khr' => 0,
    //         'fees' => 0,
    //         'other_fee' => 0,
    //         'taxi_fee' => 0,
    //         'base_fee' => 0,
    //         'driver_total' => 0,
    //         'merchant_total' => 0,
    //         'driver_total_khr' => 0,
    //         'merchant_total_khr' => 0
    //     ];

    //     if($startDate && $endDate){
    //         $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
    //         $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
    //         $qP->where(function($q) use ($startDatetime, $endDatetime) {
    //             $q->where(function($q) use ($startDatetime, $endDatetime) {
    //                 // For status_id 19, query only failed_datetime
    //                 $q->whereBetween('failed_datetime', [$startDatetime, $endDatetime])
    //                 ->whereIn('status_id', [10,19]);
    //             })
    //             ->orWhere(function($q) use ($startDatetime, $endDatetime) {
    //                 // For status_id 9, query only delivered_datetime
    //                 $q->whereBetween('delivered_datetime', [$startDatetime, $endDatetime])
    //                 ->where('status_id', 9);
    //             })
    //             ->orWhere(function($q) use ($startDatetime, $endDatetime) {
    //                 // For status_id 9, query only delivered_datetime
    //                 $q->whereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime])
    //                 ->where('status_id', 5);
    //             })
    //             ->orWhere(function($q) use ($startDatetime, $endDatetime) {
    //                 // For status_id 9, query only delivered_datetime
    //                 $q->whereBetween('assign_driver_datetime', [$startDatetime, $endDatetime])
    //                 ->where('status_id', 6);
    //             })

    //             ->orWhere(function($q) use ($startDatetime, $endDatetime) {
    //                 // For status_id 11, query only returned_datetime
    //                 $q->whereBetween('returned_datetime', [$startDatetime, $endDatetime])
    //                 ->where('status_id', 11);
    //             });
    //         });
    //     }

    //     $packages = $qP->limit(50)->orderByDesc('created_at')->get()
    //     ->each(function ($q) use($lang,&$grand){
    //         if($lang == 'km'){
    //             $q->status_code = GeneralSettingService::$statusCodeTrans[$q->status_id] ?? '';
    //         }else $q->status_code = $q->status->name;
    //         $q->merchant_name = $q->merchant->username;
    //         $q->merchant_phone = $q->merchant->phone;
    //         $q->driver_name = $q->status_id == 11 ? $q->returnUser?->username : $q->driver?->username;
    //         $q->driver_phone = $q->driver?->phone;
    //         $q->pickup_driver_name = $q->pickupDriver?->username;
    //         $q->pickup_driver_phone = $q->pickupDriver?->phone;
    //         $q->price_list = $q->merchant?->merchantPriceList?->priceList?->priceListName->name ?? null;
    //         // $q->cod_fee = $q->price;
    //         $merchantTotal = $q->cod ? $q->price:0;
    //         $fees = (float)($q->delivery_fee + $q->other_fee); 
    //         $grand['taxi_fee'] += $q->taxi_fee;
    //         if($q->payer == 'sender'){
    //             $merchantTotal -= $fees + $q->taxi_fee;
    //         }
    //         $driverCodUsd =(float)($q->driver_cod_usd ?? 0);
    //         $driverCodKhr = (float)($q->driver_cod_khr ?? 0);
    //         $taxiFee = (float)$q->taxi_fee;
    //         // $driverTotal = PackageTrailServiceImpl::calculateCodAmtBothCurrencies(
    //         //     $driverCodUsd,
    //         //     $driverCodKhr,
    //         //     'driver',
    //         //     $q->payer,
    //         //     $q->status_id,
    //         //     $fees,
    //         //     $taxiFee
    //         // );
    //         $merchantTotal = PackageTrailServiceImpl::calculateCodAmtBothCurrencies(
    //             $driverCodUsd,
    //             $driverCodKhr,
    //             'merchant',
    //             $q->payer,
    //             $q->status_id,
    //             $fees,
    //             $taxiFee
    //         );
    //         $q->driver_total = $driverCodUsd;
    //         $q->driver_total_khr = $driverCodKhr;
    //         $q->merchant_total = $merchantTotal['amount_usd'];
    //         $q->merchant_total_khr = $merchantTotal['amount_khr'];
    //         // $grand['cod'] += $q->driver_total;
    //         $grand['driver_total'] += $driverCodUsd;
    //         $grand['driver_total_khr'] += $driverCodKhr;
    //         $grand['merchant_total'] += $merchantTotal['amount_usd'];
    //         $grand['merchant_total_khr'] += $merchantTotal['amount_khr'];
    //         $grand['price'] += $q->price;
    //         $grand['price_khr'] += $q->price_khr;
    //         $grand['base_fee'] += $q->delivery_fee;
    //         $grand['other_fee'] += $q->other_fee;
    //         // $q->merchant_total = $merchantTotal;
    //         $q->base_fee = $q->delivery_fee;
    //         $q->arrive_warehouse_datetime = Helper::formatCustomDateTime($q->arrive_warehouse_datetime,'d-M-Y');
    //         $actionDate = null;
    //         if ($q->status_id == 5) $actionDate = Helper::formatCustomDateTime($q->arrive_warehouse_datetime,'d-M-Y h:i A');
    //         if ($q->status_id == 6) $actionDate = Helper::formatCustomDateTime($q->assign_driver_datetime,'d-M-Y h:i A');
    //         if ($q->status_id == 10) $actionDate = Helper::formatCustomDateTime($q->failed_datetime,'d-M-Y h:i A');
    //         if ($q->status_id == 9) $actionDate = Helper::formatCustomDateTime($q->delivered_datetime,'d-M-Y h:i A');
    //         if ($q->status_id == 19) $actionDate = Helper::formatCustomDateTime($q->failed_datetime,'d-M-Y h:i A');
    //         if ($q->status_id == 11) $actionDate = Helper::formatCustomDateTime($q->returned_datetime,'d-M-Y h:i A');
    //         $q->action_date = $actionDate;
    //         $q->makeHidden(['status','merchant','driver','returnUser','pickupDriver']);
    //     });

    //     foreach($grand as $key=>$value){
    //         $dec = 2;
    //         if(in_array($value,['price_khr','driver_total_khr','merchant_total_khr'])){
    //             $dec = 0;
    //         }
    //         $grand[$key] = Helper::getNumber($value,$dec,true);
    //     }

    //     $obj =(object)[
    //         'title' => 'Daily Packages',
    //         'sub_title' => 'Arrivate Date:',
    //         'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
    //         'company_profile' => CompanyProfileService::profileInfo($user),
    //         'grand' => $grand,
    //         'list' => $packages
    //     ];
    //     return ApiResponse::JsonResult($obj,'Get Pickup List');
    // }

    public function getDailyPackageSummaryReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $merchantId = $req->merchant_id;
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $qP = Package::where('is_deleted',0)
        ->where('outstanding',0)
        ->with(['merchant']);
        if($branchId){
            $qP->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qP->where('warehouse_id',$warehouseId);
        }
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
                    OR (status_id = 23 AND returned_datetime BETWEEN ? AND ?)",
                    [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
                );
            });
        }
        $packages = $qP->selectRaw('
            DATE(created_at) as created_date,failed_datetime,delivered_datetime,arrive_warehouse_datetime,
            merchant_id,status_id,delivery_fee,cod,assign_driver_datetime,returned_datetime
        ')
        ->whereIn('status_id',[5,6,9,10,23,19])
        ->orderByDesc('created_date')
        ->get();
        $groupedPackages = collect($packages)->map(function ($pkg) {
            $groupDate = $pkg->created_date;
            if($pkg->status_id === TrackingStatus::ON_DELIVERY->value){
                $groupDate = $pkg->assign_driver_datetime;
            }
            else if(in_array('status_id',[TrackingStatus::FAILED->value,TrackingStatus::FAILED_WITH_FEE->value])){
                $groupDate = $pkg->failed_datetime;
            }
            else if($pkg->status_id === TrackingStatus::ON_DELIVERY->value){
                $groupDate = $pkg->assign_driver_datetime;
            }
            else if($pkg->status_id === TrackingStatus::DELIVERED->value){
                $groupDate = $pkg->delivered_datetime;
            }
            else if($pkg->status_id === TrackingStatus::RETURNED->value){
                $groupDate = $pkg->returned_datetime;
            }
            else if($pkg->status_id === TrackingStatus::AT_WAREHOUSE->value){
                $groupDate = $pkg->arrive_warehouse_datetime;
            }
            $pkg->groupKey = date('d-m-Y',strtotime($groupDate));
            return $pkg;
        })
        ->groupBy('groupKey')
        ->map(function ($group, $date) {
            $uniqueMerchants = $group->unique('merchant_id');
            $uniqueMerchants->each(function ($item) use ($group) {
                $item->merchant_name = $item->merchant->username;
                $item->merchant_phone = $item->merchant->phone;
                $item->merchant_address = $item->merchant->address;
                $item->merchant_code = $item->merchant->code;
                $item->driver_name = $item->driver?->username;
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
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $qP = Payment::fromRaw('payments as p')
        ->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',1)
        ->join('users as ap','ap.id','p.approved_uid')
        ->join('users as st','st.id','p.settled_uid')
        ->selectRaw('p.payable_amount,p.id as payment_id,d.username as payer_name,ap.username as receiver_name,p.exchange_rate,p.taxi_fee,p.approved,p.payment_datetime,p.is_settled,st.username as settlement_user,p.payer_id')
        ->orderByDesc('payment_datetime');

        if($branchId){
            $qP->where('p.branch_id',$branchId);
        }

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
        //         $item->merchant_name = $item->merchant->username;
        //         $item->merchant_phone = $item->merchant->phone;
        //         $item->merchant_address = $item->merchant->address;
        //         $item->merchant_code = $item->merchant->code;
        //         $item->driver_name = $item->driver?->username;
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
        $obj = (object)[
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
        $branchId = $req->query('branch_id');
        $summary = $this->getOperationSummary($startDate, $endDate);
        $operationSummary = $summary->operation;
        $financialSummary = $summary->financial;
        $countDriver = $summary->count_driver;
        $qPmt = Payment::from('payments as pmt')->where('pmt.is_deleted',0)->where('pmt.is_settled',1)->join('payment_details as pd','pmt.id','pd.payment_id')->selectRaw('SUM(pd.amount) as total,pd.currency_code,CASE WHEN pd.method != \'cash\' THEN \'bank\' ELSE pd.method END as method_group')->groupBy(DB::raw("CASE WHEN pd.method != 'cash' THEN 'bank' ELSE pd.method END"),'pd.currency_code');
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00'; //
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qPmt->whereBetween('payment_datetime',[$startDatetime,$endDatetime]);
        }
        if($branchId){
            $qPmt->where('branch_id',$branchId);
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
        $mapBank = [
            'bank' => [
                'USD' => 0, // Bank USD
                'KHR' => 1, // Bank KHR
            ],
            'cash' => [
                'USD' => 2, // Cash USD
                'KHR' => 3, // Cash KHR
            ],
        ];


        foreach ($payments as $payment) {
            $method = $payment->method_group;
            $currency = $payment->currency_code;
            if (isset($mapBank[$method][$currency])) {
                $index = $mapBank[$method][$currency];
                $closedFinancialSummary[$index]['amount'] += $payment->total;
            }
        }

        // Format after summing
        foreach ($closedFinancialSummary as &$summary) {
            $summary['amount'] = number_format($summary['amount'], 2);
        }
        unset($summary); // avoid reference issues

        $obj =(object)[
            'title' => 'Summary Report',
            'sub_title' => 'Arrivate Date:',
            'exchange_rate' => GeneralSettingService::getLatestXRate()->buy_rate,
            'driver_count' => $countDriver,
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($user),
            'operation_summary' => $operationSummary,
            'financial_summary' => $financialSummary,
            'closed_financial_summary' => $closedFinancialSummary
        ];
        return ApiResponse::JsonResult($obj,'Get Settle Statement');
    }

    // public function getOperationSummaryReport(Request $req){
    //     $user = UserService::getAuthUser();
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $branchId = $req->branch_id;
    //     // $warehouseId = $req->warehouse_id;
    //     $summary = $this->getOperationSummary($startDate, $endDate,$branchId);
    //     $operationSummary = $summary->operation;
    //     $financialSummary = $summary->financial;

    //     $qPmt = Payment::from('payments as pmt')->where('pmt.is_deleted',0)->where('pmt.is_settled',1)->join('payment_details as pd','pmt.id','pd.payment_id')->selectRaw('SUM(pd.amount) as total,pd.currency_code,CASE WHEN pd.method != \'cash\' THEN \'bank\' ELSE pd.method END as method_group')->groupBy(DB::raw("CASE WHEN pd.method != 'cash' THEN 'bank' ELSE pd.method END"),'pd.currency_code');
    //     if($startDate && $endDate){
    //         $startDatetime = Helper::dateYMD($startDate). ' 00:00:00'; //
    //         $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
    //         $qPmt->whereBetween('payment_datetime',[$startDatetime,$endDatetime]);
    //     }
    //     if($branchId){
    //         $qPmt->where('branch_id',$branchId);
    //     }
    //     $payments = $qPmt->get();
    //     $closedFinancialSummary = [
    //         [
    //             'title' => 'Total collective payment by Bank(USD)',
    //             'amount' => 0
    //         ],
    //         [
    //             'title' => 'Total collective payment by Bank(KHR)',
    //             'amount' => 0
    //         ],
    //         [
    //             'title' => 'Total collective payment by Cash(USD)',
    //             'amount' => 0
    //         ],
    //         [
    //             'title' => 'Total collective payment by Cash(KHR)',
    //             'amount' => 0
    //         ],
    //     ];
    //     foreach ($payments as $payment) {
    //         if ($payment->method_group == 'bank') {
    //             if ($payment->currency_code == 'USD') {
    //                 $closedFinancialSummary[0]['amount'] += $payment->total;  // Bank USD
    //             } elseif ($payment->currency_code == 'KHR') {
    //                 $closedFinancialSummary[1]['amount'] += $payment->total;  // Bank KHR
    //             }
    //         } elseif ($payment->method_group == 'cash') {
    //             if ($payment->currency_code == 'USD') {
    //                 $closedFinancialSummary[2]['amount'] += $payment->total;  // Cash USD
    //             } elseif ($payment->currency_code == 'KHR') {
    //                 $closedFinancialSummary[3]['amount'] += $payment->total;  // Cash KHR
    //             }
    //         }
    //     }
    //     $obj =(object)[
    //         'title' => 'Summary Report',
    //         'sub_title' => 'Arrivate Date:',
    //         'exchange_rate' => 4100,
    //         'driver_count' => 0,
    //         'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
    //         'company_profile' => CompanyProfileService::profileInfo($user),
    //         'operation_summary' => $operationSummary,
    //         'financial_summary' => $financialSummary,
    //         'closed_financial_summary' => $closedFinancialSummary
    //     ];
    //     return ApiResponse::JsonResult($obj,'Get Settle Statement');
    // }

    private function getOperationSummary($startDate, $endDate)
    {
        $startDate = $startDate ? Helper::dateYMD($startDate) : null;
        $endDate = $endDate ? Helper::dateYMD($endDate) : null;
        $startDatetime = $startDate ? "$startDate 00:00:00" : null;
        $endDatetime = $endDate ? "$endDate 23:59:59" : null;
        $exchangeRate = 4050;
        $operationSum = [
            'merchantCount'       => 0,
            'deliveredCount'      => 0,
            'atWarehouseCount'    => 0,
            'onDeliveryCount'     => 0,
            'failedCount'         => 0,
            'returnedCount'       => 0,
            'failedWithFeeCount'  => 0,
        ];

        // Base package query
        $packageQuery = Package::query()
            ->where('is_deleted', false)
            ->where('outstanding', 0)
            ->whereIn('status_id', [5, 6, 9, 10, 11, 19]);

        // $payment
        if ($startDatetime && $endDatetime) {
            $packageQuery->where(function ($q) use ($startDatetime, $endDatetime) {
                $q->where(function ($q) use ($startDatetime, $endDatetime) {
                    $q->where('status_id', 5)
                    ->whereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime]);
                })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                    $q->whereIn('status_id', [2, 3, 4])
                    ->whereBetween('pickup_datetime', [$startDatetime, $endDatetime]);
                })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                    $q->where('status_id', 6)
                    ->whereBetween('assign_driver_datetime', [$startDatetime, $endDatetime]);
                })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                    $q->whereIn('status_id', [10, 19])
                    ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
                })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                    $q->where('status_id', 9)
                    ->whereBetween('delivered_datetime', [$startDatetime, $endDatetime]);
                })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                    $q->where('status_id', 11)
                    ->whereBetween('returned_datetime', [$startDatetime, $endDatetime]);
                });
            });
        }

        $packages = $packageQuery->get([
            'id', 'merchant_id', 'driver_id', 'status_id',
            'cod', 'price', 'delivery_fee', 'payer',
            'other_fee','taxi_fee'
        ]);


        // Log::info(json_encode($packages->toArray()));

        // Group by status and count, avoid nested loops
        $statusCounter = [
            5  => 'atWarehouseCount',
            6  => 'onDeliveryCount',
            9  => 'deliveredCount',
            10 => 'failedCount',
            11 => 'returnedCount',
            19 => 'failedWithFeeCount',
        ];

        $uniqueMerchants = [];
        $driverIds = [];

        foreach ($packages as $p) {
            $operationSum[$statusCounter[$p->status_id]]++;
            $uniqueMerchants[$p->merchant_id] = true;
            $driverIds[$p->driver_id] = true;
            // Log::info("Payer : {$p->payer}, Status ID: {$p->status_id}, Merchant Disbursement ID: {$p->merchant_disbursement_id}, Merchant Payment ID: {$p->merchant_payment_id}");
        }

        // Orders
        $orderQuery = Order::query()
            ->where('is_deleted', 0)
            ->where('status_id', 5);

        if ($startDatetime && $endDatetime) {
            $orderQuery->whereBetween('pickup_datetime', [$startDatetime, $endDatetime]);
        }

        $pickupCount = $orderQuery->sum('qty');
        // Filter packages with status_id = 9 or 19
        $financialPackages = $packages->whereIn('status_id', [9, 19]);

        // COD total: only for status_id = 9 and cod = true
        $pkgPrice = $packages
            ->filter(fn($p) => $p->status_id === 9 && $p->cod)
            ->sum('price');

        $receiverFeesTotal = $packages
        ->filter(fn($p) => $p->payer === 'receiver')
        ->sum(fn($p) => $p->delivery_fee + $p->other_fee);
        $codTotal = $pkgPrice + $receiverFeesTotal;

        // Total delivery + extra fees (status 9 and 19)
        $feesTotal = $financialPackages
            ->sum(fn($p) => $p->delivery_fee + $p->other_fee);

        // Fees that merchant still owes (no disbursement or payment)
        $merchantOweFees = $financialPackages
        ->filter(fn($p) =>
            $p->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'p.id')
                ->where('pp.payer_type', 'driver')
                ->where('pp.is_deleted', false);
            }) &&
            $p->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.is_deleted', false);
            }) &&
            $p->payer === 'sender'
        )
        ->sum(fn($p) => $p->delivery_fee + $p->other_fee + $p->taxi_fee);


        $payback = $codTotal - $merchantOweFees;

        // Final Object
        return (object)[
            'count_driver' => count($driverIds),
            'operation' => [
                ['title' => 'Count merchants',                    'count' => count($uniqueMerchants)],
                ['title' => 'Total pacakge \'Pickup\'',           'count' => $pickupCount],
                ['title' => 'Total package \'At Warehouse\'',     'count' => $operationSum['atWarehouseCount']],
                ['title' => 'Total package \'On Delivery\'',      'count' => $operationSum['onDeliveryCount']],
                ['title' => 'Total Package \'Delivered\'',        'count' => $operationSum['deliveredCount']],
                ['title' => 'Total Package \'Failed\'',           'count' => $operationSum['failedCount']],
                ['title' => 'Total Package \'Fail With Fee\'',    'count' => $operationSum['failedWithFeeCount']],
                ['title' => 'Total Package \'Returned\'',         'count' => $operationSum['returnedCount']],
            ],
            'financial' => [
                [
                    'title' => 'Total collective COD',
                    'amount' => number_format($codTotal, 2),
                    'amount_kh' => number_format($codTotal * $exchangeRate, 2)
                ],
                [
                    'title' => 'Total Fees',
                    'amount' => number_format($feesTotal, 2),
                    'amount_kh' => number_format($feesTotal * $exchangeRate, 2)
                ],
                [
                    'title' => 'Total Fees owe by \'Merchants\'',
                    'amount' => number_format($merchantOweFees, 2),
                    'amount_kh' => number_format($merchantOweFees * $exchangeRate, 2)
                ],
                [
                    'title' => 'Total money to pay back merchants',
                    'amount' => number_format($payback, 2),
                    'amount_kh' => number_format($payback * $exchangeRate, 2)
                ],
                // [
                //     'title' => 'Expected Receive fees',
                //     'amount' => number_format($totalReceivedFees, 2),
                //     'amount_kh' => number_format($totalReceivedFees * $exchangeRate, 0)
                // ],
            ]
        ];
    }


    public function getReviewAndFeedBackReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $branchId = $req->branch_id;
        $qP = FeedBack::where('is_deleted',0)
        ->selectRaw('id,create_uid,rate,created_at,comments')
        ->with('merchant');
        if($branchId){
            $qP->where('branch_id',$branchId);
        }
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qP->whereBetween('created_at',[$startDatetime,$endDatetime]);
        }

        $feedBack = $qP->orderByDesc('id')->get();
        $total = 0;
        foreach($feedBack as $fd){
            $fd->name = $fd->merchant->username;
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
            'branches' => GeneralSettingService::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getDailyPackageReportOption(Request $req){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'statuses' => GeneralSettingService::optionsTrackingStatus($user,[],[5,6,9,10,11,19,23]),
            'branches' => GeneralSettingService::optionsBranch(),
            'zones' => GeneralSettingService::optionsZone($user,'child'),
            'branches' => GeneralSettingService::optionsBranch(),
            'price_list' => GeneralSettingService::optionsPriceListName($user),
            'drivers' => GeneralSettingService::optionsDriver($user),
            'merchants' => GeneralSettingService::optionsMerchant($user),
            'has_remarks' => [
                [
                    'label' => 'All',
                    'value' => 0,
                ],
                [
                    'label' => 'No remarks',
                    'value' => 1,
                ],
                [
                    'label' => 'Has remarks',
                    'value' => 2
                ]
            ]
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPickupReportOption(){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'merchants' => GeneralSettingService::optionsMerchant($user),
            'branches' => GeneralSettingService::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getDailyPackageSummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'merchants' => GeneralSettingService::optionsMerchant($user),
            'branches' => GeneralSettingService::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    //** END::COMPANY REPORT */




    // **BEGIN::DRIVER REPORT
    public function getDriverListReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $qD = User::where('account_type','driver')
        ->where('is_deleted',false)
        ->selectRaw('code,username,gender,shift_type,phone,address,vehicle_type,plate_number,lock,employment_date');

        if($branchId){
            $qD->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qD->where('warehouse_id',$warehouseId);
        }

        $drivers = $qD->get();
        foreach($drivers as $driver){
            $driver->status_code = $driver->lock ? 'Inactive' : 'Active';
        }
        $obj =(object)[
            'title' => 'Daily Packages Summary',
            'status' => 'All Driver',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'total' => $drivers->count(),
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
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $qP = Package::where('is_deleted',0)
        ->whereIn('status_id',[9,10,11,19]);
        $qD = User::where('account_type','driver')
        ->selectRaw('id,code,username,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $oD = Order::where('status_id',5)->where('is_deleted',0)->selectRaw('qty,driver_id');

        if($branchId){
            $qP->where('branch_id',$branchId);
            $qD->where('branch_id',$branchId);
            $oD->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qP->where('warehouse_id',$warehouseId);
            $qD->where('driver_warehouse_id',$warehouseId);
            $oD->where('warehouse_id',$warehouseId);
        }
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

    // public function getDailyMerchantActivities(Request $req){
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $merchantId = $req->merchant_id;
    //     $mP = Package::from('packages as p')->where('p.is_deleted',0)->where('p.outstanding',0)->join('users as m','m.id','p.merchant_id');
    //     // $merchantPackages =
    // }

    public function getDriverPaymentReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $driverId = $req->driver_id;
        $allPayments = [];
        $amount = 0;
        $amountKh = 0;
        $total = 0;
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $xRate = GeneralSettingService::getLatestXRate()->buy_rate;
        $qP = Payment::with(['driver:id,username,code','cashier:id,username'])
        ->where('is_deleted',0)
        ->where('payer_type','driver')
        ->selectRaw('id,payer_id,payment_datetime,breakdown_notes,exchange_rate,payable_amount as amount,approved_uid');
        $qD = Disbursement::with(['driver:id,username,code','cashier:id,username'])
        ->where('is_deleted',0)->where('payee_type','driver')
        ->where('type','payment')
        ->selectRaw('id,payee_id,payment_datetime,breakdown_notes,exchange_rate,payable_amount as amount,approved_uid');

        if($branchId){
            $qP->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qD->whereHas('driver',function($q)use($warehouseId){
                $q->where('driver_warehouse_id',$warehouseId);
            });
        }

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
        $payments = $qP->get();
        $disbursements = $qD->get();
        $paymentDetails = DisbursementDetails::selectRaw('disbursement_id,method,amount,original_amount,currency_code')->get();
        $paymentDetails = PaymentDetail::selectRaw('payment_id,method,amount,original_amount,currency_code')->get();
        foreach($payments as $p){
            $p->driver_name = $p->driver?->username;
            $p->code = $p->driver?->code;
            $p->booked_by = $p->cashier?->username;
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
            $p->driver_name = $p->driver?->username;
            $p->code = $p->driver?->code;
            $p->booked_by = $p->cashier?->username;
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
        $obj = [
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
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $qP = Package::where('is_deleted',0)->where('outstanding',0)
        ->with(['merchant:id,username','status:id,name'])
        ->orderByDesc('id')
        ->selectRaw('
            status_id,qr_code,merchant_id,receiver_phone,receiver_name,receiver_address,cod,other_fee,
            delivery_fee,taxi_fee,driver_total,remarks,zone_code,zone_name,payer,driver_id,price,
            price_khr,driver_cod_usd,driver_cod_khr
        ');

        if($branchId){
            $qP->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qP->where('warehouse_id',$warehouseId);
        }

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
            $p->merchant_name = $p->merchant->username;
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
            $fees = (float)($p->delivery_fee + $p->other_fee);//- $p->taxi_fee;
            $driverCodUsd =(float)($p->driver_cod_usd ?? 0);
            $driverCodKhr = (float)($p->driver_cod_khr ?? 0);
            $taxiFee = (float)$p->taxi_fee;
            $driverCodUsd =(float)($p->driver_cod_usd ?? 0);
            $driverCodKhr = (float)($p->driver_cod_khr ?? 0);
            $taxiFee = (float)$p->taxi_fee;
            $driverTotal = PackageTrailServiceImpl::calculateCodAmtBothCurrencies(
                $driverCodUsd,
                $driverCodKhr,
                'driver',
                $p->payer,
                $p->status_id,
                $fees,
                $taxiFee
            );
            // $merchantTotal = PackageTrailServiceImpl::calculateCodAmtBothCurrencies(
            //     $driverCodUsd,
            //     $driverCodKhr,
            //     'merchant',
            //     $p->payer,
            //     $p->status_id,
            //     $fees,
            //     $taxiFee
            // );
            $p->driver_total = $driverTotal['amount_usd'];
            $p->driver_total_khr = $driverTotal['amount_khr'];
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
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $qD = User::from('users as d')->where('d.account_type','driver')
        ->join('disbursements as dis','dis.payee_id','d.id')
        ->where('dis.is_deleted',false)
        ->join('users as r','r.id','dis.receiptionist_uid')
        ->orderByDesc('dis.id')
        ->selectRaw('d.username as driver_name,d.code,dis.pickup_rate,dis.delivery_rate,dis.failed_with_fee_count,dis.delivered_package_count,dis.pickup_package_count,dis.payable_amount,dis.payment_datetime,dis.breakdown_notes,r.username as paid_by')
        ->where('dis.type','commission');
        if ($startDate && $endDate) {
            $startDateTime = Helper::dateYMD($startDate) . ' 00:00:00';
            $endDateTime = Helper::dateYMD($endDate) . ' 23:59:59'; // Corrected here
            $qD->whereBetween('dis.payment_datetime', [$startDateTime, $endDateTime]);
        }

        if($branchId){
            $qD->where('d.branch_id',$branchId);
        }
        if($warehouseId){
            $qD->where('d.driver_warehouse_id',$warehouseId);
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
        $branchId = $req->branch_id;
        $priceListId = $req->query('price_list_id');
        $warehouseId = $req->warehouse_id;
        $q = User::where('is_deleted',0)
        ->where('account_type','merchant')
        ->with([
            'bank_accounts:user_id,bank_name,bank_number,account_name',
            'merchantPriceList',
            'merchantPriceList.priceList.priceListName',
        ])
        ->selectRaw('username,business_type,phone,created_at,address,code,lock,id');
        if($branchId){
            $q->where('branch_id',$branchId);
        }
        if($warehouseId){
            $q->where('warehouse_id',$warehouseId);
        }

        if ($priceListId) {
            $priceListCallback = function ($q) use ($priceListId) {
                $q->whereHas('merchantPriceList', function($q2) use ($priceListId) {
                    $q2->where('price_list_id', $priceListId);
                });
            };
            $q->where($priceListCallback);
        }
        $merchants = $q->get();
        foreach($merchants as $m){
            $m->registered_date = Helper::dateDMY($m->created_at);
            $m->status_code = $m->lock ? 'Inactive' : 'Active';
            $m->merchantPriceList?->priceListName->name ?? null;
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
            unset($m->created_at,$m->bank_accounts,$m->merchantPriceList);
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
        $branchId = $req->branch_id;
        $warehouseId = $req->warehouse_id;
        $merchantInfo = User::where('is_deleted',0)->where('account_type','merchant')
        ->selectRaw('id,username as merchant_name,phone as merchant_phone,address')->find($merchantId);
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
        $xRate = ExchangeRate::whereRaw('DATE(x_date) >= ? AND DATE(x_date) <= ?', [Helper::dateYMD($startDate), Helper::dateYMD($endDate)])
            ->orderBy('x_date', 'desc') // Ensures the latest rate in the range is prioritized
            ->take(1)->value('buy_rate');

        if (!$xRate) {
            $xRate = GeneralSettingService::getLatestXRate()->buy_rate;
        }

        $merchantInfo->exchange_rate = $xRate;
        $pmtCase = ',CASE WHEN p.merchant_disbursement_id IS NOT NULL THEN dis.is_settled WHEN p.merchant_payment_id IS NOT NULL THEN pmt.is_settled ELSE FALSE END AS approved';
        $qP = Package::from('packages as p')->where('p.is_deleted',false)
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
        ->selectRaw('p.order_id,p.merchant_total,p.merchant_id,p.remarks,p.delivery_remarks,p.status_id,p.id,p.qr_code,p.delivered_datetime,p.failed_datetime,p.delivery_remarks,p.remarks,p.taxi_fee,p.other_fee,p.delivery_fee,p.cod,p.price,p.payer,
        p.returned_datetime,p.arrive_warehouse_datetime,p.assign_driver_datetime,p.receiver_phone,p.receiver_name,p.receiver_address,p.zone_name,p.delivery_remarks,p.merchant_disbursement_id,p.merchant_payment_id'.$pmtCase);
        if($branchId){
            $qP->where('p.branch_id',$branchId);
        }
        if($warehouseId){
            $qP->where('p.warehouse_id',$warehouseId);
        }

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
        )
        ->orderByRaw("
            GREATEST(
                COALESCE(DATE(p.failed_datetime), '1970-01-01'),
                COALESCE(DATE(p.delivered_datetime), '1970-01-01')
            ) DESC
        ");

        // ->orderByRaw('DATE(p.failed_datetime) DESC,DATE(p.delivered_datetime) DESC');
        $clonePkg = clone $qP;
        $packages = $qP->get();
        // return $packages;
        // foreach($packages as $p){

        // }
        // return $packages;
        $headerSummary = $this->getMerchantSummaryHeader($clonePkg,$merchantId,$startDate,$endDate,$branchId,$warehouseId);
        $summary = $headerSummary->package_info;
        $groupedPackages = collect($packages)->map(function ($item) use (&$grand)  {
            $item->arrive_warehouse_datetime = Helper::formatCustomDateTime($item->arrive_warehouse_datetime);
            $finishDate = $item->failed_datetime;
            if($item->status_id == 9) {
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
        ->map(function ($group, $date) use ($isKm,&$grand,&$grandTotal){
            $group->each(function ($item) use (&$grand,$isKm,&$totalDeliveryFee) {
                $item->finished_date = Helper::dateDMY($item->groupDate);//($item->failed_datetime  && $item->status_id != 9) ? Helper::dateDMY($item->failed_datetime): Helper::dateDMY($item->delivered_datetime);
                $finished_time = Helper::dateDMY($item->groupDate);//$item->failed_datetime ? Helper::formatCustomDateTime($item->failed_datetime,'h:i:s A'):Helper::formatCustomDateTime($item->delivered_datetime,'h:i:s A');
                unset($item->groupDate);
                $item->finished_time = $finished_time;
                $isCal = in_array($item->status_id,[9,19]);
                $item->price = $item->cod ? $item->price:0;
                $total = $item->cod && $item->status_id == 9 ? $item->price : 0;
                if($item->payer == 'sender') {
                    $item->delivery_fee = $isCal ? ($item->delivery_fee + $item->other_fee) : 0;
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
            $grandTotal += $grand;
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
            'grand_total' => $grandTotal,
            'summary' => $summary,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getMerchantSummaryReportV2(Request $req){
        $user = UserService::getAuthUser();
        // $lang = $req->lang;
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        $merchantId = $req->merchant_id;
        // $statusIds = $req->statusIds;
        // $branchId = $req->branch_id;
        // $warehouseId = $req->warehouse_id;
        $search = $req->search;

        $merchantInfo = User::where('account_type','merchant')
        ->select(['id','phone','username','code','address'])
        ->where('is_deleted',false)
        ->find($merchantId);
        if(!$merchantInfo){
            return ApiResponse::NotFound(__('messages.not_found',[
                'info' => 'Merchant',
                'khInfo' => 'Merchant'
            ]));
        }
        $qP = Package::where('is_deleted',false)
        ->where('outstanding',0)
        ->whereIn('status_id',[5,6,9,10,19,11,23])
        ->where('merchant_id',$merchantId);
        if($search){    
            
        }else {
            if($startDate && $endDate){
                $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
                $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
                $qP->where(function ($q) use ($startDatetime,$endDatetime){
                    $q->whereNotIn('status_id', [9,23])
                    // ✔ Only show status 9 if delivered in range
                    ->orWhere(function ($s) use ($startDatetime, $endDatetime) {
                        $s->where('status_id', 9)
                            ->whereBetween('delivered_datetime', [$startDatetime, $endDatetime]);
                    })
                    ->orWhere(function ($s) use ($startDatetime, $endDatetime) {
                        $s->where('status_id', 23)
                            ->whereBetween('returned_datetime', [$startDatetime, $endDatetime]);
                    });
                    // $q->whereRaw(
                    //     "(status_id = 5 AND arrive_warehouse_datetime BETWEEN ? AND ?)
                    //     OR (status_id = 6 AND assign_driver_datetime BETWEEN ? AND ?)
                    //     OR (status_id = 10 AND failed_datetime BETWEEN ? AND ?)
                    //     OR (status_id = 19 AND failed_datetime BETWEEN ? AND ?)
                    //     OR (status_id = 9 AND delivered_datetime BETWEEN ? AND ?)
                    //     OR (status_id = 11 AND assigned_return_at BETWEEN ? AND ?)
                    //     OR (status_id = 23 AND returned_datetime BETWEEN ? AND ?)",
                    //     [
                    //         $startDatetime, $endDatetime, 
                    //         $startDatetime, $endDatetime, 
                    //         $startDatetime, $endDatetime, 
                    //         $startDatetime, $endDatetime, 
                    //         $startDatetime, $endDatetime, 
                    //         $startDatetime, $endDatetime,
                    //         $startDatetime, $endDatetime
                    //     ]
                    // );
                });
            }
        }

        [$orderByCase,$bindings] = $this->getMerchantSummaryReportV2packageOrder();
        $packages = $qP->select([
            'id',
            'qr_code',
            'price as price_usd',
            'price_khr',
            'zone_name',
            'zone_code',
            'remarks',
            'delivery_remarks',
            'arrive_warehouse_datetime as arrived_at',
            'driver_cod_usd',
            'driver_cod_khr',
            'delivery_fee',
            'other_fee',
            'taxi_fee',
            'status_id',
            'payer',
            'receiver_address',
            'receiver_phone',
            'delivery_remarks',
            DB::raw("
                CASE
                    WHEN status_id = 9  THEN delivered_datetime
                    WHEN status_id = 5  THEN arrive_warehouse_datetime
                    WHEN status_id = 6  THEN assign_driver_datetime
                    WHEN status_id = 11 THEN assigned_return_at
                    WHEN status_id = 23 THEN returned_datetime
                    WHEN status_id IN (10,19) THEN failed_datetime
                END as action_date
            ")
        ])
        ->orderByRaw($orderByCase, $bindings)
        ->get()->each(function($q){
            $q->status = TrackingStatus::tryFrom($q->status_id)->label();
            if(in_array($q->status_id,[10,19])){
                $q->remarks = $q->delivery_remarks;
            }
        });
        
        $totalCount = 0;
        $unique = null;
        $groupedPackages = $packages->groupBy('status')->map(function ($items, $group) use (&$unique,&$totalCount) {
            $totalCount += $items->count();
            $driverCodUsd = $items->sum('driver_cod_usd');
            $driverCodKhr = $items->sum('driver_cod_khr');
            $toSettleUsd = $driverCodUsd;
            $toSettleKhr = $driverCodKhr;
            $taxiFee = $items->where('payer','sender')->sum('taxi_fee');
            $deliveryFee = $items->where('payer','sender')->sum('delivery_fee');
            $otherFee = $items->where('payer','sender')->sum('other_fee');
            $deductFees = $taxiFee + $deliveryFee + $otherFee;
            Helper::deductAmountBase($toSettleUsd,$toSettleKhr,$deductFees);
            $statusId = $items->first()->status_id;
            $count=$items->count();
            if ($statusId == 9) {
                $unique .= "{$count}-{$statusId}";
            }
            return [
                'group' => $group,
                'status_id' => $statusId,
                'count' => $count,
                'service_fees' => [
                    'taxi_fee' => $taxiFee,
                    'delivery_fee' => $deliveryFee,
                    'other_fee' => $otherFee
                ],
                'totalCharge' => Helper::getNumber($deductFees,2,true),
                'totalCod' => [
                    'usd' => Helper::getNumber($items->sum('price_usd'),2,true),
                    'khr' => Helper::getNumber($items->sum('price_khr'),0,true)
                ],
                'totalReceived' => [
                    'usd' => Helper::getNumber($driverCodUsd,2,true),
                    'khr' => Helper::getNumber($driverCodKhr,0,true)
                ],
                'to_return' => [
                    'usd' => Helper::getNumber($toSettleUsd,2,true),
                    'khr' => Helper::getNumber($toSettleKhr,0,true)
                ],
                'items' => $items->values(),      // reset keys
            ];
        })->values();

        $obj =(object)[
            'title' => 'Merchant Summary',
            // 'status' => 'All Driver',
            'date' => $startDate.' to '.$endDate,
            'merchant' => $merchantInfo,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'total_packages' => $totalCount,
            // 'grand' => [
            //     'total_cod' => $totalCod,
            //     'total_received' => $total,
            //     'service_fees' => $service_fees
            // ],
            'unique' => $unique,
            'list' => $groupedPackages
        ];
        return ApiResponse::JsonResult($obj);
    }

    private function getMerchantSummaryReportV2packageOrder():array{
        $statusOrder = [9, 10, 5, 6, 11, 23, 19]; // your custom priority
        $orderByCase = "CASE ";
        $bindings = [];

        foreach ($statusOrder as $index => $statusId) {
            $orderByCase .= "WHEN status_id = ? THEN ? ";
            $bindings[] = $statusId; // status_id
            $bindings[] = $index;    // sort index
        }
        $orderByCase .= "ELSE ? END"; // fallback
        $bindings[] = 999; // fallback index
        return [$orderByCase,$bindings];
    }

    private function getMerchantSummaryHeader($clonePkg,$merchantId,$startDate,$endDate,$branchId,$warehouseId){
        $startDate = Helper::dateYMD($startDate).' 00:00:00';
        $endDate = Helper::dateYMD($endDate).' 23:59:59';
        // $lastOrder = Package::from('packages as p')->where('p.is_deleted',0)
        // ->whereRaw(
        //     "(p.status_id = 5 AND p.arrive_warehouse_datetime BETWEEN ? AND ?)
        //     OR (p.status_id = 6 AND p.assign_driver_datetime BETWEEN ? AND ?)
        //     OR (p.status_id = 10 AND p.failed_datetime BETWEEN ? AND ?)",
        //     [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]
        // )
        // ->joinSub(
        // Order::select('id as order_id','code')
        //     ->where('merchant_id',$merchantId)
        //     ->where('status_id',5)
        //     ->orderByDesc('id') // Assuming 'id' defines the latest order
        //     ->limit(1),
        // 'o',
        // 'o.order_id',
        // '=',
        // 'p.order_id'
        // )
        // ->whereIn('p.status_id',[5,6,10])
        // ->selectRaw('p.id as package_id,p.order_id,p.qr_code,p.merchant_total,p.cod,p.price')
        // ->get();
        $qLastOrder = Package::where('is_deleted',0)
        ->where('arrive_warehouse_datetime','>=',date('Y-m-d').' 00:00:00')
        ->where('merchant_id',$merchantId)
        ->where('outstanding',0)
        ->selectRaw('id as package_id,status_id,order_id,qr_code,merchant_total,cod,price');
        if($branchId){
            $qLastOrder->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qLastOrder->where('warehouse_id',$warehouseId);
        }
        $clLatest = clone $qLastOrder;
        $lastOrder = $qLastOrder->get();

        // \Log::error($lastOrder);
        $packages = $clonePkg->whereNotIn('p.id',$clLatest->pluck('package_id')->toArray())->get();

        $totalCount = 0;
        $pkgInfo = [
            5 => ['title' => 'ចំនួនកញ្ចប់ដែលនៅសល់ ', 'count' => 0,'total' => 0],
            "5.1" => ['title' => 'ចំនួនកញ្ចប់​ចូលថ្មី ', 'count' => 0, 'total' => 0],
            // "5.2" => ['title' => 'ចំនួនកញ្ចប់សរុប ',"count" => 0, 'total' => 0],
            9 => ['title' => 'ជោគជ័យ ', 'count' => 0, 'total' => 0],
            6 => ['title' => 'កំពុងដឹក ', 'count' => 0, 'total' => 0],
            10 => ['title' => 'បរាជ័យ ', 'count' => 0, 'total' => 0],
            19 => ['title' => 'បរាជ័យគិតសេវា ', 'count' => 0, 'total' => 0],
            11 => ['title' => 'ត្រឡប់ទៅហាងវិញ ', 'count' => 0, 'total' => 0],
            // 'all' => ['title' => 'ត្រឡប់ទៅហាងវិញ ', 'count' => 0, 'total' => 0],
        ];
        $qFpkg = Package::where('is_deleted',false)
        ->where('merchant_id',$merchantId)
        ->whereIn('status_id',[10,5])
        ->whereNotIn('id',$clLatest->pluck('package_id'))
        ->select('id','qr_code','status_id')
        ->whereNotIn('id',$packages->pluck('id'));
        if($branchId){
            $qFpkg->where('branch_id',$branchId);
        }
        if($warehouseId){
            $qFpkg->where('warehouse_id',$warehouseId);
        }
        $failedPkgs = $qFpkg->get();
        foreach($failedPkgs as $p){
            if (!isset($pkgInfo[5])) {
                $pkgInfo[5] = ['count' => 0, 'total' => 0];
            }
            $pkgInfo[5]['count'] += 1;
            $pkgInfo[5]['total'] += $p->cod ? $p->price : 0;
        }

        $statuses = [6, 9, 10, 11, 19];
        foreach($lastOrder as $p){
            $pkgInfo['5.1']['count'] += 1;
            $pkgInfo['5.1']['total'] += $p->cod ? $p->price:0;//- $p->merchant_total;
            // $pkgInfo['5.2']['count'] = $pkgInfo['5.1']['count'] + $pkgInfo[5]['count'];
            // $pkgInfo['5.2']['total'] = $pkgInfo['5.1']['total'] + $pkgInfo[5]['total'];
            $totalCount += 1;
            if (in_array($p->status_id, $statuses)) {
                if (!isset($pkgInfo[$p->status_id])) {
                    $pkgInfo[$p->status_id] = ['count' => 0, 'total' => 0];
                }

                $pkgInfo[$p->status_id]['count'] += 1;
                $pkgInfo[$p->status_id]['total'] += $p->cod ? $p->price : 0;
                if($p->status_id == 10){
                    if (!isset($pkgInfo[5])) {
                        $pkgInfo[5] = ['count' => 0, 'total' => 0];
                    }
                    $pkgInfo[5]['count'] += 1;
                    $pkgInfo[5]['total'] += $p->cod ? $p->price : 0;
                }
            }
            if ($p->status_id == 5) {
                if (!isset($pkgInfo[5])) {
                    $pkgInfo[5] = ['count' => 0, 'total' => 0];
                }
                $pkgInfo[5]['count'] += 1;
                $pkgInfo[5]['total'] += $p->cod ? $p->price : 0;
            }
        }
        // return $packages;
        foreach ($packages as $p) {
            $totalCount += 1;
            $statusId = $p->status_id;
            if(isset($pkgInfo[$statusId]) && !in_array($statusId,[5,10])){
                $pkgInfo[$statusId]['count'] += 1;
                $pkgInfo[$statusId]['total'] += $p->cod ? $p->price:0;//- $p->merchant_total;
            }
            if(in_array($statusId,[5,10])){
                // if($statusId == 5 || $statusId == 10){
                    $pkgInfo[5]['count'] += 1;
                    $pkgInfo[5]['total'] += $p->cod ? $p->price : 0;
                // }//- $p->merchant_total;
                if(isset($pkgInfo['5.2'])){
                    $pkgInfo['5.2']['count'] = $pkgInfo['5.1']['count'] + $pkgInfo[5]['count'];
                    $pkgInfo['5.2']['total'] = $pkgInfo['5.1']['total'] + $pkgInfo[5]['total'];
                }
            }
        }

        if(isset($pkgInfo[9])) $pkgInfo[9]['total'] = Helper::getNumber($pkgInfo[9]['total'],2);
        if(isset($pkgInfo[11])) $pkgInfo[11]['total'] = Helper::getNumber($pkgInfo[11]['total'],2);
        if(isset($pkgInfo[19])) $pkgInfo[19]['total'] = Helper::getNumber($pkgInfo[19]['total'],2);
        if(isset($pkgInfo[5])) $pkgInfo[5]['total'] = Helper::getNumber($pkgInfo[5]['total'],2);
        if(isset($pkgInfo[6])) $pkgInfo[6]['total'] = Helper::getNumber($pkgInfo[6]['total'],2);
        if(isset($pkgInfo[10])) $pkgInfo[10]['total'] = Helper::getNumber($pkgInfo[10]['total'],2);
        if(isset($pkgInfo['5.1'])) $pkgInfo['5.1']['total'] = Helper::getNumber($pkgInfo['5.1']['total'],2);
        if(isset($pkgInfo['5.2'])) $pkgInfo['5.2']['total'] = Helper::getNumber($pkgInfo['5.2']['total'],2);

        // \Log::error(array_values($pkgInfo));
        return (object)[
            'package_info' => array_values($pkgInfo),
            'total_count' => $totalCount
        ];
    }

    public function getMerchantPaymentReport(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $allPayments = [];
        $transactionType = $req->transaction_type;
        $branchId = $req->branch_id;
        $merchantIds = collect();
        $totalPkgs = 0;
        $totalAmtUsd = 0;
        $totalAmtKhr = 0;
        // $warehouseId = $req->warehouse_id;
        $bankAccounts = UserBank::orderByDesc('is_primary')
        ->where('is_deleted',false)
        ->select('id','bank_name','currency','bank_number','account_name','user_id')
        ->get()
        ->groupBy('user_id');
        $startDateTime = $startDate && $endDate ? Helper::dateYMD($startDate).' 00:00:00' : null;
        $endDateTime = $startDate && $endDate ? Helper::dateYMD($endDate).' 23:59:59' : null;

        // Helper function to apply date filter
        $applyDateFilter = function ($query) use ($startDateTime, $endDateTime,$branchId) {
            if ($startDateTime && $endDateTime) {
                $query->whereBetween('payment_datetime', [$startDateTime, $endDateTime]);
            }
            // if($branchId){
            //     $query->where('');
            // }
            return $query;
        };

        if ($transactionType === TransactionType::TRANSFER_IN->value || $transactionType === null) {
            $pQ = Payment::where('payments.is_deleted', 0)
                ->where('payments.is_settled', 1)
                ->where('payer_type', 'merchant')
                ->with(['merchant'])
                ->join('users as b', 'payments.settled_uid', 'b.id')
                ->select([
                    'payments.id','payments.package_count','payments.payable_amount','payments.breakdown_notes',
                    'b.username as booked_user','payments.remarks','payments.payment_datetime','payments.payer_id',
                    'payments.received_amount_usd','payments.received_amount_khr'
                ]);

            $applyDateFilter($pQ);
            $payments = $pQ->get();

            foreach ($payments as $p) {
                $merchantIds->push($p->payer_id);
                $p->bank_accounts = $this->userBankAccount($bankAccounts,$p->payer_id);
                $p->payment_date = Helper::dateDMY($p->payment_datetime);
                $p->trx_type = 'Receive';
                $p->trx_type_code = 'receive';
                $p->merchant_name = $p->merchant?->username;
                $totalAmtUsd += $p->received_amount_usd;
                $totalAmtKhr += $p->received_amount_khr;
                $totalPkgs += $p->package_count;
                unset($p->merchant);
                $allPayments[] = $p;
            }
        }

        if ($transactionType === TransactionType::TRNASFER_OUT->value || $transactionType === null) {
            $dQ = Disbursement::where('disbursements.is_deleted', 0)
                ->where('disbursements.is_settled', 1)
                ->where('payee_type', 'merchant')
                ->with(['merchant'])
                ->join('users as b', 'disbursements.settled_uid', 'b.id')
                ->select([
                    'disbursements.id','disbursements.package_count','disbursements.payable_amount',
                    'disbursements.breakdown_notes','b.username as booked_user','disbursements.remarks',
                    'disbursements.payment_datetime','payee_id','disbursements.received_amount_khr',
                    'disbursements.received_amount_usd'
                ]);

            $applyDateFilter($dQ);
            $disbursements = $dQ->get();

            foreach ($disbursements as $p) {
                $merchantIds->push($p->payee_id);
                $p->bank_accounts = $this->userBankAccount($bankAccounts,$p->payee_id);
                $p->payment_date = Helper::dateDMY($p->payment_datetime);
                $p->merchant_name = $p->merchant?->username;
                $p->trx_type = 'Disbursement';
                $p->trx_type_code = 'disbursement';
                // $totalAmt -= $p->payable_amount;
                $totalAmtUsd -= $p->received_amount_usd;
                $totalAmtKhr -= $p->received_amount_khr;
                $totalPkgs += $p->package_count;
                unset($p->merchant);
                $allPayments[] = $p;
            }
        }

        $merchantCount = $merchantIds->unique()->count();

        $obj =(object)[
            'title' => 'Merchant Payment',
            'status' => 'All Merchant',
            'total_merchant' => $merchantCount,
            'date' => $startDate.' to '.$endDate,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'grand' => [
                'total' => Helper::getNumber($totalAmtUsd,2,true),
                'total_khr' => Helper::getNumber($totalAmtKhr,0,true)
            ],
            'list' => $allPayments
        ];
        return ApiResponse::JsonResult($obj);
    }

    private function userBankAccount($bankAccountsGrouped, $userId) {
        if (!isset($bankAccountsGrouped[$userId])) {
            return [];//null;
        }
        // Get first (primary or just first)
        // $bank = $bankAccountsGrouped[$userId]->first();

        return $bankAccountsGrouped[$userId];//GeneralSettingService::concatBankInfo($bank->bank_name, $bank->bank_number, $bank->account_name);
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
        $sumAmount = 'SUM(CASE WHEN packages.payer = \'sender\' THEN packages.delivery_fee + packages.other_fee + packages.taxi_fee ELSE packages.taxi_fee END) AS amount';
        $pQ = Package::where('packages.is_deleted', 0)
        ->whereIn('packages.status_id',[9,19])
        // ->whereNull('packages.merchant_disbursement_id')
        // ->whereNull('packages.merchant_payment_id')
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'packages.id')
                ->where('pp.payer_type', 'merchant')
                ->where('pp.is_deleted', false);
        })->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'packages.id')
                ->where('dp.payee_type', 'merchant')
                ->where('dp.type','payment')
                ->where('dp.is_deleted', false);
        })
        ->where('packages.cod', true) // Filter only COD packages
        ->whereRaw('packages.price - (packages.delivery_fee + packages.other_fee + packages.taxi_fee) < 0')
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
            m.username as merchant_name,
            COUNT(packages.id) as total_package,
            SUM(packages.taxi_fee) as taxi_fee,
            SUM(CASE WHEN packages.payer = \'sender\' THEN packages.delivery_fee + packages.other_fee ELSE 0 END) AS total_delivery_fee,
            ' . $sumAmount
        )
        ->groupByRaw('m.code, packages.merchant_id, m.username, uba.bank_info') ;
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

    public function getMerchantPayable(Request $req)
    {
        $authUser = Auth::user();
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');
        $qP = Package::query()
            ->select([
                'p.merchant_id',
                'm.username as merchant_name',
                DB::raw("COUNT(*) as package_count"),
                DB::raw("
                    SUM(
                        CASE 
                            WHEN p.payer = 'sender' 
                            THEN (COALESCE(p.delivery_fee, 0) + COALESCE(p.extra_charge, 0))
                            ELSE 0
                        END
                    ) as fees
                "),
                DB::raw("SUM(p.taxi_fee) as taxi_fee"),
                DB::raw("SUM(p.driver_cod_usd) as cod_amount"),
                DB::raw("SUM(p.driver_cod_khr) as cod_amount_khr"),
                DB::raw("TO_CHAR(COALESCE(p.delivered_datetime, p.failed_datetime), 'DD/MM/YYYY') as finished_date")
            ])
            ->from('packages as p')
            ->join('users as m', 'm.id', '=', 'p.merchant_id')
            ->where('p.is_deleted', false)
            ->whereIn('p.status_id', [9, 19])
            ->withoutMerchantPayment()
            ->groupBy('p.merchant_id','m.username', DB::raw("TO_CHAR(COALESCE(p.delivered_datetime, p.failed_datetime), 'DD/MM/YYYY')"))
            ->orderByRaw("MAX(COALESCE(p.delivered_datetime, p.failed_datetime)) DESC");
            if($startDate && $endDate){
                $qP->where(function ($query) use ($startDate,$endDate) {
                    $startDateTime = Helper::dateYMD($startDate).' 00:00:00';
                    $endDateTime = Helper::dateYMD($endDate).' 00:00:00';
                    $query->where(function ($q) use ($startDateTime,$endDateTime) {
                        $q->where('p.status_id', 9)
                        ->whereBetween('p.delivered_datetime',[$startDateTime,$endDateTime]);
                    })->orWhere(function ($q) use ($startDateTime,$endDateTime) {
                        $q->where('p.status_id', 19)
                        ->whereBetween('p.failed_datetime',[$startDateTime,$endDateTime]);
                    });
                });
            }
            $totalKhr = 0;
            $totalUsd = 0;
            $payableList = $qP->get()
            ->each(function($q) use(&$totalKhr,&$totalUsd){
                $q->payment_status = 'Unpaid';
                $codUsd = $q->cod_amount;
                $codKhr = $q->cod_amount_khr;
                Helper::deductAmountBase($codUsd,$codKhr,$q->fees + $q->taxi_fee);
                $totalKhr += $codKhr;
                $totalUsd += $codUsd;
                $q->amount = Helper::amountStdFmt($codUsd, 'USD');
                $q->amount_khr = Helper::amountStdFmt($codKhr,'KHR');
                $q->cod_amount = Helper::amountStdFmt($q->cod_amount, 'USD');
                $q->cod_amount_khr = Helper::amountStdFmt($q->cod_amount_khr,'KHR');
            });

        $obj =(object)[
            'title' => 'Merchant Payable',
            'status' => '',
            'date' => Helper::dateDMY($startDate).' to '.Helper::dateDMY($endDate),
            'company_profile' => CompanyProfileService::profileInfo($authUser),
            'total_amount' => Helper::amountStdFmt($totalUsd),
            'total_amount_khr' => Helper::amountStdFmt($totalKhr, 'KHR'),
            'list' => $payableList
        ];
        return ApiResponse::JsonResult($obj);
        // return [
            // 'total_amount' => Helper::amountStdFmt($totalUsd),
            // 'total_amount_khr' => Helper::amountStdFmt($totalKhr, 'KHR'),
        //     'list' => $payableList
        // ];
    }
    
    public function getMerchantDailyPackage(){

    }

    //** END MERCHANT REPORT */
    public function driverDeliverySummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'drivers' => GeneralSettingService::optionsDriver($user),
            'branches' => GeneralSettingService::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function merchantSummaryReportOption(){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            // 'merchants' => GeneralSettingService::optionsMerchant($user)
            'branches' => GeneralSettingService::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getMerchatnPaymentReportOption(){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'transaction_types' => GeneralSettingService::optionsTransactionType(),
            'branches' => GeneralSettingService::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formOptionUser (){
        $user = UserService::getAuthUser();
        $obj = [
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
            'statuses' => GeneralSettingService::optionsUserStatus(),
            'branches' => GeneralSettingService::optionsBranch(),
            'prict_lists' => GeneralSettingService::optionsPriceList($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function optionsWarehouse(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsWarehouse($user));
    }
}
