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
        $delivery = DeliveryPackage::from('delivery_packages as dp')
            ->where('dp.delivery_id', $trip_id)
            ->join('packages as p', 'dp.package_id', '=', 'p.id')
            ->with(['status'])
            ->selectRaw('dp.')
            ->get();
        return ApiResponse::JsonResult($delivery,false,__('messages.get_list'));
    }
}
