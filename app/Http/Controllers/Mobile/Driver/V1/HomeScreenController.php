<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\DTO\Mobile\DeliveryTripsPackagesDTO;
use App\DTO\Mobile\HomeBalanceCardDTO;
use App\DTO\Mobile\HomeReturnPackageDTO;
use App\Enums\ImageDirectory;
use App\Enums\TrackingStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\V1\GeneralSettingController;
use App\Jobs\SendNotificationJob;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\DriverCommission;
use App\Models\EmergencyContact;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Models\ScoringReward;
use App\Models\UserScoringReward;
use App\Services\AppSetting;
use App\Services\CloudMessagingService;
use App\Services\GeneralSettingService;
use App\Services\GeoResolverService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use App\Services\UserShopService;
use Illuminate\Support\Facades\DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
// use WebSocket\Client;

class HomeScreenController extends Controller
{
    //
    private object $user;
    private string $dateFmt;
    public function __construct(
        private PickupCenterService $pickupCenterService
    ){
        $this->dateFmt = 'd/m/Y';
        $this->user = UserService::getAuthUser('driver');
    }


    // public function  getAssignReturnPackages(){
    //     $data = (object)[
    //         'unpaid_amount' => 0
    //     ];
    //     return ApiResponse::JsonResult(HomePaymentDTO::fromModel($data));
    // }
    public function getAvailableOrders(Request $req){
        $user = UserService::getAuthUser('driver');
        $query = Order::query()->where('is_deleted',0)
        ->with(['merchant','warehouse'])
        ->where('status_id',1)
        ->where('company_id',$user->company_id)
        ->orderByDesc('id')
        ->selectRaw('id,loc_lat,loc_lng,order_datetime,merchant_id,warehouse_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type');
        $callback = function ($order){
            $order->delivery_type = trans($order->delivery_type);
            $order->merchant_name = $order->merchant->username;
            $order->merchant_code = $order->merchant->code;
            $order->merchant_phone = $order->merchant->phone;
            $order->warehouse_address = $order->warehouse->address;
            $orderDatetime = $order->order_datetime;
            $order->order_date = Helper::formatCustomDateTime($orderDatetime,$this->dateFmt);
            $order->order_time = Helper::formatCustomDateTime($orderDatetime,'h:i A');
            unset($order->merchant,$order->warehouse);
            return $order;
        };
        return ApiResponse::PaginationV1($query,$req,'',[],1000,$callback);
    }

    public function getAcceptedPickup(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status','warehouse'])
        // ->whereIn('status_id',[2,3,4]) //** order which not mark as arrive warehouse */
        ->where('status_id',3)  //* only accepted pick up
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        // ->orderByRaw('status_id = ? desc',[3])
        ->orderByDesc('id')
        ->selectRaw('id,warehouse_id,driver_id,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type,loc_lat,loc_lng,product_type,pickup_notes as remarks');
        $callback = function($order){
            $order->warehouse_address = $order->warehouse->address;
            $order->status_code = $order->tracking_status->name;
            $order->merchant_name = $order->merchant->username;
            $order->merchant_phone = $order->merchant->phone;
            $orderDatetime = $order->order_datetime;
            $order->order_date = Helper::formatCustomDateTime($orderDatetime,$this->dateFmt);
            $order->order_time = Helper::formatCustomDateTime($orderDatetime,'h:i A');
            $order->telegram_url = Helper::generateTelegramLink($order->merchant_phone)['url'];
            // $order->pickup_notes = 'Hello World';
            // $order->remarks = "Neak order write 2 jur, yg ka pea kom oy overflow.fsdfkdsdfksdfgsdfkgkfsdlfgjsldfk";
            // $latLng = Helper::getLatLongFromGoogleMapsUrl($order->pickup_address_google_map);
            $order->latitude = $order->loc_lat ;//? $order->loc_lat : 11.552692;//;
            $order->longitude = $order->loc_lng ;// ? $order->loc_lng : 104.901413;//$order->loc_lng;
            unset($order->merchant,$order->tracking_status,$order->warehouse);
            return $order;
        };
        // ->get();
        // foreach($orders as $order){
        //     $order->warehouse_address = $order->warehouse->address;
        //     $order->status_code = $order->tracking_status->name;
        //     $order->merchant_name = $order->merchant->username;
        //     $order->merchant_phone = $order->merchant->phone;
        //     // $latLng = Helper::getLatLongFromGoogleMapsUrl($order->pickup_address_google_map);
        //     $order->latitude = $order->loc_lat ;//? $order->loc_lat : 11.552692;//;
        //     $order->longitude = $order->loc_lng ;// ? $order->loc_lng : 104.901413;//$order->loc_lng;
        //     unset($order->merchant,$order->tracking_status,$order->warehouse);
        // }
        return ApiResponse::PaginationV1($orders,$req,'',[],1000,$callback);
    }

    public function getDriverBalance(Request $req){
        $user = UserService::getAuthUser('driver');
        $lang = $req->lang;

        $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
        ->get();

        $commissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$user->id);
        // return $commissionInfo;

        $normalStartDatetime = $commissionInfo->normal_delivery_commission_start_date
            ? Helper::dateYMD($commissionInfo->normal_delivery_commission_start_date) . ' 00:00:00'
            : null;

        $fastStartDatetime = $commissionInfo->fast_delivery_commission_start_date
            ? Helper::dateYMD($commissionInfo->fast_delivery_commission_start_date) . ' 00:00:00'
            : null;

