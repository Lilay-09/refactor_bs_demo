<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\FleetManagementController;
use App\Http\Controllers\V1\PackageTrailController;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Notification;
use App\Models\Package;
use App\Services\CloudMessagingService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\UserService;
use Cache;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;


class GeneralSettingController extends Controller
{
    //
    public function getOptionsDriverFailRemarks(Request $req){
        return ApiResponse::JsonResult(GeneralSettingService::optionsDriverRemarks('failure',$req->isFailWithFee));
    }

    public function getMerchantFormBooking(){
        $user = UserService::getAuthUser('merchant');
        $obj = (object)[
            'vehicle_types' => GeneralSettingService::optionsVehicleType($user),
            'product_types' => GeneralSettingService::optionsProductType($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormOptionsHistory(Request $req){
        $user = UserService::getAuthUser('driver');
        $lang = $req->lang;
        $stCode = $lang != 'en' ? 'ទាំងអស់':'All';
        $bonusRow = collect([['id' => 0, 'name' => $stCode]]);
        $results = GeneralSettingService::optionsTrackingStatus($user,[],[9,10,11,19],'delivery',null,$req->lang);
        // Merge the bonus row with the fetched results
        $results = $bonusRow->merge($results);
        foreach($results as $st){
            if($lang != 'en'){
                $st['name'] = isset(GeneralSettingService::$statusCodeTrans[$st['id']]) ? GeneralSettingService::$statusCodeTrans[$st['id']] : null;
            }
        }
        $obj = (object)[
            'payment_statuses' => GeneralSettingService::paymentStatus($lang),
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
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->selectRaw('status_id,driver_id,merchant_id,is_contact')->first();
        if(!$package && is_numeric($item_ref)) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package is already delivered.',
            'khInfo' => 'កញ្ចប់បានដឹកហើយ'
        ]));
        if($package->status_id == 19) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package is already failed with fee.',
            'khInfo' => 'កញ្ចប់ធ្លាប់បរាជ័យគិតសេវា'
        ]));
        $diffDriver = $package->driver_id ? ($user->id != $package->driver_id) : false;
        $isOnDelivery = $package->status_id == 6;
        $data = null;
        if(!$diffDriver && $isOnDelivery)
            $data = Package::where('qr_code',$item_ref)
            ->with(['status:id,name','merchant:id,user_name'])
            ->selectRaw('receiver_address,merchant_id,id,qr_code,status_id,assign_driver_datetime,receiver_phone,receiver_name,product_type,cod,zone_name,zone_code,price,delivery_fee,driver_total as total,taxi_fee,additional_fee,extra_charge,payer')
            // ->selectRaw('p.delivered_datetime,p.failed_datetime,p.assign_driver_datetime,p.merchant_id,p.qr_code,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code
            // ,d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.driver_total as delivery_fee,p.taxi_fee,p.product_type,dp.status_id')
            ->first();
            if($data){
                $data->merchant_name = $data->merchant?->user_name;
                $data->cod = $data->cod ? 'Yes' : 'No';
                $data->status_code = $data->status->name;
                $data->fee = PickupCenterService::getFees($data->payer,$data->delivery_fee,$data->extra_charge,$data->taxi_fee);
                unset($data->status,$data->merchant);
            }
        return ApiResponse::JsonResult([
            'is_contact' => $package->is_contact,
            'diff_driver' => $diffDriver,
            'is_delivery' => $isOnDelivery,
            'info' => $data
        ]);
    }

    public static function markReadNotification(Request $req,$user){
        $id = $req->id ?? null;
        $msg = 'Mark read all';
        $notification = Notification::where('user_id',$user->id)->where('is_read',0)->selectRaw('id,is_read,title,body');
        if($id) {
            $msg = 'Read';
            $notification->find($id);
            if(!$notification) return DataResponse::Duplicated('Already marked');
            $notification->update([
                'is_read' => true,
                'read_datetime' => now()
            ]);
        }else{
            $notification->update([
                'is_read' => true,
                'read_datetime' => now()
            ]);
        }

        return DataResponse::JsonResult(null,$msg);
    }

    public function scanPackageChooseAction(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $changeDriver = $req->change_driver;
        $markContact = $req->mark_contact ?? 0;
        $confirmDelivery = $req->confirm_delivery ?? 0;
        $cms = new CloudMessagingService();
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->with('driver')->first();
        if(!$package) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.arrived',[
            'info' => 'Package'
        ]));
        // $statusId = $package->status_id;
        $driver = $package->driver;
        $updateArr = [];
        if($confirmDelivery){
            if($package->status_id == 6) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'Package is already on delivery'
            ]));
            if($changeDriver) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'You cannot change the driver and confirm delivery the same time!',
            ]));
            $updateArr['status_id'] = 6;
            $updateArr['driver_id'] = $user->id;
            $updateArr['assign_driver_datetime'] = now();
            $notes = $package->tracking_notes."|[$user->id]Driver ($user->user_name) scan on delivery (".Helper::getDateTime()."";
            // $notifRequpdateArr['tracking_notes'] = $notes;
            $pckTl = new PackageTrailController();
            // DB::beginTransaction();
            // try{
                $trip = $pckTl->createOrUpdateTrip($user->id,$package->id,$package->drivervehicle_type,$user,$notes,6,'assign');
                if($trip->error) return ApiResponse::flex($trip);
                // DB::commit();
            // }catch(Exception $e){
            //     DB::rollBack();
            // }
        }else $confirmDelivery = ($package->status_id == 6);
        if($markContact && !$confirmDelivery) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You cannot mark contact on package which is not on delivery'
        ])); else {
            $updateArr['is_contact'] = true;
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$package->merchant_id);
            $notifReq = new Request([
                'topic' => $topics->private,
                'title' => 'Contact',
                'body' => 'Driver has contacted your customer ('.$package->receiver_phone.')',
            ]);

            $cms->sendNotificationByTopic($notifReq,$user);
        }

        if($changeDriver){
            if($user->id == $package->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'It seems like you tried to confirm delivery package again'
                // 'info' => 'This package is already marked as out for delivery. Please check the delivery status before proceeding.'
            ]));
            $requester = $user->info->phone."($user->user_name)";
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$package->driver_id);
            Cache::set($topics->private,(object)[
                'requester' => $requester,
                'requester_id' => $user->id,
            ],250);
            $notifReq = new Request([
                'topic' => $topics->private,
                'title' => 'Change Driver',
                'body' => "$requester request change package ",
                'data' => [
                    'action' => 'change-driver',
                    'requester' => $requester,
                    'barcode' => $item_ref,
                    "en_message" => "$requester request change package ",//$requester." request swap the package",
                    "km_message" => $requester." ស្នើរសុំកញ្ចប់"
                ]
            ]);
            // var_dump($requester,$topics->private);
            $cms->sendNotificationByTopic($notifReq,$user);
            $driverName = $driver->user_name;
            $updateArr['tracking_notes'] = $package->tracking_notes."|[$user->id]Driver ($user->user_name) ask [$package->driver_id]Driver $driverName to change driver";
        }
        // if(empty($updateArr)) return ApiResponse::JsonResult(null,__('messages.updated'));
        $package->update($updateArr);
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function confirmOrCancelSwapPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $confirm = $req->confirm;
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->with('driver')->first();
        if(!$package) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.arrived',[
            'info' => 'Package'
        ]));

        $selfTopic = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$user->id)->private;
        $cache = Cache::get($selfTopic);
        $requester = $cache?->requester;
        // return $cache;
        $requester_id = $cache?->requester_id;
        Cache::forget($selfTopic);
        if(!$cache) return ApiResponse::NotFound();
        if($requester_id == $package->driver_id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to confirm self request'
        ]));
        $requesterTopic = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$requester_id);
        $cms = new CloudMessagingService();
        $notifTitle = 'Confirm';
        $notifBody = $user->user_name.' has confirmed your request';
        if(!$confirm){
            $notifTitle = 'Cancelled';
            $notifBody = 'Your request has been denied';
        }else{
            // $today = now();
            // return $requester_id;
            $fleet = new FleetManagementController();
            $fleetArr = new Request([
                'packages' => [
                    [
                        'package_id' => $package->id
                    ]
                ],
                'depart_datetime' => now(),
                'driver_id' => $requester_id
            ]);
            // return $requester_id;
            // DB::beginTransaction();
            // try{
                $createOrUpdate = $fleet->createOrUpdateTripService($fleetArr,$user,[6]);
                if($createOrUpdate->error) return ApiResponse::flex($createOrUpdate);
            //     DB::commit();
            // }catch(Exception $e){
            //     DB::rollBack();
            // }
            $selfTrip = Delivery::where('driver_id',$package->driver_id)->where(function($q){
                $q->where('is_deleted',0)->where('finished',0);
            })->selectRaw('package_count,id')->orderByDesc('id')->first();
            //** remove self pacakge */
            $selfTrip->update([
                'package_count' => $selfTrip->package_count - 1
            ]);
            DeliveryPackage::where('delivery_id',$selfTrip->id)
            ->where('package_id',$package->id)
            ->update([
                'is_deleted' => true,
                'deleted_uid' => $user->id,
                'has_swap' => true,
                'delay_count' => 0,
                'deleted_datetime' => now(),
                'notes' => DB::raw('notes || \'| confirm to change swap package\'')
            ]);
            $currTrip = Delivery::where('id',$selfTrip->id)->selectRaw('id,package_count,tracking_notes,delivered_count,failed_count,is_deleted,deleted_datetime,deleted_uid,finished,finished_datetime,status_id')->first();
            if($currTrip->package_count == 0) {
                $currTrip->update([
                    'is_deleted' => 1,
                    'deleted_datetime' => now(),
                    'deleted_uid' => $user->id,
                    'tracking_notes' => $currTrip->tracking_notes.'|Trip delete because driver has swapped package to '.$requester.'('.Helper::getDateTime().')'
                ]);
            }else if($currTrip->package_count == ($currTrip->delivered_count + $currTrip->failed_count)){
                $currTrip->update([
                    'finished' => 1,
                    'status_id' => 16,
                    'finished_datetime' => now(),
                ]);
            }
            $onDeliveryCount = $currTrip->package_count - ($currTrip->delivered_count + $currTrip->failed_count);
            if($onDeliveryCount == 0 && $currTrip->package_count > 0) {
                $currTrip->update([
                    'finished' => 1,
                    'is_completed' => 1,
                    'status_id' => 16
                ]);
                // Log::error(json_encode(Delivery::select('status_id','is_completed','finished')->find($trip_id)));
            }

            $package->update([
                'driver_id' => $requester_id,
                'status_id' => 6,
                'tracking_notes' => $package->tracking_notes.'|Package tranferred from ['.$package->driver_id.']'.$package->driver->user_name.' to ['.$requester_id.']'.$requester
            ]);
        }
        // return DeliveryPackage::where('package_id',29)->where('is_deleted',0)->get();
        $notifReq = new Request([
            'topic' => $requesterTopic->private,
            'title' => $notifTitle,
            'body' => $notifBody,
            'data' => [
                'action' => 'change-driver',
                'sender' => $user->user_name,
            ]
        ]);
        $cms->sendNotificationByTopic($notifReq,$user);
        return ApiResponse::JsonResult(null,$confirm ? 'Success':'Declined change driver');
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::optionsZone($user));
    }

    public function getZonePrice(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->zone_id;
        return ApiResponse::JsonResult(GeneralSettingService::priceByZone($id,$user,$req->merchant_id));
    }
}
