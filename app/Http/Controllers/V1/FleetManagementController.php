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
        ->selectRaw('fleet_tracking_number,status_id,depart_datetime,remarks,package_count,delivered_count,failed_count,warehouse_id,vehicle_type');
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
            ->selectRaw('dp.status_id,dp.package_id,delivery_id,p.qr_code,p.product_type,p.price,p.dim_x,dim_z,dim_y,dp.failure_notes')
            ->get();
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            unset($package->status);
        }
        return ApiResponse::JsonResult($packages,false,__('messages.get_list'));
    }

    public function setPackageStatus(Request $req){
        $user = UserService::getAuthUser();
        $trip_id = $req->trip_id;
        $package_id = $req->package_id;
        $status = $req->status;
        $failure_notes = $req->failure_notes ?? null;
        if(!$status) return ApiResponse::ValidateFail(__('messages.not_found',['info' => 'Status']));
        // $deliveryPackage = DeliveryPackage::where('company_id',$user->company_id)->where('delivery_id',$trip_id)->where('package_id',$package_id)->where('is_deleted',0)->first();
        // if(!$deliveryPackage) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        // $deliveryPackage->update([
        //     'delivered_datetime' => $status == 2 ? now():null,
        //     'update_uid' => $user->id,
        //     'failed_datetime' => $status == 1 ? now() : null,
        //     'failure_notes' => $status == 1 ? $failure_notes : null,
        //     'status_id' => $status == 2 ? 9 : 10
        // ]);
        $package->update([
            'update_uid' => $user->id,
            'failure_notes' => $status == 1 ? $failure_notes : null,
            'failed_date' => $status == 1 ? now() : null,
            'delivered_datetime' => $status == 2 ? now():null,
            'status_id' => $status == 2 ? 9 : 10
        ]);
        GeneralSettingService::setDeliveryStatus($trip_id,$user);
        return ApiResponse::JsonResult(null,false,__('messages.updated'));
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
