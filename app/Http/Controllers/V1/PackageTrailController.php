<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\CloudMessagingService;
use App\Services\CompanyProfileService;
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
        $search = $req->search??null;
        $warehouse_id = $req->warehouse_id ?? null;
        $statusId = $req->status_id??null;
        $query = Package::where('is_deleted',0)
        ->with(['status','merchant','driver'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->whereNotIn('status_id',[9])
        ->selectRaw('merchant_id,order_id,id,taxi_fee,delivery_type,qr_code,price,driver_id,product_type,dim_z,dim_x,dim_y,status_id,failure_notes,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,delivered_datetime,assign_driver_datetime,arrive_warehouse_datetime,driver_total,merchant_total,billed_kg,actual_kg')
        ->orderByRaw('(status_id = ?) DESC', [5])
        ->orderBy('arrive_warehouse_datetime','desc');
        if((int)$warehouse_id){
            $query->whereHas('order',function($q) use($warehouse_id){
                $q->where('warehouse_id',$warehouse_id);
            });
        }
        if((int)$statusId){
            $query->where('status_id',$statusId);
        }
        if($search){
            $query->where('qr_code',$search);
        }
        $packages = $query->get();
        foreach($packages as $pkg){
            $cod = $pkg->cod;
            $pkg->driver_name = $pkg->driver?->user_name;
            $pkg->merhcant_name = $pkg->merchant?->user_name;
            $pkg->merchant_phone = $pkg->merchant?->phone;
            $pkg->cod = $cod == true ? 1:0;
            $pkg->status_code = $pkg->status->name;
            $pkg->total = PickupCenterService::getTotal($cod,$pkg->payer,$pkg->price,$pkg->delivery_fee,$pkg->additional_fee,$pkg->excharge_fee);
            $pkg->warehouse_timeago = Helper::timeAgo($pkg->arrive_warehouse_datetime,false);
            unset($pkg->status,$pkg->merchant,$pkg->driver);
        }
        return ApiResponse::Pagination($packages,$req,__('messages.get_list',['info'=>'Package']));
    }

    public function getOnePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('is_deleted',0)
        ->with(['status','driver'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->selectRaw('id,taxi_fee,actual_kg,billed_kg,extra_charge,delivery_type,qr_code,price,driver_id,product_type,dim_z,dim_x,dim_y,status_id,failure_notes,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,delivered_datetime,assign_driver_datetime,arrive_warehouse_datetime,driver_total,merchant_total,driver_id,remarks,billed_kg,actual_kg')
        ->find($id);
        if(!$package) return ApiResponse::NotFound();
        $package->status_code = $package->status->name;
        $cod = $package->cod;
        $package->cod = $cod == false ? 0 : 1;
        $driver = $package->driver;
        if($driver){
            $package->driver_name = $driver->user_name;
        }
        $package->base_fee = $package->delivery_fee;
        $package->delivery_fee = $package->delivery_fee + $package->taxi + ($cod ? $package->price : 0);
        $package->warehouse_timeago = Helper::timeAgo($package->arrive_warehouse_datetime,false);
        unset($package->status,$package->driver);
        return ApiResponse::JsonResult($package);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if($package->status_id == 13) return ApiResponse::Forbidden(__('messages.no_access',['info' => 'This package is already assigned to driver']));
        if($package->status_id == 14) return ApiResponse::Forbidden(__('messages.no_access',['info' => 'This package is on delivery']));
        $pkupService = new PickupCenterService();
        $req->merge(['merchant_id' => $package->merchant_id]);
        $validate = $pkupService->packageValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['update_uid'] = $user->id;
        $price = $inputs['price'] ?? 0;
        $inputs['price'] = $price;
        $actualKg = $inputs['actual_kg'] ?? 0;
        $billedKg = $inputs['billed_kg'] ?? 0;
        $inputs['actual_kg'] = $actualKg;
        $payer = $inputs['payer'];
        $inputs['billed_kg'] = $actualKg;
        $inputs['status_id'] = 5; //** add warehouse */
        $zoneCode = $inputs['zone_code'];
        $extra_charge = $inputs['extra_charge'] ?? 0;
        $calPrice = GeneralSettingService::calculatePackageFee($zoneCode,$price,$billedKg,$actualKg,$payer,$inputs['cod'],$extra_charge,$user);
        if($calPrice->error) return $calPrice;
        $inputs['driver_total'] = $calPrice->driver_total;
        $inputs['merchant_total'] = $calPrice->merchant_total;
        $inputs['delivery_fee'] = $calPrice->delivery_fee;
        $package->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function deletePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        // if(in_array($package->status_id,[]))
        // $package->update([
        //     'is_deleted' => true,
        //     'deleted_datetime' => now(),
        //     'deleted_uid' => $user->id
        // ]);
        return ApiResponse::JsonResult(null,__('messages.info',['info' => 'Deleted']));
    }

    public function returnPackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        if(!in_array($package->status_id,[5,10,19])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only failed package can be returned'
        ]));
        $package->update([
            'status_id' => 11, // returned
            'returned_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,__('messages.info',['info' => 'Returned']));
    }

    public function getPrintInfo(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = $package = Package::where('is_deleted',0)
        ->with(['driver','merchant'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->selectRaw('merchant_id,id,taxi_fee,actual_kg,billed_kg,extra_charge,delivery_type,qr_code,price,driver_id,product_type,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,driver_total,merchant_total,driver_id,remarks')
        ->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $driver = $package->driver;
        if($driver){
            $package->driver_name = $driver->user_name;
        }
        $package->merchant_name = $package->merchant->user_name;
        $package->merchant_phone = $package->merchant->phone;
        $package->base_fee = $package->delivery_fee;
        $package->delivery_fee = $package->delivery_fee + $package->taxi + ($package->cod ? $package->price : 0);
        unset($package->status,$package->driver,$package->merchant);
        $obj = (object)[
            'company_info' => CompanyProfileService::profileInfo($user),
            'package' => $package,
        ];
        return ApiResponse::JsonResult($obj,__('messages.info',['info' => 'Print Information']));
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
        if($pacakge->status_id == 9) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already delivered']));
        if($pacakge->status_id == 19) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already marked as failed with fee']));
        if($pacakge->status_id == 11) return ApiResponse::Duplicated(__('messages.error',['info' => 'This package is already returned']));
        if($pacakge->driver_id){
            $deliveryPackage = DeliveryPackage::where('package_id',$id)->where('is_deleted',0)->where('delay_count',0)->first();
            if($deliveryPackage){
                if($deliveryPackage->status_id !== 10 && !in_array($pacakge->status_id,[5,10,19]) ) return ApiResponse::Duplicated(__('messages.has already assigned',['info' => 'Package']));
            }
        }
        DB::beginTransaction();
        try{
            $pacakge->update([
                'driver_id' => $driver_id,
                'status_id' => 6, // On Delivery
                'assign_driver_datetime' => now(),
            ]);
            $trip = $this->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes,6);
            if($trip->error) return ApiResponse::flex($trip);
            $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$driver_id);
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $driver_id,
                'title' => 'Assigned Package',
                'body' => 'You have been assigned to deliver the package('.$pacakge->qr_code.').'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.assigned',['info' => '']));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return ApiResponse::Error(__('messages.error',['info' => 'Fail to assign driver']));
        }
    }

    public function createOrUpdateTrip($driverId,$packageId,$vehicleType,$user,$notes,$statusId){
        $today = date('Y-m-d');
        $isNewPkg = true;
        $pendingTrip = Delivery::where(function ($query) use ($today) {
            $query->whereDate('depart_datetime', $today)
                ->orWhere(function ($q) {
                    $q->where('finished', 0)
                    ->orWhere('is_completed', 0);
                });
        })->where('company_id', $user->company_id)
        ->where('driver_id', $driverId)
        ->first();
        if($pendingTrip) Log::error('found');

        // $pendingTrip = Delivery::whereDate('depart_datetime',$today)->where('company_id',$user->company_id)->where('driver_id',$driverId)->first();
        // if(!$pendingTrip) $pendingTrip = Delivery::where(function($q){
        //     $q->where('finished',0)
        //     ->orWhere('is_completed',0);
        // })->where('company_id',$user->company_id)->where('driver_id',$driverId)->first();
        if(!$pendingTrip){
            $QuerylastPackage = DeliveryPackage::where('package_id',$packageId)->where('delay_count',0)->where('is_deleted',0);
            $hasFailPackage = $QuerylastPackage->orderByDesc('id')->get();
            if(isset($hasFailPackage[0])) $QuerylastPackage->update([
                'delay_count' => 1,
            ]);
            // Log::error(json_encode($hasFailPackage));
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
            $deliveryId = $pendingTrip->id;
            $newPackageCount = $pendingTrip->package_count;
            $delay = 1;
            $existsPkg = DeliveryPackage::where('package_id',$packageId)->where('delivery_id',$deliveryId)->where('delay_count',0)->where('is_deleted',0)
            ->first();
            if($existsPkg) {
                $isNewPkg = false;
                $delay = 0;
                if($statusId){
                    $existsPkg->update([
                        'status_id' => $statusId
                    ]);
                }
            }else {
                $newPackageCount +=1;
            }
            $pendingTrip->update([
                'driver_id' => $driverId,
                'delay_count' => $delay,
                'status_id' => 14,
                'package_count' => $newPackageCount,
                'update_uid' => $user->id,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id,
            ]);
            // ->update([
                // 'driver_id' => $driverId,
                // 'delay_count' => $delay,
                // 'status_id' => 14,
                // 'package_count' => $newPackageCount,
                // 'update_uid' => $user->id,
                // 'branch_id' => $user->branch_id,
                // 'company_id' => $user->company_id,
            // ]);

        }
        //** add delivery tracking */
        if($isNewPkg) {
            $dPackage = DeliveryPackage::create([
                'notes' => $notes,
                'driver_id' => $driverId,
                'delivery_id' => $deliveryId,
                'package_id' => $packageId,
                'status_id' => 6, // On Delivery
                'update_uid' => $user->id,
                'create_uid' => $user->id,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id,
            ]);
            if(!$dPackage) return DataResponse::Error(__('messages.error',['info' => 'Fail to assign package']));
        }


        GeneralSettingService::updateTripStatus($deliveryId,$user);


        return DataResponse::JsonResult(null);
    }

}
