<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\UserService;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;

class PackageTrailController extends Controller
{
    //
    public function getPackages(Request $req){
        $user = UserService::getAuthUser();
        $query = Package::where('is_deleted',0)
        ->with(['status'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->selectRaw('id,qr_code,driver_id,product_type,dim_z,dim_x,dim_y,status_id,failure_notes,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,delivered_datetime,assign_driver_datetime,arrive_warehouse_datetime,driver_total,merchant_total');
        $packages = $query->get();
        foreach($packages as $pkg){
            $pkg->status_code = $pkg->status->name;
            $pkg->warehouse_timeago = Helper::timeAgo($pkg->arrive_warehouse_datetime);
            unset($pkg->status);
        }
        return ApiResponse::Pagination($packages,$req,__('messages.get_list',['info'=>'Package']));
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('status_id',5)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if($package->status_id == 13) return ApiResponse::Forbidden(__('messages.no_access',['info' => 'This package has already assigned to driver']));
        $pkupService = new PickupCenterService();
        $validate = $pkupService->packageValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['update_uid'] = $user->id;
        $price = $inputs['price'] ?? 0;
        $inputs['cod'] = 0;
        $inputs['price'] = $price;
        $actualKg = $inputs['actual_kg'] ?? 0;
        $billedKg = $inputs['billed_kg'] ?? 0;
        $inputs['actual_kg'] = $actualKg;
        $payer = $inputs['payer'];
        $inputs['billed_kg'] = $actualKg;
        if($price > 0) $inputs['cod'] = 1;
        $inputs['status_id'] = 5; //** add warehouse */
        $zoneCode = $inputs['zone_code'];
        $calPrice = GeneralSettingService::calculatePackageFee($zoneCode,$price,$billedKg,$actualKg,$payer);
        if($calPrice->error) return $calPrice;
        $inputs['driver_total'] = $calPrice->driver_total;
        $inputs['merchant_total'] = $calPrice->merchant_total;
        $inputs['delivery_fee'] = $calPrice->delivery_fee;
        $package->update($inputs);
        return ApiResponse::JsonResult(null,false,__('messages.updated'));
    }

    public function assignDriver(Request $req){
        $user  = UserService::getAuthUser();
        $id = $req->id;
        $driver_id = $req->driver_id;
        $notes = $req->notes;
        $validDriver = GeneralSettingService::getDriverById($driver_id);
        if(!$validDriver) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        $pacakge = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$pacakge) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if($pacakge->driver_id){
            $deliveryPackage = DeliveryPackage::where('package_id',$id)->where('is_deleted',0)->first();
            if($deliveryPackage->status_id !== 10) return ApiResponse::Duplicated(__('messages.has already assigned',['info' => 'Package']));
        }
        DB::beginTransaction();
        try{
            $pacakge->update([
                'driver_id' => $driver_id,
                'status_id' => 6, // On Delivery
                'assign_driver_datetime' => now(),
            ]);
            $trip = $this->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes);
            if($trip->error) return ApiResponse::flex($trip);
            DB::commit();
            // return DeliveryPackage::get();
            // return $pacakge->with('status')->find($id);
            return ApiResponse::JsonResult(null,false,__('messages.assigned',['info' => '']));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return ApiResponse::Error(__('messages.error',['info' => 'Fail to assign driver']));
        }
    }

    public function createOrUpdateTrip($driverId,$packageId,$vehicleType,$user,$notes){
        $today = date('Y-m-d');
        $todayDelivery = Delivery::whereDate('depart_datetime',$today)->where('driver_id',$driverId)->first();
        if(!$todayDelivery){
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
            if(!$create) return DataResponse::Error(__('messages.error',['info' => 'Fail to add fleet']));
            $deliveryId = $create->id;
            Helper::setFleetNumber($user->branch_id,'fleet_code_controls','deliveries',$deliveryId,'fleet_tracking_number');
        }else{
            $deliveryId = $todayDelivery->id;
            $todayDelivery->update([
                'package_count' => $todayDelivery->package_count + 1,
                'update_uid' => $user->id,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id,
            ]);
        }

        //** add delivery tracking */
        $dPackage = DeliveryPackage::create([
            'notes' => $notes,
            'delivery_id' => $deliveryId,
            'package_id' => $packageId,
            'status_id' => 6, // On Delivery
            'update_uid' => $user->id,
            'create_uid' => $user->id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id,
        ]);
        if(!$dPackage) return DataResponse::Error(__('messages.error',['info' => 'Fail to assign package']));
        return DataResponse::JsonResult(null);
    }
}
