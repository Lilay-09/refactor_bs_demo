<?php

namespace App\Services\V1;

use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Models\User;
use App\Services\GeneralSettingService;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FleetServiceImpl
{
    // Your service methods go here
    public function createOrUpdateTripService(Request $req,$user,$allowedPkgStatuses=[5]){
        // $today = date('Y-m-d');
        $validate = validator($req->all(),[
            'packages' => 'required|array',
            'depart_datetime' => 'required',
            'vehicle_type' => 'nullable',
            'driver_id' => 'required|int'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $packageIds = $inputs['packages'];
        $driverId = $inputs['driver_id'];
        $vehicleType = $inputs['vehicle_type'] ?? null;
        $driver = User::where('is_deleted',0)->find($driverId);
        if(!$driver) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Driver'
        ]));
        if(!$vehicleType) $vehicleType = $driver->vehicle_type;
        $pendingTrip = Delivery::where(function($q){
            $q->where('is_deleted',0)->where('finished',0);
        })
        ->where('company_id', $user->company_id)
        ->where('driver_id', $driverId)
        ->first();


        $copyFields = [
            'main_zone_name',
            'main_zone_code',
            'receiver_lat',
            'receiver_lng',
            'qr_code',
            'last_submit_uid',
            'driver_display_order',
            'last_remark_user',
            'product_type',
            'returned_uid',
            'price',
            'dim_x',
            'taxi_fee',
            'dim_y',
            'dim_z',
            'remarks',
            'status_id',
            'failure_notes',
            'merchant_id',
            'failed_datetime',
            'pickup_notes',
            'pickup_datetime',
            'order_id',
            // 'return_uid',
            'payer',
            'cod',
            'delivery_fee',
            'tracking_notes',
            'receiver_address',
            'zone_code',
            'zone_name',
            'receiver_phone',
            'returned_datetime',
            'receiver_name',
            'delivery_type',
            'additional_fee',
            'outstanding',
            'assign_uid',
            'actual_kg',
            'billed_kg',
            'delivered_datetime',
            'assign_driver_datetime',
            'merchant_total',
            'driver_total',

            'kick_notes',
            'update_uid',
            'delivery_remarks',
            'extra_charge',
            'kick_reason',
            'kick_uid',
            'is_contact',
            'contact_reason',
            'priority_level',
            'arrive_warehouse_datetime',
            'warehouse_id',

            'cod_khr',
            'cod_usd',
            'driver_cod_usd',
            'driver_cod_khr',
        ];
        $pkgIds = array_column($packageIds,'package_id');
        $allowablePkgs = Package::where('is_deleted',false)->where('outstanding',0)
        ->whereIn('id',$pkgIds)
        ->get()->keyBy('id');

        $bulkInsertData = [];
        $updatePackages = [];
        DB::beginTransaction();
        try{
            if(!$pendingTrip){
                $QuerylastPackage = DeliveryPackage::whereIn('package_id',$packageIds)->where(function($q){
                    $q->where('delay_count',0)->where('is_deleted',0);
                });
                $hasFailPackage = $QuerylastPackage->orderByDesc('id')->get();
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
                if(!$create) return DataResponse::Error(__('messages.error',['info' => 'Fail to add fleet']));
                $deliveryId = $create->id;
                $pendingTrip = Delivery::find($deliveryId);
                Helper::setFleetNumber($user->branch_id,'fleet_code_controls','deliveries',$deliveryId,'fleet_tracking_number');
            }else{
                $deliveryId = $pendingTrip->id;
                $pendingTrip->update([
                    'package_count' => $pendingTrip->package_count + count($packageIds)
                ]);
            }

            foreach ($packageIds as $pkg) {
                $packageId = $pkg['package_id'] ?? null;
                if (!$packageId) {
                    return DataResponse::ValidateFail('Please provide package identity');
                }

                $allowablePkg = $allowablePkgs[$packageId] ?? null;
                if (!$allowablePkg) {
                    return DataResponse::ValidateFail(__('messages.not_found', ['info' => 'Package']));
                }

                if (!in_array($allowablePkg->status_id, $allowedPkgStatuses)) {
                    $requiredStatus = $allowedPkgStatuses[0] === 5
                        ? 'Package must be at warehouse'
                        : 'Package must be on delivery';
                    return DataResponse::ValidateFail(__('messages.info', ['info' => $requiredStatus]));
                }

                $existing = DeliveryPackage::where(function ($q) {
                    $q->where('delay_count', 0)->where('is_deleted', 0);
                })->where('package_id', $packageId)->first();

                // Skip if already exists and status doesn't allow re-adding
                if ($existing && !in_array(6, $allowedPkgStatuses)) {
                    continue;
                }

                $copiedData = $allowablePkg->only($copyFields);

                $merged = array_merge($copiedData, [
                    'package_id'  => $packageId,
                    'notes'       => 'Admin add package to trip',
                    'driver_id'   => $driverId,
                    'delivery_id' => $deliveryId,
                    'status_id'   => 6,
                    'update_uid'  => $user->id,
                    'create_uid'  => $user->id,
                    'branch_id'   => $user->branch_id,
                    'company_id'  => $user->company_id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $bulkInsertData[] = $merged;

                $updatePackages[] = $allowablePkg;
            }

            // Bulk insert delivery packages
            if (!empty($bulkInsertData)) {
                DeliveryPackage::insert($bulkInsertData);
            }

            // Update each original package
            foreach ($updatePackages as $pkg) {
                $pkg->update([
                    'driver_id'      => $driverId,
                    'status_id'      => 6,
                    'tracking_notes' => "[$user->id] Admin add package to trip (" . Helper::getDateTime() . ")",
                ]);
            }

            GeneralSettingService::updateTripStatus($deliveryId,$user);
            // return Delivery::orderByDesc('id')->get();
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error',['info' => 'Failed to save trip']));
        }
    }
}
