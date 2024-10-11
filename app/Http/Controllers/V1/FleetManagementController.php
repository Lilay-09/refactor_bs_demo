<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
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
}
