<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\PackageTrailController;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\CloudMessagingService;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Cache;
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
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->first();
        if(!$package && is_numeric($item_ref)) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.arrived',[
            'info' => 'Package'
        ]));
        return ApiResponse::JsonResult([
            'is_contact' => $package->is_contact,
            'diff_driver' => $user->id != $package->driver_id,
            'is_delivery' => $package->status_id == 6
        ]);
        // if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        // $id = $package->id;
        // if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already delivered']));
        // if($package->status_id == 19) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already marked as failed with fee']));
        // if($package->status_id == 11) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already returned']));
        // if($package->driver_id){
        //     $deliveryPackage = DeliveryPackage::where('package_id',$package->id)->where('is_deleted',0)->where('delay_count')->first();
        //     if($deliveryPackage){
        //         if($deliveryPackage->status_id !== 10) return ApiResponse::Duplicated(__('messages.has already assigned',['info' => 'Package']));
        //     }
        // }
        // $driver_id = $package->driver_id;
        // DB::beginTransaction();
        // try{
        //     $package->update([
        //         'driver_id' => $driver_id,
        //         'status_id' => 6, // On Delivery
        //         'tracking_notes' => $package->tracking_notes.'|Driver scan delivery '.date('d-M-Y h:i:s A'),
        //         'assign_driver_datetime' => now(),
        //     ]);
        //     $pckTl = new PackageTrailController();
        //     $validDriver = GeneralSettingService::getDriverById($driver_id);
        //     $trip = $pckTl->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes);
        //     if($trip->error) return ApiResponse::flex($trip);
        //     // DB::commit();
        //     return ApiResponse::JsonResult(null,__('messages.assigned',['info' => '']));
        // }catch(Exception $e){
        //     DB::rollBack();
        //     Log::error($e->getMessage());
        //     Log::error($e->getTraceAsString());
        //     return ApiResponse::Error(__('messages.error',['info' => 'Fail to assign driver']));
        // }
        // $pckTl = new PackageTrailController();
        // $driver_id = $package->driver_id;
        // $validDriver = GeneralSettingService::getDriverById($driver_id);
        // $trip = $pckTl->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes);
        // if($trip->error) return ApiResponse::flex($trip);
        // $package->update([
        //     ''
        // ]);
    }

    public function scanPackageChooseAction(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $changeDriver = $req->change_driver;
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->with('driver')->first();
        if(!$package) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.arrived',[
            'info' => 'Package'
        ]));
        if($changeDriver){
            $requester = $user->info->phone."($user->user_name)";
            $cms = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$package->driver_id);
            Cache::set($topics->private,(object)[
                'requester' => $requester,
                'requester_id' => $user->id,
            ]);
            $notifReq = new Request([
                'topic' => $topics->private,
                'title' => 'Change Driver',
                'body' => 'safsdfsd',
                'data' => [
                    'action' => 'change-driver',
                    'requester' => $requester
                ]
            ]);
            $cms->sendNotificationByTopic($notifReq);
        }
    }

    public function confirmOrCancelSwapPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->with('driver')->first();
        if(!$package) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.arrived',[
            'info' => 'Package'
        ]));

        $selfTopic = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$user->id)->private;
        $cache = Cache::get($selfTopic);
        $requester = $cache?->requester;
        $requester_id = $cache?->requester_id;
        Cache::forget($selfTopic);
        if($requester_id == $package->driver_id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to confirm self request'
        ]));
        $requesterTopic = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$requester_id);
        $cms = new CloudMessagingService();
        $notifReq = new Request([
            'topic' => $requesterTopic->private,
            'title' => 'Confirm',
            'body' => $user->user_name.' has confirmed your request',
            'data' => [
                'action' => 'change-driver',
                'sender' => $user->user_name
            ]
        ]);
        $cms->sendNotificationByTopic($notifReq);
        return $cache;
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
