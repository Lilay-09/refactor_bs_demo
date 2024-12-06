<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FeedBack;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    //** Company Report */

    public function getPickupReport(Request $req){
        $user = UserService::getAuthUser();
        $qORder = Order::whereNotNull('driver_id')->with(['driver','merchant'])
        ->where('status_id',5)
        ->where('company_id',$user->company_id)
        ->selectRaw('driver_id,merchant_id,code,product_type,pickup_address,qty,vehicle_type');
        $orders = $qORder->get();
        foreach($orders as $order){
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_phone = $order->merchant->phone;
            $order->driver_name = $order->driver->user_name;
            $order->driver_phone = $order->driver->phone;
            unset($order->driver,$order->merchant);
        }
        $obj =(object)[
            'title' => 'Daily Packages',
            'sub_title' => 'Arrivate Date:',
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $orders
        ];
        return ApiResponse::JsonResult($obj,'Get Pickup List');
    }

    public function getDailyPackageReport(Request $req){
        $user = UserService::getAuthUser();
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
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];

        return ApiResponse::JsonResult($obj,'Get Pickup List');
    }

    public function getDailyPackageSummaryReport(){
        $user = UserService::getAuthUser();
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
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $groupedPackages
        ];

        return ApiResponse::JsonResult($obj,'Get Daily Packages Summary');

    }


    public function getReviewAndFeedBackReport(){
        $user = UserService::getAuthUser();
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
            'total' => $total,
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $feedBack
        ];

        return ApiResponse::JsonResult($obj,'Get Daily Packages Summary');
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
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
        ];
        return ApiResponse::JsonResult($obj);
    }




    // **Driver Report
    public function formOptionDriver (){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'warehouses' => GeneralSettingService::optionsWarehouse($user),
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function driverList(Request $req){
        $qD = User::where('account_type','driver')
        ->selectRaw('code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $drivers = $qD->get();
        return ApiResponse::JsonResult($drivers,'Driver List');
    }

    public function optionsWarehouse(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsWarehouse($user));
    }

    public function driverDeliverySummary(Request $req){
        $user = UserService::getAuthUser();
        $packages = Package::where('is_deleted',0)->whereIn('status_id',[9,10,19])->get();
        $qD = User::where('account_type','driver')
        ->selectRaw('id,code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $drivers = $qD->get();
        foreach($drivers as $d){
            $details = $this->getDriverSummaryDetails($packages,$d->id);
            $d->delivered_count = $details->delivered_count;
            $d->failed_with_fee_count = $details->failed_with_fee_count;
            $d->returned_count = $details->returned_count;
        }
        return ApiResponse::JsonResult($drivers);
    }

    private function getDriverSummaryDetails($rows,$driverId){
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


}
