<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Models\User;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use DB;
use Helper;
use Illuminate\Http\Request;
use Log;

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
        ->selectRaw('p.id as package_id,dp.delivery_id,dp.delay_count,dp.status_id,p.driver_total')
        // ->where('dp.delay_count',0)
        // ->groupBy('p.id','dp.delivery_id','dp.delay_count')
        ->get();
        $query = Delivery::with(['status','driver'])->where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,fleet_tracking_number,status_id,depart_datetime,remarks,package_count,delivered_count,failed_count,warehouse_id,vehicle_type,driver_id');
        $deliveries = $query->get();
        foreach($deliveries as $delivery){
            $delivery->status_code = $delivery->status->name;
            $delivery->driver_name = $delivery->driver->user_name;
            $delivery->driver_phone = $delivery->driver->phone;
            $details = $this->getTripDetails($packages,$delivery->id);
            $delivery->total = $details->total;
            $delivery->total_delivered = $details->total_delivered;
            $delivery->failed_count = $details->failed_count;
            $delivery->delivery_count = $details->delivery_count;
            $delivery->failed_with_fee_count = $details->failed_with_fee_count;
            $delivery->depart_time = Helper::formatCustomDateTime($delivery->depart_datetime,'h:i:s');
            $delivery->depart_date = Helper::formatCustomDateTime($delivery->depart_datetime,'d-M-Y',false);
            unset($delivery->status,$delivery->driver);
        }
        return ApiResponse::Pagination($deliveries,$req);
    }

    public function getTripDetails($packages,$deliveryId){
        $total = 0;
        $failedCount = 0;
        $failedWithFeeCount = 0;
        $total_delivered = 0;
        $deliveryCount = 0;
        foreach($packages as $pkg){
            if($pkg->delivery_id == $deliveryId){
                if(!$pkg->delay_count) {
                    $total += $pkg->driver_total;
                }
                if($pkg->status_id == 9) $total_delivered += $pkg->driver_total;
                if($pkg->status_id == 10) $failedCount +=1;
                if($pkg->status_id == 19) $failedWithFeeCount +=1;
                if($pkg->status_id == 6) $deliveryCount +=1;
                // Log::info(json_encode($pkg));
            }
        }
        return (object)[
            'total' => $total,
            'failed_count' => $failedCount,
            'total_delivered' => $total_delivered,
            'failed_with_fee_count' => $failedWithFeeCount,
            'delivery_count' => $deliveryCount
        ];
    }

    public function getTripPackages(Request $req){
        $trip_id = $req->trip_id;
        $packages = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        // ->where('dp.delay_count',0)
        ->where('dp.delivery_id',$trip_id)
        ->join('users as m','m.id','p.merchant_id')
        ->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','dp.status_id')
        ->selectRaw('p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code,d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.driver_total as delivery_fee,p.taxi_fee,p.product_type,dp.status_id')
        ->get();
        // foreach($packages as $package){

        //     unset($package->status);
        // }
        return ApiResponse::Pagination($packages,$req,__('messages.get_list',['info' => 'Package']));
    }

    public function setPackageStatus(Request $req){
        $user = UserService::getAuthUser();
        $trip_id = $req->trip_id;
        $package_id = $req->package_id;
        $status_id = $req->status_id;
        $failure_notes = $req->failure_notes ?? null;
        $delivery = Delivery::where('is_deleted',0)->find($trip_id);
        $tripPackage = DeliveryPackage::where('package_id',$package_id)->where('delivery_id',$trip_id)->where('delay_count',0)->first();
        if(!$tripPackage) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package',
            'khInfo' => 'កញ្ចប់'
        ]));
        if(!$delivery) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Trip']));
        if(!$status_id || !in_array($status_id,[9,10,19])) return ApiResponse::ValidateFail(__('messages.not_found',['info' => 'Status']));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package has already been delivered'
        ]));
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
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->with('driver')->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        $todayDT = Helper::getDateTime();
        $driverName = $package->driver->user_name;
        $fleetNumber = Delivery::where('id',$trip_id)->take(1)->value('fleet_tracking_number');
        $trackingNotes = $package->tracking_notes."|[$user->id]Admin('.$user->user_name) remove package from Driver($driverName) at ($todayDT) on fleet number $fleetNumber";
        $package->update([
            'driver' => null,
            'tracking_notes' => $trackingNotes
        ]);
        $deliveryPackage->udpate([
            'deleted_uid' => $user->id,
            'is_deleted' => true,
            'notes' => $deliveryPackage->notes."|[$user->id]-Admin('.$user->user_name) remove package from Driver($driverName) at ($todayDT) on fleet number $fleetNumber"
        ]);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Package '.$package->qr_code.' has been removed from Driver'
        ]));
    }

    public function createOrUpdateTrip(Request $req){
        $user = UserService::getAuthUser();
        $today = date('Y-m-d');
        $validate = validator($req->all(),[
            'barcode' => 'required|string',
            'vehicle_type' => 'nullable|exists:vehicle_types,name',
            'driver_id' => 'required|int'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $barcode = $inputs['barcode'];
        $driverId = $inputs['driver_id'];
        $vehicleType = $inputs['vehicle_type'] ?? null;
        $driver = User::where('is_deleted',0)->find($driverId);
        if(!$driver) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Driver'
        ]));
        if(!$vehicleType) $vehicleType = $driver->vehicle_type;
        $todayDelivery = Delivery::whereDate('depart_datetime',$today)->where('company_id',$user->company_id)->where('driver_id',$driverId)->first();
        $packageId = Package::where('qr_code',$barcode)->where('is_deleted',0)->value('id');
        if(!$todayDelivery){
            $QuerylastPackage = DeliveryPackage::where('package_id',$packageId)->where('is_deleted',0);
            $hasFailPackage = $QuerylastPackage->get();
            if(isset($hasFailPackage[0])) $QuerylastPackage->update([
                'delay_count' => 1,
            ]);
            $create = Delivery::create([
                'driver_id' => $driverId,
                'depart_datetime' => now(),
                'package_count' => 1,
                'status_id' => 14, //** On Delivery */
                'warehouse_id' => 1,
                'vehicle_type' => $vehicleType,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id,
                'update_uid' => $user->id,
                'create_uid' => $user->id,
            ]);
            if(!$create) return ApiResponse::Error(__('messages.error',['info' => 'Fail to add fleet']));
            $deliveryId = $create->id;
            Helper::setFleetNumber($user->branch_id,'fleet_code_controls','deliveries',$deliveryId,'fleet_tracking_number');
        }else{
            $deliveryId = $todayDelivery->id;
            $todayDelivery->update([
                'driver_id' => $driverId,
                'delay_count' => $todayDelivery->delay_count + 1,
                'package_count' => $todayDelivery->package_count + 1,
                'update_uid' => $user->id,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id,
            ]);
        }
        DeliveryPackage::create([
            'driver_id' => $driverId,
            'delivery_id' => $deliveryId,
            'package_id' => $packageId,
            'status_id' => 6, // On Delivery
            'update_uid' => $user->id,
            'create_uid' => $user->id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id,
        ]);
        return ApiResponse::JsonResult(null,__('messages.saved'));
    }

    public function getPackageByBarcode(Request $req){
        $user = UserService::getAuthUser();
        $barCode = $req->barcode;
        $package = Package::where('qr_code',$barCode)
        ->where('company_id',$user->company_id)
        ->selectRaw('id,receiver_name,receiver_phone,zone_name,zone_code,taxi_fee,cod,payer,delivery_fee,remarks')
        ->first();
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        return $package;
    }
}
