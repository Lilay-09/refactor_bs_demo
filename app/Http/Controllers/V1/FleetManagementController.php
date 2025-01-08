<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Models\User;
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

class FleetManagementController extends Controller
{
    //
    public function getTrips(Request $req){
        $user = UserService::getAuthUser();
        $search = $req->search ?? null;
        $driverId = $req->driver_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $statusId = $req->status_id;
        $packages = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->selectRaw('p.id as package_id,dp.delivery_id,dp.delay_count,dp.status_id,p.driver_total')
        ->where('dp.is_deleted',0)
        // ->where('dp.delay_count',0)
        // ->groupBy('p.id','dp.delivery_id','dp.delay_count')
        ->get();
        $query = Delivery::with(['status','driver'])->where('is_deleted',0)->where('company_id',$user->company_id)
        ->orderBy('status_id')
        ->orderByDesc('id')

        ->selectRaw('id,fleet_tracking_number,status_id,driver_id,depart_datetime,remarks,package_count,delivered_count,failed_count,warehouse_id,vehicle_type,driver_id,is_completed,finished');
        if($search){
            $query->whereHas('packages.package',function($q) use ($search){
                $q->where('qr_code',$search);
            })->orWhere('fleet_tracking_number',$search)->orWhereHas('driver',function($q) use ($search){
                $q->where('user_name','ilike','%'.$search.'%')->orWhere('name_km','ilike','%'.$search.'%');
            });
        }
        if($driverId){
            $query->where('driver_id',$driverId);
        }
        if($statusId){
            $query->where('status_id',$statusId);
        }
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $query->where(function ($q) use ($startDate,$endDate){
                $q->whereRaw('depart_datetime::DATE >= ? AND depart_datetime::DATE <= ?', [$startDate, $endDate]);
            });
        }else{
            $query->whereDate('depart_datetime',now());
        }
        $deliveries = $query->get();
        foreach($deliveries as $delivery){
            $delivery->status_code = $delivery->status->name;
            $delivery->driver_name = $delivery->driver->user_name;
            $delivery->driver_phone = $delivery->driver->phone;
            $details = $this->getTripDetails($packages,$delivery->id);
            $delivery->total = $details->total;
            $delivery->total_delivered = number_format($details->total_delivered + $details->total_failed_with_fee,2);
            $delivery->failed_count = $details->failed_count;
            $delivery->delivery_count = $details->delivery_count;
            $delivery->failed_with_fee_count = $details->failed_with_fee_count;
            $delivery->depart_time = Helper::formatCustomDateTime($delivery->depart_datetime,'h:i:s');
            $delivery->depart_date = Helper::formatCustomDateTime($delivery->depart_datetime,'d-M-Y',false);
            unset($delivery->status,$delivery->driver);
        }
        return ApiResponse::Pagination($deliveries,$req);
    }

    public function updateTripCount(Request $req){
        $updateArr = [];
        if($req->package_count>=0){
            $updateArr['package_count'] = $req->package_count;
        }
        if($req->delivered_count>=0){
            $updateArr['delivered_count'] = $req->delivered_count;
        }
        Delivery::where('fleet_tracking_number',$req->code)->where('is_deleted',0)->update($updateArr);
    }

    public function getTripDetails($packages,$deliveryId){
        $total = 0;
        $failedCount = 0;
        $failedWithFeeCount = 0;
        $total_delivered = 0;
        $totalFailedWithFee = 0;
        $deliveryCount = 0;
        foreach($packages as $pkg){
            if($pkg->delivery_id == $deliveryId){
                if(!$pkg->delay_count) {
                    $total += $pkg->driver_total;
                }
                if($pkg->status_id == 9) $total_delivered += $pkg->driver_total;
                if($pkg->status_id == 10) $failedCount +=1;
                if($pkg->status_id == 19) {
                    $failedWithFeeCount +=1;
                    $totalFailedWithFee += $pkg->driver_total;
                }
                if($pkg->status_id == 6) $deliveryCount +=1;
            }
        }
        return (object)[
            'total' => number_format($total,2),
            'failed_count' => $failedCount,
            'total_delivered' => number_format($total_delivered,2),
            'total_failed_with_fee' => number_format($totalFailedWithFee,2),
            'failed_with_fee_count' => $failedWithFeeCount,
            'delivery_count' => $deliveryCount
        ];
    }

    public function getTripPackages(Request $req){
        $trip_id = $req->trip_id;
        $search = $req->search;
        $qP = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->whereIn('p.status_id',[6,9,10,19])
        // ->where('dp.delay_count',0)
        ->where('dp.delivery_id',$trip_id)
        ->leftJoin('users as m','m.id','p.merchant_id')
        ->leftJoin('users as d','d.id','p.driver_id')
        ->where(function($q){
            $q->where('dp.is_deleted',0)->where('dp.delay_count',0);
        })
        ->join('tracking_statuses as ts','ts.id','dp.status_id')
        ->selectRaw('p.qr_code,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code,d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.taxi_fee,p.product_type,dp.status_id,p.payer')
        ->orderByRaw('(dp.status_id = ?) DESC', [6]);
        if ($search && str_starts_with($search, 'JPK')) {
            $qP->where('p.qr_code',$search);
        }


        $packages = $qP->get();
        foreach($packages as $package){
            $package->delivery_fee = $package->base_fee + $package->extra_charge;
            unset($package->status);
        }
        return ApiResponse::Pagination($packages,$req,__('messages.get_list',['info' => 'Package']));
    }

    public function setPackageStatus(Request $req){
        $user = UserService::getAuthUser();
        $trip_id = $req->trip_id;
        $package_id = $req->package_id;
        $status_id = $req->status_id;
        $failure_notes = $req->failure_notes ?? null;
        $payer = $req->payer ?? null;
        $delivery = Delivery::where('is_deleted',0)->selectRaw('id')->find($trip_id);
        $tripPackage = DeliveryPackage::where('package_id',$package_id)->where('delivery_id','!=',$trip_id)->where('delay_count',0)->orderByDesc('id')->first();
        // if(!$tripPackage) return ApiResponse::NotFound(__('messages.not_found',[
        //     'info' => 'Package',
        //     'khInfo' => 'កញ្ចប់'
        // ]));
        $delayMsg = '.';
        if($tripPackage) $delayMsg = ', This package is delivered in trip number('.Delivery::where('id',$tripPackage->delivery_id)->value('fleet_tracking_number').')';
        if(!$delivery) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Trip']));
        if(!$status_id || !in_array($status_id,[9,10,19])) return ApiResponse::ValidateFail(__('messages.not_found',['info' => 'Status']));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('tracking_notes,payer,id,status_id,driver_id,cod,delivery_fee,price,additional_fee,extra_charge,taxi_fee')
        ->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package has already been delivered'
        ]));
        if($package->status_id == 19 && $status_id != 19) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package has already been marked as failed with fee'.$delayMsg
        ]));
        if(!in_array($package->status_id,[6,9,10,19])) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Package must be on delivery before set to delivered,failed or failed with fee.']));
        $failDatetime = ($status_id == 10 || $status_id == 19) ? now() : null;
        $deliveredDatetime = $status_id == 9 ? now():null;
        DB::beginTransaction();
        try{
            $updateArr = [
                'update_uid' => $user->id,
                'failure_notes' => $failure_notes,
                'failed_datetime' => $failDatetime,
                'delivered_datetime' => $deliveredDatetime,
                'status_id' => $status_id
            ];

            if($status_id == 19) {
                $driverTotal = PickupCenterService::getDriverTotal($package->cod,$payer,0,$package->delivery_fee,$package->additional_fee,$package->extra_charge,0);
                if($payer == 'receiver') {
                    $updateArr['driver_total'] = $driverTotal;
                    $updateArr['merchant_total'] = 0;
                }
                else {
                    $updateArr['driver_total'] = 0;
                    $updateArr['merchant_total'] = PickupCenterService::getTotal('merchant',$package->cod,$payer,$package->price,$package->delivery_fee,$package->additional_fee,$package->extra_charge,$package->taxi_fee);
                }

                $updateArr['payer'] = $payer;
            }else{
                $driverTotal = PickupCenterService::getDriverTotal($package->cod,$payer,$package->price,$package->delivery_fee,$package->additional_fee,$package->extra_charge,$package->taxi_fee);
                $updateArr['driver_total'] = $driverTotal;
            }
            $package->update($updateArr);
            DeliveryPackage::where('package_id',$package_id)->where('delivery_id',$trip_id)->where(function($q){
                $q->where('delay_count',0)->orWhere('is_deleted',0);
            })->update([
                'update_uid' => $user->id,
                'failure_notes' => $failure_notes,
                'failed_datetime' => $failDatetime,
                'delivered_datetime' => $deliveredDatetime,
                'status_id' => $status_id
            ]);
            GeneralSettingService::updateTripStatus($trip_id,$user);
            DB::commit();
        }catch(Exception $e){
            DB::rollBack();
        }
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
        $kickReason = $req->kick_reason ?? null;
        if(!$kickReason) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please enter your reason'
        ]));
        $deliveryPackage = DeliveryPackage::where('company_id',$user->company_id)->where('delivery_id',$trip_id)->where('package_id',$package_id)->where('is_deleted',0)
        ->first();
        if(!$deliveryPackage) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->with('driver')->find($package_id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        $todayDT = Helper::getDateTime();
        $driverName = $package->driver?->user_name;
        if($package->status_id != 6) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only delivery package can be kicked from trip'
        ]));
        DB::beginTransaction();
        try{
            $fleetNumber = Delivery::where('id',$trip_id)->take(1)->value('fleet_tracking_number');
            $trackingNotes = $package->tracking_notes."|[$user->id]Admin('.$user->user_name) remove package from Driver($driverName) at ($todayDT) on fleet number $fleetNumber";
            $package->update([
                'driver_id' => null,
                'kick_uid' => $user->id,
                'kick_reason' => $kickReason,
                'status_id' => 5,
                'kick_notes' => $package->kick_notes."|[$user->id]$user->user_name remove package from Driver($driverName) at ($todayDT) on fleet number $fleetNumber",
                'tracking_notes' => $trackingNotes
            ]);
            $deliveryPackage->update([
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'is_deleted' => true,
                'delay_count' => 1,
                'kick_uid' => $user->id,
                'kick_reason' => $kickReason,
                'kick_notes' => $package->kick_notes."|[$user->id]$user->user_name remove package from Driver($driverName) at ($todayDT) on fleet number $fleetNumber",
                'notes' => $deliveryPackage->notes."|[$user->id]-Admin('.$user->user_name) remove package from Driver($driverName) at ($todayDT) on fleet number $fleetNumber"
            ]);

            Delivery::find($trip_id)->update([
                'package_count' =>  DB::raw('package_count - 1'),
            ]);

            $trip = Delivery::find($trip_id);
            if($trip){
                $onDeliveryCount = $trip->package_count - ($trip->delivered_count + $trip->failed_count);
                if($onDeliveryCount == 0) {
                    $trip->update([
                        'finished' => 1,
                        'is_completed' => 1,
                        'status_id' => 16
                    ]);
                    // Log::error(json_encode(Delivery::select('status_id','is_completed','finished')->find($trip_id)));
                }
                // Log::error($onDeliveryCount);
                if($trip->package_count == 0) $trip->update([
                    'is_deleted' => 1,
                    'deleted_uid' => $user->id,
                    'deleted_datetime' => now(),
                    'tracking_notes' => $trip->tracking_notes.'| Kick all packages out so this trip is deleted'
                ]);
            }
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.info',[
                'info' => 'Package '.$package->qr_code.' has been removed from Driver'
            ]));
        }catch(Exception $e){
            DB::rollBack();
            return ApiResponse::Error('Failed');
        }
    }

    public function deleteTrip(Request $req){
        $user = UserService::getAuthUser();
        $tripId = $req->trip_id;
        $trip = Delivery::where('is_deleted',0)->where('company_id',$user->company_id)
        ->find($tripId);
        if(!$trip) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Trip']));
        $qP = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->where('dp.delay_count',0)
        ->where('dp.delivery_id',$tripId)
        ->join('users as m','m.id','p.merchant_id')
        ->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','dp.status_id')
        ->selectRaw('dp.id as dp_id,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code,d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.driver_total as delivery_fee,p.taxi_fee,p.product_type,dp.status_id');
        $packages = $qP->get();
        // return $packages;
        $count = $qP->count();
        $deleteCount = 0;
        $message = 'Removed on delivery packages from trip list';
        foreach($packages as $pkg){
            $deletable = DeliveryPackage::where('status_id',6)->where('is_deleted',0)->find($pkg->dp_id); //** on delivery to package trail
            if($deletable){
                $deleteCount += 1;
                $deletable->update([
                    'is_deleted' => true,
                    'deleted_datetime' => now(),
                    'deleted_uid' => $user->id
                ]);
            }
        }
        if($deleteCount == $count){
            $trip->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
            ]);
            $message = 'Trip deleted successfully';
        }

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => $message
        ]));

    }

    public function finishTrip(Request $req){
        $user = UserService::getAuthUser();
        $tripId = $req->trip_id;
        $reason = $req->reason;
        if(!$reason) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Enter your reason'
        ]));
        $trip = Delivery::where('is_deleted',0)->where('company_id',$user->company_id)
        ->find($tripId);
        if(!$trip) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Trip']));
        if(($trip->finished || $trip->is_completed) && $trip->status_id == 16) {
            return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'This trip has already been finisded'
            ]));
        }

        $qP = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->where('dp.delay_count',0)
        ->where('dp.delivery_id',$tripId)
        ->join('users as m','m.id','p.merchant_id')
        ->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','dp.status_id')
        ->selectRaw('dp.id as dp_id,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code,d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.driver_total as delivery_fee,p.taxi_fee,p.product_type,dp.status_id');
        $packages = $qP->get();
        $deliveredCount = 0;
        foreach($packages as $pkg){
            $updatable = DeliveryPackage::where('status_id',6)->find($pkg->dp_id); //** on delivery to package trail
            if($updatable){
                $deliveredCount +=1;
                $updatable->update([
                    'status_id' => 9,
                    'delivered_datetime' => now(),
                    'update_uid' => $user->id
                ]);
            }
            $updatablePkg = Package::where('status_id',6)->find($pkg->package_id);
            if($updatablePkg){
                $updatablePkg->update([
                    'status_id' => 9,
                    'delivered_datetime' => now(),
                    'update_uid' => $user->id
                ]);
            }
        }

        $trip->update([
            'finished' => 1,
            'is_completed' =>1,
            'status_id' => 16,
            'finished_uid' => $user->id,
            'delivered_count' => $deliveredCount + $trip->delivered_count,
            'finished_reason' => $reason,
            'finished_datetime' => now()
        ]);


        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Trip finished',
        ]));
    }

    public function createOrUpdateTrip(Request $req){
        $user = UserService::getAuthUser();
        $createOrUpdate = $this->createOrUpdateTripService($req,$user);
        return ApiResponse::flex($createOrUpdate);
    }

    public function createOrUpdateTripService(Request $req,$user,$allowedPkgStatuses=[5]){
        $today = date('Y-m-d');
        $validate = validator($req->all(),[
            'packages' => 'required|array',
            'depart_datetime' => 'required',
            'vehicle_type' => 'nullable|exists:vehicle_types,name',
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
        DB::beginTransaction();
        try{
            if(!$pendingTrip){
                $QuerylastPackage = DeliveryPackage::whereIn('package_id',$packageIds)->where('delay_count',0)->where('is_deleted',0);
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
            // $duplicatedPkgs = [];
            foreach($packageIds as $pkg){
                // $newPackageCount = $pendingTrip->package_count;
                // $delay = 1;
                // $isNewPkg = true;

                $packageId = $pkg['package_id'] ?? null;
                if(!$packageId) return DataResponse::ValidateFail('Please provide package identity');
                $allowablePkg = Package::where('outstanding',0)->find($packageId);
                if(!$allowablePkg) return DataResponse::ValidateFail(__('messages.not_found',[
                    'info' => 'Package'
                ]));
                if(!in_array($allowablePkg->status_id,$allowedPkgStatuses)) return DataResponse::ValidateFail(__('messages.info',[
                    'info' => $allowedPkgStatuses[0] == 5 ?'Package must be at warehouse':'Package must be on delivery'
                ]));
                //** add delivery tracking */
                // if($isNewPkg) {

                    $dPackage = DeliveryPackage::where('delay_count',0)->where('is_deleted',0)->where('package_id',$packageId)->first();
                    if(!$dPackage){
                        $dPackage = DeliveryPackage::create([
                            'notes' => 'Admin add package to trip',
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
                    if(in_array(6,$allowedPkgStatuses)){
                        $dPackage = DeliveryPackage::create([
                            'notes' => 'Admin add package to trip',
                            'driver_id' => $driverId,
                            'delivery_id' => $deliveryId,
                            'package_id' => $packageId,
                            'status_id' => 6, // On Delivery
                            'update_uid' => $user->id,
                            'create_uid' => $user->id,
                            'branch_id' => $user->branch_id,
                            'company_id' => $user->company_id,
                        ]);
                    }
                    $allowablePkg->update([
                        'driver_id' => $driverId,
                        'status_id' => 6,
                        'tracking_notes' => "[$user->id]Admin add package to trip (".Helper::getDateTime().")"
                    ]);
                // }
            }
            // if(isset($duplicatedPkgs[0])){
            //     $pkgQrString = implode(',',$duplicatedPkgs);
            //     return ApiResponse::Duplicated(__('messages.info',[
            //         'info' =>  "These packages are delivered.[$pkgQrString]"
            //     ]));
            // }
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

    public function getPackageByBarcode(Request $req){
        $user = UserService::getAuthUser();
        $barCode = $req->barcode;
        $package = Package::where('qr_code',$barCode)
        ->with('merchant')
        ->where('company_id',$user->company_id)
        ->selectRaw('id,receiver_name,receiver_phone,zone_name,zone_code,taxi_fee,cod,payer,delivery_fee,extra_charge,remarks,status_id,merchant_id,additional_fee,price,delivery_type')
        ->first();

        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($package->status_id != 5) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only package at warehouse allows'
        ]));

        $xRate = GeneralSettingService::getLatestXRate();
        $total = PickupCenterService::getDriverTotal($package->cod,$package->payer,$package->price,$package->delivery_fee,$package->additional_fee,$package->extra_charge,$package->taxi_fee);
        $package->total = $total;
        $package->cod = $package->cod ? 'Yes' : 'No';
        $package->total_khr = $total * $xRate->buy_rate;
        $package->fee = PickupCenterService::getFees($package->payer,$package->delivery_fee,$package->delivery_fee,$package->extra_charge);
        $package->merchant_name = $package->merchant?->user_name;
        $package->merchant_phone = $package->merchant?->phone;
        unset($package->merchant);
        return ApiResponse::JsonResult($package,__('messages.info',[
            'info' => 'Added'
        ]));
    }


    public function printTripPackages(Request $req){
        $user = UserService::getAuthUser();
        $tripId = $req->trip_id;
        $startDate = $req->start_date;
        $endDate = $req->end_date;
        $companyInfo = CompanyProfileService::profileInfo($user);
        $qP = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->where('dp.delivery_id',$tripId)
        ->where('dp.delay_count',0)
        ->selectRaw('p.id as package_id,dp.delivery_id,dp.delay_count,dp.status_id,p.driver_total');
        if($startDate && $endDate){
            $startDate = date('Y-m-d H:i:s',strtotime($startDate));
            $endDate = date('Y-m-d H:i:s',strtotime($endDate));
            $qP->whereBetween('p.arrive_warehouse_datetime', [$startDate, $endDate]);
        }
        $packages = $qP->get();
        $obj = [
            'company_info' => $companyInfo,
            'packages' => $packages
        ];
        return ApiResponse::JsonResult($obj);
    }
}
