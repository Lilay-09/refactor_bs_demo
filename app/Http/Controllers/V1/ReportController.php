<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
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
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $orders
        ];
        return ApiResponse::JsonResult($obj,'Get Pickup List');
    }

    public function getDailyPackageReport(Request $req){
        $user = UserService::getAuthUser();
        $qP = Package::where('is_deleted',0)
        ->with(['status','driver','merchant'])
        ->selectRaw('merchant_id,driver_id,product_type,receiver_address,receiver_phone,cod,delivery_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,assign_driver_datetime,updated_at,failed_datetime,delivered_datetime');
        $packages = $qP->get();
        foreach($packages as $pkg){
            $pkg->status_code = $pkg->status->name;
            $pkg->merchant_name = $pkg->merchant->user_name;
            $pkg->merchant_phone = $pkg->merchant->phone;
            $pkg->driver_name = $pkg->driver?->user_name;
            $pkg->driver_phone = $pkg->driver?->phone;
            $actionDate = Helper::formatCustomDateTime($pkg->updated_at);
            if($pkg->status_id == 5) Helper::formatCustomDateTime($pkg->arrive_warehouse_datetime);
            if($pkg->status_id == 6) Helper::formatCustomDateTime($pkg->assign_driver_datetime);
            if($pkg->status_id == 10) Helper::formatCustomDateTime($pkg->failed_datetime);
            if($pkg->status_id == 9) Helper::formatCustomDateTime($pkg->delivered_datetime);
            $pkg->action_datetime = $actionDate;
            unset($pkg->driver,$pkg->merchant,$pkg->status);
        }
        $obj =(object)[
            'company_profile' => CompanyProfileService::profileInfo($user),
            'list' => $packages
        ];
        return ApiResponse::JsonResult($obj,'Get Pickup List');
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
