<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class FleetManagementController extends Controller
{
    //
    public function getTrips(Request $req){
        $user = UserService::getAuthUser();
        // $deliveries = Delivery::fromRaw('deliveries as d')->join('users as ud','d.driver_id','ud.id')
        // ->where('d.is_deleted',0)->where('d.company_id',$user->company_id)
        // ->join('tracking_statuses as ts','ts.id','d.status_id')
        // ->selectRaw('d.id,d.fleet_tracking_number,d.status_id,d.depart_datetime,d.remarks,d.package_count,d.delivered_count,d.failed_count,d.warehouse_id,d.vehicle_type,d.driver_id,ts.name as status_code,ud.user_name as driver_name,ud.phone as driver_phone')
        // ->get();
        // foreach($deliveries as $delivery){
        //     $delivery->depart_time = Helper::formatCustomDateTime($delivery->depart_datetime,'h:i:s');
        //     $delivery->depart_date = Helper::formatCustomDateTime($delivery->depart_datetime,'d-M-Y',false);
        // }
        $packages = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->selectRaw('p.id as package_id,dp.delivery_id,sum(p.driver_total) as driver_total')
        ->where('dp.delay_count',0)
        ->groupBy('p.id','dp.delivery_id','dp.delay_count')
        ->get();
        $query = Delivery::with(['status','driver'])->where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,fleet_tracking_number,status_id,depart_datetime,remarks,package_count,delivered_count,failed_count,warehouse_id,vehicle_type,driver_id');
        $deliveries = $query->get();
        foreach($deliveries as $delivery){
            $delivery->status_code = $delivery->status->name;
            $delivery->driver_name = $delivery->driver->user_name;
            $delivery->driver_phone = $delivery->driver->phone;
            $delivery->total = $this->getTripTotal($packages,$delivery->id);
            $delivery->depart_time = Helper::formatCustomDateTime($delivery->depart_datetime,'h:i:s');
            $delivery->depart_date = Helper::formatCustomDateTime($delivery->depart_datetime,'d-M-Y',false);
            unset($delivery->status,$delivery->driver);
        }
        return ApiResponse::Pagination($deliveries,$req);
    }

    public function getTripTotal($packages,$deliveryId){
        $total = 0;
        foreach($packages as $pkg){
            if($pkg->delivery_id == $deliveryId){
                $total += $pkg->driver_total;
            }
        }
        return $total;
    }

    public function getTripPackages(Request $req){
        $trip_id = $req->trip_id;
        $packages = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->where('dp.delay_count',0)
        ->where('dp.delivery_id',$trip_id)
        ->join('users as m','m.id','p.merchant_id')
        ->join('users as d','d.id','p.driver_id')
        ->selectRaw('d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee,p.driver_total,p.taxi_fee,p.product_type')
        ->get();
        foreach($packages as $package){
            // $package->status_code = $package->status->name;
            // unset($package->status);
        }
        return ApiResponse::JsonResult($packages,__('messages.get_list',['info' => 'Package']));
    }

    public function setPackageStatus(Request $req){
        $user = UserService::getAuthUser();
        $trip_id = $req->trip_id;
        $package_id = $req->package_id;
        $status_id = $req->status_id;
        $failure_notes = $req->failure_notes ?? null;
        $delivery = Delivery::where('is_deleted',0)->find($trip_id);
        $tripPackage = DeliveryPackage::where('package_id',$package_id)->where('delivery_id',$trip_id)->first();
        if(!$tripPackage) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package',
            'khInfo' => 'កញ្ចប់'
        ]));
        if(!$delivery) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Trip']));
        if(!$status_id || !in_array($status_id,[9,10,19])) return ApiResponse::ValidateFail(__('messages.not_found',['info' => 'Status']));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if(!in_array($package->status_id,[6,9,10,19])) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Package must be on delivery before set to delivered,failed or failed with fee.']));
        $failDatetime = ($status_id == 10 || $status_id == 19) ? now() : null;
        $deliveredDatetime = $status_id == 9 ? now():null;
        $package->update([
            'update_uid' => $user->id,
            'failure_notes' => $failure_notes,
            'failed_datetime' => $failDatetime,
            'delivered_datetime' => $deliveredDatetime,
            'status_id' => $status_id
        ]);

        DeliveryPackage::where('package_id',$package_id)->update([
            'update_uid' => $user->id,
            'failure_notes' => $failure_notes,
            'failed_datetime' => $failDatetime,
            'delivered_datetime' => $deliveredDatetime,
            'status_id' => $status_id
        ]);
        GeneralSettingService::updateTripStatus($trip_id,$user);
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function getTripByDriver(Request $req){
        $driverId = $req->driver_id;
        $todayDate = date('Y-m-d');
        $user = UserService::getAuthUser();
        $trip = Delivery::with(['status'])->where('is_deleted',0)->where('company_id',$user->company_id)
        ->whereDate('depart_datetime',$todayDate)
        ->selectRaw('id,fleet_tracking_number,status_id,depart_datetime,remarks,package_count,delivered_count,failed_count,warehouse_id,vehicle_type')
        ->first();
        return ApiResponse::ValidateFail($req);
    }

    public function takeOutPackage(Request $req){
        $user = UserService::getAuthUser();
        $trip_id = $req->trip_id;
        $package_id = $req->package_id;
        $deliveryPackage = DeliveryPackage::where('company_id',$user->company_id)->where('delivery_id',$trip_id)->where('package_id',$package_id)->where('is_deleted',0)->first();
        if(!$deliveryPackage) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
    }


}