        $qP = Package::where('is_deleted', 0)
            ->where('driver_id', $user->id)
            ->whereIn('status_id', [6, 9, 19]);
        $clQp = clone $qP;
        $qP->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as pp')
                ->whereColumn('pp.package_id', 'packages.id')
                ->where('pp.type','commission')
                ->where('pp.payee_type', 'driver')
                ->where('pp.is_deleted', false);
        });

        // Log::info($normalStartDatetime);
        if ($normalStartDatetime || $fastStartDatetime) {
            $qP->where(function ($q) use ($normalStartDatetime, $fastStartDatetime) {
                if ($normalStartDatetime) {
                    $q->orWhere(function ($q) use ($normalStartDatetime) {
                        $q->where('delivery_type', 'normal')
                        ->where(function ($q) use ($normalStartDatetime) {
                            $q->where('delivered_datetime', '>=', $normalStartDatetime)
                                ->orWhere('failed_datetime', '>=', $normalStartDatetime);
                                // ->orWhere('assign_driver_datetime', '>=', $normalStartDatetime);
                        });
                    });
                }
                if ($fastStartDatetime) {
                    $q->orWhere(function ($q) use ($fastStartDatetime) {
                        $q->where('delivery_type', 'fast')
                        ->where(function ($q) use ($fastStartDatetime) {
                            $q->where('delivered_datetime', '>=', $fastStartDatetime)
                                ->orWhere('failed_datetime', '>=', $fastStartDatetime);
                                // ->orWhere('assign_driver_datetime', '>=', $fastStartDatetime);
                        });
                    });
                }
            });
        }

        $counts = $qP->selectRaw("
            COUNT(CASE WHEN status_id = 9 AND delivery_type = 'normal' THEN 1 END) AS delivered_normal_pkg,
            COUNT(CASE WHEN status_id = 9 AND delivery_type = 'fast' THEN 1 END) AS delivered_fast_pkg,

            COUNT(CASE WHEN status_id = 19 AND delivery_type = 'normal' THEN 1 END) AS failed_with_fee_normal_pkg,
            COUNT(CASE WHEN status_id = 19 AND delivery_type = 'fast' THEN 1 END) AS failed_with_fee_fast_pkg

        ")->first() ?? (object)[
            'delivered_normal_pkg' => 0, 'delivered_fast_pkg' => 0,
            'failed_with_fee_normal_pkg' => 0, 'failed_with_fee_fast_pkg' => 0,
            // 'delivery_normal_pkg' => 0, 'delivery_fast_pkg' => 0,
        ];
        $collectedCod = $clQp->withoutDriverPayment()
        ->selectRaw("
            SUM(CASE WHEN status_id IN (9, 19) THEN driver_cod_usd ELSE 0 END) AS driverCodUsd,
            SUM(CASE WHEN status_id IN (9, 19) THEN driver_cod_khr ELSE 0 END) AS driverCodKhr,
            COUNT(CASE WHEN status_id = 6 AND delivery_type = 'normal' THEN 1 END) AS delivery_normal_pkg,
            COUNT(CASE WHEN status_id = 6 AND delivery_type = 'fast' THEN 1 END) AS delivery_fast_pkg
        ")
        ->first();

        $pickedUpCount = DB::table('orders')
            ->join('packages', function($join) {
                $join->on('packages.order_id', '=', 'orders.id')
                    ->where('packages.status_id', 9)  // Delivered packages
                    ->where('packages.is_deleted', 0);
            })
            ->where('orders.is_deleted', 0)
            ->whereNull('orders.driver_commission_id')
            ->where('orders.status_id', 5)
            ->where('orders.driver_id', $user->id)
            // ->whereBetween('orders.order_datetime', ['2025-07-01 00:00:00', '2025-09-13 23:59:59'])
            ->count('packages.id');

        //** Type: Normal */
        $normalDeliveredPkg = $counts->delivered_normal_pkg;
        // Log::info($normalDeliveredPkg);
        $allDeliveryPkg = $collectedCod->delivery_normal_pkg + $collectedCod->delivery_fast_pkg;
        $normalFailedWithFeePkg = $counts->failed_with_fee_normal_pkg;

        //** Type: Fast */
        $fastDeliveredPkg = $counts->delivered_fast_pkg;
        $fastFailedWithFeePkg = $counts->failed_with_fee_fast_pkg;

        //** Normal Commission */
        $normalDeliveryComm = $normalDeliveredPkg * $commissionInfo->normal_delivery_commission + $pickedUpCount * $commissionInfo->normal_pickup_commission;
        $normalPickupComm = $collectedCod->delivery_normal_pkg * $commissionInfo->normal_pickup_commission;
        // $normalFailedWithFeeComm = $normalFailedWithFeePkg * $commissionInfo->normal_delivery_commission;

        // //** Fast Commission */

        // $fastDeliveryComm = $fastDeliveredPkg * $commissionInfo->fast_delivery_commission;
        // $fastFailedWithFeeComm = $fastFailedWithFeePkg * $commissionInfo->fast_delivery_commission;

        // $orderCount = Order::whereNull('driver_commission_id')->count();
        $orderCounts = Order::whereNull('driver_commission_id')
        ->where('is_deleted',false)
        ->where('driver_id',$user->id)
        // COUNT(*) as total_orders,
        ->selectRaw("
            COUNT(CASE WHEN status_id = 3 THEN 1 END) as pickup_count
        ")
        ->first();
        // // return $commissionInfo;
        // // $totalOrders = $counts->total_orders;
        $pickupCount = $orderCounts->pickup_count;
        // $pickedUpCount = $orderCounts->picked_up_count;


        // $balanceDues = TransactionService::getMobileUserBalance($req,$user,'driver');
        // $totalSettledDisburment = Disbursement::where('payee_id',$user->id)->where('type','payment')->where('is_deleted',0)->where('is_settled',1)->sum('payable_amount');
        $pcsUnitLng = $lang == 'km' ? 'កញ្ចប់': 'PCS';
        // $totalEearning = $normalDeliveryComm + $normalFailedWithFeeComm + $fastDeliveryComm + $fastFailedWithFeeComm;
        $totalDeliveredPkg = $normalDeliveredPkg;//+ $normalFailedWithFeePkg + $fastDeliveredPkg + $fastFailedWithFeePkg;

        $isSalaryDay = $user->info->isSalaryDay;
        return ApiResponse::JsonResult(data: HomeBalanceCardDTO::fromModel([
            'settleAmountUsd' => 'USD'.Helper::getNumber($collectedCod->drivercodusd ?? 0,2),//'USD ' . Helper::getNumber($collectedCod->drivercodusd ?? 0,2,true),
            'settleAmountKhr' => 'KHR'.Helper::getNumber($collectedCod->drivercodkhr ?? 0,2),//'KHR '. Helper::getNumber($collectedCod->drivercodkhr ?? 0,2,true),
            "accepted_order_count" => $pickedUpCount.$pcsUnitLng,
            "delivered_pkg_count" => $totalDeliveredPkg.$pcsUnitLng,
            "earning" =>  !$isSalaryDay ? "":Helper::currencyAmount(Helper::getNumber($normalDeliveryComm,2,true),'USD'),
            "pickupCount" => $pickupCount,
            "deliveryCount" => $allDeliveryPkg,
        ]));
    }

    // public function getDriverBalance(Request $req){
    //     $user = UserService::getAuthUser('driver');
    //     $lang = $req->lang;
    //     $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
    //     ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
    //     ->get();

    //     $commissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$user->id);
    //     // return $commissionInfo;

    //     $normalStartDatetime = $commissionInfo->normal_delivery_commission_start_date
    //         ? Helper::dateYMD($commissionInfo->normal_delivery_commission_start_date) . ' 00:00:00'
    //         : null;

    //     $fastStartDatetime = $commissionInfo->fast_delivery_commission_start_date
    //         ? Helper::dateYMD($commissionInfo->fast_delivery_commission_start_date) . ' 00:00:00'
    //         : null;

    //     $qP = Package::where('is_deleted', 0)
    //         ->where('driver_id', $user->id)
    //         ->whereIn('status_id', [6, 9, 19]);

    //     if ($normalStartDatetime || $fastStartDatetime) {
    //         $qP->where(function ($q) use ($normalStartDatetime, $fastStartDatetime) {
    //             if ($normalStartDatetime) {
    //                 $q->orWhere(function ($q) use ($normalStartDatetime) {
    //                     $q->where('delivery_type', 'normal')
    //                     ->where(function ($q) use ($normalStartDatetime) {
    //                         $q->where('delivered_datetime', '>=', $normalStartDatetime)
    //                             ->orWhere('failed_datetime', '>=', $normalStartDatetime)
    //                             ->orWhere('assign_driver_datetime', '>=', $normalStartDatetime);
    //                     });
    //                 });
    //             }
    //             if ($fastStartDatetime) {
    //                 $q->orWhere(function ($q) use ($fastStartDatetime) {
    //                     $q->where('delivery_type', 'fast')
    //                     ->where(function ($q) use ($fastStartDatetime) {
    //                         $q->where('delivered_datetime', '>=', $fastStartDatetime)
    //                             ->orWhere('failed_datetime', '>=', $fastStartDatetime)
    //                             ->orWhere('assign_driver_datetime', '>=', $fastStartDatetime);
    //                     });
    //                 });
    //             }
    //         });
    //     }

    //     $counts = $qP->selectRaw("
    //         COUNT(CASE WHEN status_id = 9 AND delivery_type = 'normal' THEN 1 END) AS delivered_normal_pkg,
    //         COUNT(CASE WHEN status_id = 9 AND delivery_type = 'fast' THEN 1 END) AS delivered_fast_pkg,

    //         COUNT(CASE WHEN status_id = 19 AND delivery_type = 'normal' THEN 1 END) AS failed_with_fee_normal_pkg,
    //         COUNT(CASE WHEN status_id = 19 AND delivery_type = 'fast' THEN 1 END) AS failed_with_fee_fast_pkg,

    //         COUNT(CASE WHEN status_id = 6 AND delivery_type = 'normal' THEN 1 END) AS delivery_normal_pkg,
    //         COUNT(CASE WHEN status_id = 6 AND delivery_type = 'fast' THEN 1 END) AS delivery_fast_pkg
    //     ")->first() ?? (object)[
    //         'delivered_normal_pkg' => 0, 'delivered_fast_pkg' => 0,
    //         'failed_with_fee_normal_pkg' => 0, 'failed_with_fee_fast_pkg' => 0,
    //         'delivery_normal_pkg' => 0, 'delivery_fast_pkg' => 0,
    //     ];

    //     //** Type: Normal */
    //     $normalDeliveredPkg = $counts->delivered_normal_pkg;
    //     $allDeliveryPkg = $counts->delivery_normal_pkg + $counts->delivery_fast_pkg;
    //     $normalFailedWithFeePkg = $counts->failed_with_fee_normal_pkg;

    //     //** Type: Fast */

    //     $fastDeliveredPkg = $counts->delivered_fast_pkg;
    //     $fastFailedWithFeePkg = $counts->failed_with_fee_fast_pkg;

    //     //** Normal Commission */
    //     $normalDeliveryComm = $normalDeliveredPkg * $commissionInfo->normal_delivery_commission;
    //     // $normalFailedWithFeeComm = $normalFailedWithFeePkg * $commissionInfo->normal_delivery_commission;


    //     // //** Fast Commission */

    //     // $fastDeliveryComm = $fastDeliveredPkg * $commissionInfo->fast_delivery_commission;
    //     // $fastFailedWithFeeComm = $fastFailedWithFeePkg * $commissionInfo->fast_delivery_commission;

    //     // $orderCount = Order::whereNull('driver_commission_id')->count();
    //     $orderCounts = Order::whereNull('driver_commission_id')
    //     ->where('is_deleted', 0)
    //     ->where('driver_id',$user->id)
    //     // COUNT(*) as total_orders,
    //     ->selectRaw("
    //         COUNT(CASE WHEN status_id = 3 THEN 1 END) as pickup_count,
    //         COUNT(CASE WHEN status_id = 5 THEN 1 END) as picked_up_count
    //     ")
    //     ->first();

    //     // // return $commissionInfo;
    //     // // $totalOrders = $counts->total_orders;
    //     $pickupCount = $orderCounts->pickup_count;
    //     $pickedUpCount = $orderCounts->picked_up_count;

    //     $balanceDues = TransactionService::getMobileUserBalance($req,$user,'driver');
    //     // $totalSettledDisburment = Disbursement::where('payee_id',$user->id)->where('type','payment')->where('is_deleted',0)->where('is_settled',1)->sum('payable_amount');
    //     $pcsUnitLng = $lang == 'km' ? 'កញ្ចប់': 'PCS';
    //     // $totalEearning = $normalDeliveryComm + $normalFailedWithFeeComm + $fastDeliveryComm + $fastFailedWithFeeComm;
    //     $totalDeliveredPkg = $normalDeliveredPkg + $normalFailedWithFeePkg + $fastDeliveredPkg + $fastFailedWithFeePkg;
    //     // $maxSettlement = $balanceDues['total'] > 150
    //     //     ? ((int) ceil($balanceDues['total'] / 100)) * 100
    //     //     : 800;

    //     // $percentSettlement = round($balanceDues['total'] / $maxSettlement * 100, 1);

    //     // $obj = [
    //         // 'max_settlement' => $maxSettlement,
    //         // 'percent_settlement' => $percentSettlement,
    //         // 'earning' => (string)Helper::getNumber($totalEearning,2,true),
    //         // 'delivered_count' => (string)$totalDeliveredPkg.$pcsUnitLng,
    //         // 'pickedup_count' => (string)$pickedUpCount.$pcsUnitLng,
    //         // 'pickup_count' => (string)$pickupCount,
    //         // 'delivery' => (string)$allDeliveryPkg,
    //         // 'settlement' => (string)Helper::getNumber($balanceDues['total'],2,true),
    //     // ];
    //     return ApiResponse::JsonResult(HomePaymentDTO::fromModel((object)[
            // "unpaid_amt" => '$'.$balanceDues['total'],
            // "accepted_order_count" => $pickedUpCount.$pcsUnitLng,
            // "delivered_pkg_count" => $totalDeliveredPkg.$pcsUnitLng,
            // "salary" => '$'.$normalDeliveryComm,
            // "pickup_count" => $pickupCount,
            // "on_delivery_count" => $allDeliveryPkg
    //     ]));
    // }

    public function getReturningPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $pk = Package::query()
        ->where('is_deleted',0)
        ->where('returned_uid',$user->id)
        ->where('status_id',TrackingStatus::RETURNING->value)
        ->with([
            'merchant:id,username,phone',
            'order:id,loc_lat,loc_lng,pickup_address'
        ])->orderByDesc('assigned_return_at');
        $geoResolver = new GeoResolverService();
        $callback = function ($pkg) use($geoResolver){
            $pkg->status = TrackingStatus::tryFrom($pkg->status_id)->label();
            $pkg->merchant_name = $pkg->merchant->username;
            $pkg->merchant_phone = $pkg->merchant->phone;
            $pkg->remarks = $pkg->delivery_remarks;
            $pkg->date = Helper::dateDMY($pkg->assigned_return_at,'d M, Y');
            $pkg->time = Helper::dateDMY($pkg->assigned_return_at,'h:i A');

            $locLat = (float)($pkg->order->loc_lat ?? 0);
            $locLng = (float) ($pkg->order->loc_lng ?? 0);
            // $geoMap = $geoResolver->fromCoords($locLat,$locLng);
            $pkg->loc_lat = $locLat;
            $pkg->loc_lng = $locLng;
            $pkg->map_address = $pkg->order->pickup_address;
            // $pkg->map_url = $geoMap['mapUrl'];
            $pkg->telegram_link = Helper::generateTelegramLink($pkg->merchant_phone);
            return HomeReturnPackageDTO::fromModel($pkg);
        };
        $select = ['id','merchant_id','status_id','order_id','delivery_remarks','assigned_return_at','created_at','qr_code'];
        return ApiResponse::PaginationV1($pk,$req,'',[],300,$callback,$select);
    }

    public function getOneAcceptedPickup(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        // ->with(['merchant','tracking_status','warehouse'])
        ->whereIn('status_id',[2,3,4])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,warehouse_id,driver_id,pickup_address_google_map,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->find($orderId);
        return ApiResponse::JsonResult($orders);
    }

    public function getDelivery(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $driverId = $user->id;
        $lang = $req->lang;
        $query = Delivery::where('driver_id', $driverId)->where('is_deleted',0)
        ->with(['status'])
        ->where(function ($q){
            $q->where('finished',0)->orWhereDate('depart_datetime',Carbon::today());
        })
        ->selectRaw('id,status_id,package_count,delivered_count,fleet_tracking_number,depart_datetime,driver_id')->orderByDesc('id');
        $cloneQ = clone $query;
        $packages = $this->tripPackageInfo($cloneQ->pluck('id')->toArray());
        $groupedPackages = $packages['packages']->groupBy('delivery_id');
        $callback = function ($fleet) use($groupedPackages,$lang){
            // $fleet->status_code = $fleet->status->name == '';
            $fleet->depart_date = Helper::formatCustomDateTime($fleet->depart_datetime,$this->dateFmt);
            $fleet->depart_time = Helper::formatCustomDateTime($fleet->depart_datetime,'h:i A');
            if($fleet->status_id == 14){
                $fleet->status_code = $lang == 'km' ? 'កំពុងដឹក':'On Trip';
            }else {
                $fleet->status_code = $lang == 'km' ? 'រួចរាល់':$fleet->status->name;
            }
            $fleetPackages = $groupedPackages->get($fleet->id, collect());
            $fleet->package_count = $fleetPackages->count();
            // $fleet->package_count = $packages['packages']->where('delivery_id',$fleet->id)->count();
            $fleet->total = $this->getTripTotalAmount($fleetPackages,$fleet->id);
            unset($fleet->status);
            return $fleet;
        };

        return ApiResponse::PaginationV1($query,$req,'',[],100,$callback);
    }

    public function getDeliveriesPackages(Request $req){
        $user = UserService::getAuthUser();
        $driverId = $user->id;
        $cutoff = Carbon::now()->subDays(15);
        $statusId = $req->query('status_id');
        $qP = Package::query()
        ->from('packages as p')
        ->where('p.is_deleted',false)
        ->where('p.driver_id', $driverId)
        ->whereIn('p.status_id', [6,9,10,19])
        ->whereRaw("
            (
                (p.status_id = 9 AND p.delivered_datetime >= ?)
                OR (p.status_id IN (10,19) AND p.failed_datetime >= ?)
                OR (p.status_id NOT IN (9,10,19))
            )
        ", [$cutoff, $cutoff])
        // ->where('p.arrive_warehouse_datetime', '>=', Carbon::now()->subDays(15))
        ->whereExists(function ($q) use ($driverId) {
            $q->select(DB::raw(1))
                ->from('delivery_packages as dp')
                ->join('deliveries as d', 'd.id', 'dp.delivery_id')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.is_deleted', 0)
                ->where('dp.has_swap', 0)
                ->where('dp.delay_count', 0)
                ->where('d.driver_id', $driverId)
                ->where('dp.id', function ($sub) {
                    $sub->selectRaw('MAX(id)')
                        ->from('delivery_packages')
                        ->whereColumn('package_id', 'p.id');
                })
                ->where(function ($q2) {
                    $q2->where('d.finished', 0)
                        ->orWhereDate('d.depart_datetime', Carbon::today());
                });
        })

        ->join('users as d', 'd.id', 'p.driver_id')
        ->join('users as m', 'm.id', 'p.merchant_id')
        // ->join('tracking_statuses as ts', 'ts.id', 'p.status_id')
        ->orderBy('p.driver_display_order', 'asc')
        // ->orderBy('p.status_id', 'desc');
        ->orderByRaw("
            CASE WHEN p.status_id = ? THEN assign_driver_datetime
            WHEN p.status_id = ? THEN delivered_datetime
            WHEN p.status_id IN (?,?) THEN failed_datetime
            END DESC, d.id DESC
        ",[6,9,10,19]);

        $totalOnDelivery = (clone $qP)->where('p.status_id', 6)->count();
        $totalDelivered = (clone $qP)->where('p.status_id', 9)->count();
        $totalFailed = (clone $qP)->where('p.status_id', 10)->count();
        $totalFailedWithFee = (clone $qP)->where('p.status_id', 19)->count();
        if ($statusId) {
            $qP->where('p.status_id', $statusId);
        }
        $select = [
            'p.driver_display_order','p.payer','p.receiver_address','p.extra_charge','p.id','p.delivered_datetime','p.failed_datetime',
            'p.assign_driver_datetime','p.merchant_id','p.qr_code','p.price','p.cod','p.receiver_name','p.receiver_phone','p.zone_code',
            'p.zone_name','d.username as driver_name','d.phone as driver_phone','m.username as merchant_name','p.arrive_warehouse_datetime',
            'm.phone as merchant_phone','p.id as package_id','p.zone_code','p.zone_name','p.delivery_fee as base_fee','p.driver_total',
            'p.taxi_fee','p.product_type','p.status_id','p.driver_notes','p.is_contact','p.remarks','p.price_khr','p.other_fee'
        ];
        $xRate = 4000;//GeneralSettingService::getLatestXRate()->sell_rate;
        $callback = function($q) use($xRate){
            $q->status = TrackingStatus::tryFrom($q->status_id)->label();
            $q->self_notes = $q->driver_notes;
            $q->total = $q->driver_total;
            $q->append('image_url');
            $priceKhr = $q->price_khr;
            $fees = $q->base_fee + $q->other_fee;
            $q->total_khr = $priceKhr > 0 ? number_format($priceKhr + ($q->payer == 'receiver' ? $fees:0) * $xRate,2,'.',''):"0";
            $q->fees_usd = $fees;
            $q->fees_khr = $fees * $xRate;
            // $q->exchange_rate = $xRate;
            $this->dateTimeByStatus($q,$q->status_id);
            return DeliveryTripsPackagesDTO::fromModel($q);
        };
        return ApiResponse::PaginationV1($qP,$req,'',[
            'total_count' => $totalOnDelivery + $totalDelivered + $totalFailed + $totalFailedWithFee,
            'total_on_delivery' => $totalOnDelivery,
            'total_delivered' => $totalDelivered,
            'total_failed' => $totalFailed,
            'total_failed_with_fee' => $totalFailedWithFee
        ],250,$callback,$select);
    }

    private function dateTimeByStatus(&$row, $statusId)
    {
        $datetimeMap = match (true) {
            in_array($statusId, [5, 6]) => $row->arrive_warehouse_datetime,
            $statusId === 9             => $row->delivered_datetime,
            in_array($statusId, [10, 19]) => $row->failed_datetime,
            default                     => null,
        };

        if ($datetimeMap) {
            $row->date = Helper::formatCustomDateTime($datetimeMap, 'd m Y');
            $row->time = Helper::formatCustomDateTime($datetimeMap, 'h:i A');
        }
        return $row;
    }

    public function editSelfNotes(Request $req){
        $id = $req->id;
        $driverId = $this->user->id;
        $package = Package::where('is_deleted',false)
        ->where('driver_id',$driverId)
        ->find($id);
        $notes = $req->notes ?? null;
        if(!$package){
            return ApiResponse::NotFound();
        }
        $package->update([
            'driver_notes' => $notes
        ]);
        return ApiResponse::JsonResult(null,__('messages.saved'));
    }

    private function getTripTotalAmount($packages,$tripId){
        $total = 0;
        foreach ($packages as $key => $p) {
            if($p->delivery_id == $tripId) {
                if($p->status_id != 11){
                    $total += $p->driver_total;
                }
            }
        }
        return '$'.$total;
    }

    private function tripPackageInfo($tripIds=null,$driverId = null){
        $qP = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->join('users as d','d.id','p.driver_id')
        ->leftJoin('users as m','m.id','p.merchant_id')
        ->where(function($q){
            $q->where('dp.is_deleted',0)
            ->where('dp.has_swap',0)
            ->where('dp.delay_count',0);
        })
        ->where('p.created_at', '>=', Carbon::now()->subDays(15))
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->selectRaw('p.driver_display_order,p.payer,p.receiver_address,p.extra_charge,p.id,p.delivered_datetime,p.failed_datetime,p.assign_driver_datetime,p.merchant_id,p.qr_code,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code,d.username as driver_name,d.phone as driver_phone,m.username as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.taxi_fee,p.product_type,p.status_id')
        ->orderBy('p.driver_display_order','asc')
        ->orderByRaw('(p.status_id = ?) DESC', [6]);
        // if($driverId){
        //     $qP->where('p.driver_id',$driverId);
        // }
        if ($driverId) {
            $qP->where(function ($query) use ($driverId) {
                $query->where(function ($q) use ($driverId) {
                    $q->where('p.status_id', 11)
                      ->where('p.returned_uid', $driverId);
                })->orWhere(function ($q) use ($driverId) {
                    $q->where('p.status_id', '!=', 11)
                      ->where('p.driver_id', $driverId);
                });
            });
        }

        if(!empty($tripIds)){
            $qP->whereIn('dp.delivery_id',$tripIds);
        }
        $packages = $qP->get();
        foreach($packages as $p){
            $p->date = Helper::formatCustomDateTime($p->assign_driver_datetime,$this->dateFmt.' h:i A');
            $p->action_date = Helper::formatCustomDateTime($p->assign_driver_datetime,$this->dateFmt);
            $p->action_time = Helper::formatCustomDateTime($p->assign_driver_datetime,'h:i A');
            $p->delivery_fee = Helper::getNumber($p->base_fee + $p->extra_charge,2);
            if($p->status_id == 9) {
                $p->date = Helper::formatCustomDateTime($p->delivered_datetime,$this->dateFmt.' h:i A');
                $p->action_date = Helper::formatCustomDateTime($p->delivered_datetime,$this->dateFmt);
                $p->action_time = Helper::formatCustomDateTime($p->delivered_datetime,'h:i A');
            }
            if($p->status_id == 10 || $p->status_id == 19) {
                $p->date = Helper::formatCustomDateTime($p->failed_datetime,$this->dateFmt.' h:i A');
                $p->action_date = Helper::formatCustomDateTime($p->failed_datetime,$this->dateFmt);
                $p->action_time = Helper::formatCustomDateTime($p->failed_datetime,'h:i A');
            }
            unset($p->assign_driver_datetime,$p->delivered_datetime,$p->failed_datetime);
        }
        return [
            'packages' => $packages,
            'total_packages' => $qP->count()
        ];
    }

    public function getDeliveryItems(Request $req){
        $user = $this->user;
        $tripId = $req->trip_id;
        $driverId = $user->id;
        $packages = $this->tripPackageInfo([$tripId],$driverId)['packages'];
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            $package->telegram_url = AppSetting::getTelegramLink('driver',$package->receiver_phone,$package->merchant_phone);
            unset($package->status);
        }
        return ApiResponse::JsonResult($packages);
    }

    public function acceptOrder(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        $user = UserService::getAuthUser('driver');
        if($user->error) return ApiResponse::flex($user);
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));

        if($order->status_id != 1){
            if($user->id != $order->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'This order is not available'
            ]));
            else return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'You have already accepted order ('.$order->code.')',
                'khInfo' => 'ការកម្នង់នេះមានគេទទួលរួចហើយ ('.$order->code.')'
            ]));
        }

        $trackingNotes = $order->tracking_notes.'|Driver has accepted order ('.$order->code.') '.date('d-M-Y h:i:s A');
        $order->update([
            'tracking_notes' => $trackingNotes,
            'status_id' => 3,
            'driver_id' => $user->id
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Order accepted',
            'khInfo' => 'ទទួលយកយកការកម្មង់'
        ]));
    }

    //** Pick or Pick & Book */
    public function updateAcceptedOrder(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        $statusId = $req->status_id;

        // if (request()->isMethod('post')) {
        //     $postSize = (int) request()->server('CONTENT_LENGTH', 0);
        // }

        // return $req;
        $order = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status'])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,status_id,merchant_id')
        ->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.info',[
            'info' => 'No order was found'
        ]));

        if($order->status_id == 2) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This order has already been picked'
        ]));
        if($order->status_id !== 3) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Only accepted orders can be updated',
            'khInfo' => 'ទាល់តែកញ្ចប់ដែលបានទទួលទើបអាចបន្ថែមព័ត៌មាន'
        ]));
        $qty = $req->qty;
        $images = $req->file('images') ?? [];
        $details = $req->details ?? [];
        if(!in_array($statusId,[2,4])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' =>'Please choose the correct status'
        ]));
        if($statusId == 4 && empty($details)) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please add details when you choose pick & book'
        ]));
        $qty = $req->qty ?? null;
        $user = UserService::getAuthUser('driver');

        $qty = $qty ?? $order->qty;
        if($qty <=0) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Order quantity must be atleast 1'
        ]));
        $acceptArr = [
            'status_id' => $statusId,
            'pickup_datetime' => now(),
            'qty' => $qty,
            'driver_id' => $user->id // the requester is driver
        ];
        if($statusId == 4){
            $acceptArr['booking_channel'] = 'driver';
            $pickMsg = 'Pick & Book';
        }

        if(isset($details[0])){
            // $detailsCount = count($details);
            // if($qty < $detailsCount) return ApiResponse::ValidateFail(__('messages.info',[
            //     'info' => 'Your details is greater than quantity',
            //     'khInfo' => 'ចំនួនកញ្ចប់និងទិន្នន័យកញ្ចប់មិនត្រូវគ្នា, ទិន្នន័យបញ្ចូលលើសចំនួនសរុប'
            // ]));
            foreach($details as $d){
                $d['merchant_id'] = $order->merchant_id;
                $d['cod'] = 0;
                $price = $d['price'] ?? 0;
                $priceKhr = $d['price_khr'] ?? 0;
                $hasPrice = $price + $priceKhr > 0 ? 1 : 0;
                if($hasPrice > 0){
                    $d['cod'] = 1;
                }
                $rD = new Request($d);
                $savePkg = $this->pickupCenterService->createOrUpdatePackage($rD,$user,null,$orderId);
                if($savePkg->error) return ApiResponse::flex($savePkg);
            }
        }
        if($statusId == 2) {
            $pickMsg = 'Pickup';
            // $imgCount = count($images);
            // if($imgCount != $qty) return ApiResponse::ValidateFail(__('messages.info',[
            //     'info' => 'Your package quantity is not matching the number of photos.',
            //     'khInfo' => 'ចំនួនកញ្ចប់និងចំនួនរូបភាពមិនត្រូវគ្នា'
            // ]));
            $maxSize = Helper::validTotalImageSize($images);
            if($maxSize->error) return ApiResponse::ValidateFail($maxSize->message);
            foreach($images as $idx => $image){
                $isValidUpload = Helper::isValidUploadImage($image,0.8);
                if($isValidUpload->error) return ApiResponse::ValidateFail($isValidUpload->message.', check your Image #'.($idx + 1));
                $photoFileName = Helper::saveImageFile($image,$user->company_id,'order_image')->filename;
                if($photoFileName){
                    OrderImage::create([
                        'order_id' => $orderId,
                        'original_name' => $image->getClientOriginalName(),
                        'photo_file_name' => $photoFileName,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id
                    ]);
                }
            }
        }
        $order->update($acceptArr);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'You have accepted for '.$pickMsg,
            // 'khInfo' => 'បានបញ្ចូលទិន្នន័យកញ្ចប់',
            'khInfo' => 'បានទទួល'
        ]));
    }

    public function editMerchantShopLocation(Request $req){
        $authUser = UserService::getAuthUser();
        $userShopService = new UserShopService();
        $editable = $userShopService->editMerchantShopLocation($authUser,$req->merchantId,$req);
        return ApiResponse::flex($editable);
    }

    public function getMerchantShopLocation(Request $req){
        // $authUser = auth()->user();
        $userShopService = new UserShopService();
        $editable = $userShopService->getPickUpLocation($req->merchantId);
        return ApiResponse::flex($editable);
    }


    private function validatePackageDetails(Request $req){
        return validator($req->all(),[
            'price' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string|max:250',
            'receiver_phone' => 'required|string|max:20|min:8',
            'receiver_name' => 'nullable|string|max:50',
            'actual_kg' => 'nullable|numeric',
            'zone_code' => 'required',
            'merchant_id' => 'nullable',
            'pickup_notes' => 'nullable|string|max:250'
        ],[
            'receiver_phone.min' => __('messages.info',[
                'info' => 'Please enter a valid phone number',
                'khInfo' => 'សូមបញ្ចូលលេខទូរស័ព្ទដែរត្រឹមត្រូវ'
            ])
        ]);
    }

    public function submitDeliveryPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->package_id;
        $validate = validator($req->all(),[
            'status_id' => 'required|in:9,10,19',
            'delivery_remarks' => 'nullable|string',
            'images' => 'nullable',
            'driver_cod_usd' => 'nullable',
            'driver_cod_khr' => 'nullable',
            'amount' => 'nullable|numeric',
            'currency' => 'nullable',
            'payer' => 'nullable|in:sender,receiver'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $status_id = $inputs['status_id'];
        $inputs['last_submit_uid'] = $user->id;
        $inputs['driver_cod_usd'] = $inputs['driver_cod_usd'] ?? 0;
        $inputs['driver_cod_khr'] = $inputs['driver_cod_khr'] ?? 0;
        $inputs['original_driver_cod_usd'] = $inputs['driver_cod_usd'];
        $inputs['original_driver_cod_khr'] = $inputs['driver_cod_khr'];
        // $amount = $inputs['amount'] ?? 0;
        // $inputs['price'] = $amount;
        // $inputs['cod'] = $amount > 0 ? true:false;
        // $codChange = $amount > 0 ? true:false;
        // $inputs['cod_changed'] = $codChange;
        $photos = $inputs['images'] ?? null;
        $deliveryRemarks = $inputs['delivery_remarks'] ?? null;

        // if($codChange){
        //     $inputs['driver_total'] = $amount;
        // }

        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));

        $payer = $inputs['payer'] ?? $package->payer;

        if($package->status_id == 9) {
            return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'This package has already been delivered!',
                'khInfo' => 'កញ្ចប់​បានដឹករួចហើយ'
            ]));
        }

        else if($package->status_id == 10) {
            return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'This package has summitted as failed, Only On Delivery can be summitted!',
                'khInfo' => 'កញ្ចប់​បរាជ័យ, មានតែកញ្ចប់​ដែលកំពុងដឹកទើបប្រតិបត្តិបាន'
            ]));
        }

        else if($package->status_id == 11){
            return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'Package has already been returned.',
                'khInfo' => 'កញ្ចប់បានយកត្រឡប់ទៅហាងរួចហើយ'
            ]));
        }


        else if($package->driver_id !== $user->id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Please submit package that belongs to you',
            'khInfo' => 'សូមបញ្ជូនកញ្ចប់ដែលជាកម្មសិទ្ធិរបស់អ្នក'
        ]));

        else if($package->status_id == 19) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package is already failed with fee.',
            'khInfo' => 'កញ្ចប់ធ្លាប់បរាជ័យគិតសេវា'
        ]));

        PackageAttachment::where('package_id',$id)->update([
            'hidden' => 1
        ]);

        $attactmentImgs = [];
        if(isset($photos[0])) {
            foreach($photos as $p){
                $dirName = ImageDirectory::SUBMIT_PACKAGE->value;
                $today = date('Y-m-d');
                $isValidUpload = Helper::isValidUploadImage($p,3);
                if($isValidUpload->error) return ApiResponse::ValidateFail($isValidUpload->message);
                $fileName = Helper::saveImageFileOrBase64($p,$user->company_id,$dirName,$today)->filename;
                if($fileName){
                    $attactmentImgs[] = [
                        'package_id' => $id,
                        'file_dir' => $dirName,
                        'submit_uid' => $user->id,
                        'file_name' => $fileName,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        $todayDt = Helper::getDateTime();
        $driverName = $user->username;
        $statusCode = $status_id == 9 ? 'Delivered' : ($status_id == 10 ? 'Failed':($status_id == 19 ? 'Failed with fee':''));
        $inputs['tracking_notes'] = $package->tracking_notes."|[$user->id]Driver ($driverName) submit $statusCode ($todayDt)[Remark: $deliveryRemarks]";
        // if($codChange && $package->price != $amount){
        //     $inputs['tracking_notes'] .= "|[$user->id]Driver ($driverName) change cod $package->price to $amount ($todayDt)";
        // }
        if($status_id == 9) {
            $inputs['delivered_datetime'] = now();
            $inputs['delivery_remarks'] = $deliveryRemarks;
        }

        if($status_id == 10) {
            if(!$deliveryRemarks) return ApiResponse::ValidateFail(__('messages.info',[
                'Please input remarks'
            ]));
            $inputs['failed_datetime'] = now();
            $inputs['failure_notes'] = $deliveryRemarks;
        }

        if($status_id == 19) {
            $inputs['failed_datetime'] = now();
            $inputs['failure_notes'] = $deliveryRemarks;
            // $package->price = 0;
        }

        if($payer && $status_id == 19){
            // $calucalteFee = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$payer,$package->cod,$package->other_fee,$user,$package->taxi_fee,$package->merchant_id,$status_id);
            // $inputs['merchant_total'] = $calucalteFee->merchant_total;
            // $inputs['driver_total'] = $calucalteFee->driver_total;
            if($payer == 'receiver'){
                $inputs['driver_cod_usd'] = $package->delivery_fee + $package->other_fee;
            }
        }

        try{
            DB::beginTransaction();
            $package->update($inputs);
            $dp = DeliveryPackage::where('package_id',$id)->where('driver_id',$user->id)
            ->where('is_deleted',0)
            ->where('has_swap',0)
            ->orderByDesc('id')->where('delay_count',0)->first();
            $dp->update([
                'notes' => $inputs['tracking_notes'],
                'status_id' => $status_id
            ]);
            GeneralSettingService::updateTripStatus($dp->delivery_id,$user);
            if(!empty($attactmentImgs)){
                PackageAttachment::insert($attactmentImgs);
            }
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.submitted',[
                'info' => 'Package has',
                'khInfo' => 'បានបញ្ចូន'
            ]));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return ApiResponse::Error('It will get back soon!');
        }

    }

    public function cancelOrder(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $reason = $req->reason;
        if(!$reason) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please enter a reason'
        ]));
        $order = Order::where('is_deleted',0)->where('driver_id',$user->id)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));
        // if($order->status_id == 20) return ApiResponse::Duplicated(__('messages.info',[
        //     'info' => 'Package has already been canceled'
        // ]));
        $order->update([
            'cancel_notes' => $reason,
            'cancel_uid' => $user->id,
            'status_id' => 1 // canceled
        ]);
        return ApiResponse::JsonResult(null,__('messages.canceled'));
    }

    public function dropOrderAtWarehouse(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->where('driver_id',$user->id)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order',
        ]));
        if($order->status_id == 21) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Order has already dropped'
        ]));
        if(!in_array($order->status_id,[2,4])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You can not mark as dropped'
        ]));
        $driverName = $user->name;
        $todayDt = Helper::getDateTime();
        $tracking_notes = $order->tracking_notes."|[$user->id]Driver ($driverName) dropped order ($todayDt)";
        $order->update([
            'status_id' => 21,
            'tracking_notes' => $tracking_notes
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Dropped',
        ]));
    }

    public function markPackageContact(Request $req){
        $user = UserService::getAuthUser('driver');
        $packageRef = $req->package_ref;
        $package = Package::where('is_deleted',0)->where('driver_id',$user->id)->find($packageRef);
        if(!$package) Package::where('is_deleted',0)->where('driver_id',$user->id)->where('qr_code',$packageRef);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package',
            'khInfo' => 'កញ្ចប់'
        ]));
        if($package->is_contact) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This package has already contacted',
            'khInfo' => 'កញ្ចប់នេះបានទំនាក់ទំនងរួចរាល់'
        ]));
        // if(!in_array($package->status_id,[6])) return ApiResponse::ValidateFail(__('messages.info',[
        //     'info' => 'You can not mark as dropped'
        // ]));
        // $driverName = $user->username;
        $todayDt = Helper::getDateTime();
        $tracking_notes = $package->tracking_notes."|[$user->id]Driver Marked contact $todayDt";
        $package->update([
            'is_contact' => 1,
            'contact_datetime' => now(),
            'tracking_notes' => $tracking_notes
        ]);

        //** Send Notif */
        $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$package->merchant_id);
        $notifReq = new Request([
            'topic' => $topics->private,
            'type' => 'private',
            'target_uid' => $package->merchant_id,
            'title' => 'Contact receiver',
            'body' => 'Driver contacted receiver '.$package->receiver_phone
        ]);
        $queueFCMName = config('queue_job_names.'.config('app.env').'.notification');
        SendNotificationJob::dispatch($notifReq, $user)->onQueue($queueFCMName);
        return ApiResponse::JsonResult(null,__('messages.saved'));

    }

    public function getOptionsStatus(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick',null,null,$req->lang);
        return ApiResponse::JsonResult($statuses);
    }

    public function setArriveWarehouse(Request $req){
        $user = UserService::getAuthUser('driver');
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick',null,null,$req->lang);
        return ApiResponse::JsonResult($statuses);
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    public function sortPackages(Request $req){
        // $user = UserService::getAuthUser('driver');
        $sortListIds = $req->input('sort_list');
        if(empty($sortListIds)) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Sort list is required'
        ]));
        // $packageIds = Helper::pluckArrValue($sortList);
        $packages = Package::where('is_deleted',0)
        ->whereIn('id',$sortListIds)
        ->select('id','driver_display_order')
        ->get()->keyBy('id');
        // return $packages;
        $lastIdx = 1;
        DB::beginTransaction();
        try {
            foreach (array_values($sortListIds) as $index => $pkgId) {
                if (!isset($packages[$pkgId])) {
                    // DB::rollBack();
                    return ApiResponse::NotFound("Package row (" . ($index + 1) . ") not found!");
                }
                $lastIdx += $index + 1;
                $packages[$pkgId]->update(['driver_display_order' => $index + 1]); // Efficient batch update
            }
            Package::whereNotIn('id', $sortListIds)
            ->where('driver_id',$this->user->id)
            ->update(['driver_display_order' => $lastIdx]);
            DB::commit();
            return ApiResponse::JsonResult(null, 'Sorted');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ApiResponse::Error('Failed');
        }
    }

    public function booking(Request $req){
        $user = UserService::getAuthUser('driver');
        $req->merge([
            'warehouse_id' => $user->info->warehouse_id,
            'branch_id' => $user->branch_id,
        ]);
        $createOrder = $this->pickupCenterService->createOrder($req,$user);
        return ApiResponse::flex($createOrder);
    }


    public function getNotifications(){
        $user = UserService::getAuthUser('driver');
        $notifications = Notification::where('user_id',$user->id)->where('is_read',0)->orderByDesc('sent_datetime')->selectRaw('id,is_read,title,body,sent_datetime')->get();
        $groupedPackages = collect($notifications)->map(function ($item) {
            $sentAt = Carbon::parse($item->sent_datetime)->timezone(config('app.timezone'));
            if ($sentAt->isToday()) {
                $item->groupKey = 'Today';
            } elseif ($sentAt->isYesterday()) {
                $item->groupKey = 'Yesterday';
            } else {
                $item->groupKey = $sentAt->format('d-M-Y'); // e.g., 17-Apr-2025
            }
            // $item->groupKey = $sentAt;
            $item->time = Helper::formatCustomDateTime($item->sent_datetime,'h:i A');
            $item->time_ago = Helper::timeAgo($sentAt,false);
            return $item;
        })
        ->groupBy('groupKey')
        ->map(function ($group, $date) {
            $group->each(function ($item) use ($group) {
                unset($item->groupKey,$item->sent_datetime);
            });
            return [
                'date' => $date,
                'details' => $group->values(),
            ];
        })->values();
        return ApiResponse::JsonResult($groupedPackages);
    }

    public function readNotification(Request $req){
        $user = UserService::getAuthUser('driver');
        $mr = GeneralSettingController::markReadNotification($req,$user);
        return ApiResponse::flex($mr);
    }

    public function getScoringReward(){
        $user = UserService::getAuthUser('driver');
        $scoringReward = ScoringReward::select('message','description')->find(1);
        $userReward = UserScoringReward::where('user_id',$user->id)->where('reward_id',1)->first();
        $alertMsg = '';
        $message = '';
        if($scoringReward){
            $replaceKeys = ['N/A'];
            if($userReward){
                $replaceKeys = [$userReward->amount];
            }else{
                $alertMsg = 'You have no reward';
            }
            $message = Str::replace(['??amount??'], $replaceKeys, $scoringReward->message);
        }
        return ApiResponse::JsonResult([
            'target' => [
                'date' => 'April 2025',
                'title' => 'Your Monthly target',
                'target_packages' => $userReward ? (string)($userReward->target_package.' points') : '0 points',
                'current_packages' => 10
            ],
            'alert_message' => $alertMsg,
            'message' => $message,
            'description' => $scoringReward->description ?? ''
        ]);
    }

    public function getEmergencyContact(){
        $user = UserService::getAuthUser('driver');
        $emergencyContacts = EmergencyContact::where('is_deleted',0)
        ->select('name_en as name','phone','logo')
        ->get()->map(function($emer) use ($user){
            $emer->logo = Helper::getImageUrl($emer->logo,$user->company_id,'emergency');
            return $emer;
        });
        return ApiResponse::JsonResult($emergencyContacts);
    }

    public function getFeedbackQuestions(Request $req){
        $lang = $req->lang;
        $nameKey = 'question_'.$lang.' as question';
        $questions = FeedbackQuestion::where('form_id',1)
        ->select('id',$nameKey)
        ->get();
        return ApiResponse::JsonResult($questions);
    }

    public function createFeedback(Request $req){
        $user = UserService::getAuthUser('driver');
        $validator = validator($req->all(),[
            'comment' => 'nullable|string|max:255',
            'answers' => 'required|array'
        ]);

        if($validator->fails()) return ApiResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $failMsg = '';
        try{
            DB::transaction(function () use ($inputs, $user,&$failMsg) {
            // Create the submission once\
                $formId = 1;// Default Form for driver ***
                $submission = FeedbackSubmission::create([
                    'comment' => $inputs['comment'] ?? null,
                    'form_id' => $formId,
                    'submitted_datetime' => now(),
                    'user_id' => $user->id,
                    'create_uid' => $user->id,
                    'update_uid' => $user->id,
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id
                ]);

                // Prepare all answer rows with submission_id
                $answerArr = [];
                $answeredQuestionIds = [];
                $questionIds = FeedbackQuestion::where('is_deleted',0)->where('form_id',$formId)->get()->keyBy('id');
                if (count($inputs['answers']) !== $questionIds->count()) {
                    $failMsg = 'The number of answers must match the number of questions.';
                    throw new Exception($failMsg);
                }
                foreach ($inputs['answers'] as $idx => $ans) {
                    if (empty($ans['rate']) || empty($ans['question_id'])) {
                        $failMsg = 'Please rate all questions.';
                        throw new Exception($failMsg);
                    }
                    if($ans['rate'] < 1 || $ans['rate'] >5){
                        $failMsg = 'Please rate between 1-5';
                        throw new Exception($failMsg);
                    }
                    if(empty($questionIds[$ans['question_id']])) {
                        $failMsg = 'Please rate all questions.';
                        throw new Exception($failMsg.' invalid id or missing');
                    }
                    if (isset($answeredQuestionIds[$ans['question_id']])) {
                        $failMsg = 'You have already answered question ' . $answeredQuestionIds[$ans['question_id']];
                        throw new Exception($failMsg);
                    }
                    $answeredQuestionIds[$ans['question_id']] = $idx + 1;
                    $answerArr[] = [
                        'user_id' => $user->id,
                        'submission_id' => $submission->id,
                        'rating' => $ans['rate'],
                        'question_id' => $ans['question_id'],
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id
                    ];
                }
                FeedbackAnswer::insert($answerArr);
            });
            if(!empty($failMsg)) return ApiResponse::ValidateFail($failMsg);
            return ApiResponse::JsonResult(null,__('messages.saved'));
        }catch (Exception $e){
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            if(!empty($failMsg)) return ApiResponse::ValidateFail($failMsg);
            return ApiResponse::Error('Something went wrong!');
        }
    }
}
