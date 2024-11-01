<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Http\Request;

class FleetManagementController extends Controller
{
    //
    public function getTrips(Request $req){
        $user = UserService::getAuthUser();
        $query = Delivery::with(['status'])->where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,fleet_tracking_number,status_id,depart_datetime,remarks,package_count,delivered_count,failed_count,warehouse_id,vehicle_type');
        $deliveries = $query->get();
        foreach($deliveries as $delivery){
            $delivery->status_code = $delivery->status->name;
            unset($delivery->status);
        }
        return ApiResponse::Pagination($deliveries,$req);
    }

    public function getTripPackages(Request $req){
        $trip_id = $req->trip_id;
        $packages = DeliveryPackage::from('delivery_packages as dp')
            ->where('dp.delivery_id', $trip_id)
            ->join('packages as p', 'dp.package_id', '=', 'p.id')
            ->with(['status'])
            ->orderByDesc('p.id')
            ->selectRaw('dp.status_id,dp.package_id,delivery_id,p.qr_code,p.product_type,p.price,p.dim_x,p.dim_z,p.dim_y,dp.failure_notes')
            ->get();
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            unset($package->status);
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
