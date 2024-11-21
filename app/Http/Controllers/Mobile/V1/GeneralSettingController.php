<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\PackageTrailController;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use DB;
use Exception;
use Illuminate\Http\Request;
use Log;


class GeneralSettingController extends Controller
{
    //
    public function getOptionsDriverFailRemarks(){
        return ApiResponse::JsonResult(GeneralSettingService::optionsDriverRemarks('failure'));
    }

    public function getMerchantFormBooking(){
        $user = UserService::getAuthUser('merchant');
        $obj = (object)[
            'vehicle_types' => GeneralSettingService::optionsVehicleType($user),
            'product_types' => GeneralSettingService::optionsProductType($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormOptionsHistory(){
        $user = UserService::getAuthUser('driver');
        $bonusRow = collect([['id' => 0, 'name' => 'All']]);
        $results = GeneralSettingService::optionsTrackingStatus($user,[],[9,10,11],'delivery');
        // Merge the bonus row with the fetched results
        $results = $bonusRow->merge($results);
        $obj = (object)[
            'payment_statuses' => GeneralSettingService::paymentStatus(),
            'statuses' =>$results
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormBooking(){
        $user = UserService::getAuthUser('driver');
        $obj = (object)[
            'vehicle_types' => GeneralSettingService::optionsVehicleType($user),
            'merchants' => GeneralSettingService::optionsMerchant($user),
            'product_types' => GeneralSettingService::optionsProductType($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function scanPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $notes = $req->notes;
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->first();
        if(!$package) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        $id = $package->id;
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already delivered']));
        if($package->status_id == 19) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already marked as failed with fee']));
        if($package->status_id == 11) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already returned']));
        if($package->driver_id){
            $deliveryPackage = DeliveryPackage::where('package_id',$package->id)->where('is_deleted',0)->where('delay_count')->first();
            if($deliveryPackage){
                if($deliveryPackage->status_id !== 10) return ApiResponse::Duplicated(__('messages.has already assigned',['info' => 'Package']));
            }
        }
        $driver_id = $package->driver_id;
        DB::beginTransaction();
        try{
            $package->update([
                'driver_id' => $driver_id,
                'status_id' => 6, // On Delivery
                'tracking_notes' => $package->tracking_notes.'|Driver scan delivery '.date('d-M-Y h:i:s A'),
                'assign_driver_datetime' => now(),
            ]);
            $pckTl = new PackageTrailController();
            $validDriver = GeneralSettingService::getDriverById($driver_id);
            $trip = $pckTl->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes);
            if($trip->error) return ApiResponse::flex($trip);
            // DB::commit();
            return ApiResponse::JsonResult(null,__('messages.assigned',['info' => '']));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return ApiResponse::Error(__('messages.error',['info' => 'Fail to assign driver']));
        }
        // $pckTl = new PackageTrailController();
        // $driver_id = $package->driver_id;
        // $validDriver = GeneralSettingService::getDriverById($driver_id);
        // $trip = $pckTl->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes);
        // if($trip->error) return ApiResponse::flex($trip);
        // $package->update([
        //     ''
        // ]);
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::optionsZone($user));
    }

    public function getZonePrice(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->zone_id;
        return ApiResponse::JsonResult(GeneralSettingService::priceByZone($id,$user));
    }
}
