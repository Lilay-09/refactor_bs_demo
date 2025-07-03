<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Enums\TrackingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Models\User;
use App\Models\Zone;
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
    private $cacheTags;
    public function __construct(private PickupCenterService $pickupCenterService){
        $this->cacheTags = ['package_trail'];
    }
    public function getPackages(Request $req){
        $user = UserService::getAuthUser();
        $search = $req->query('search', null);
        $orderId = $req->query('order_id', null);
        $lang = $req->query('lang');
        $warehouse_id = $req->query('warehouse_id');
        $statusId = $req->query('status_id', null);
        $merchantId = $req->query('merchant_id', null);
        $driverId = $req->query('driver_id', null);
        $zoneCode = $req->query('zone_code', $req->query('zone_id', null));
        $startDate = $req->query('startDate', null);
        $endDate = $req->query('endDate', null);


        $query = Package::query()->where('is_deleted',0)
        ->with(['status','merchant','driver'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->where(function($q){
            $q->whereNotIn('status_id',[9,11,12])->whereNull('returned_uid');
        })
        // ->selectRaw('merchant_id,order_id,id,taxi_fee,delivery_type,qr_code,price,driver_id,product_type,dim_z,dim_x,dim_y,status_id,failed_datetime,failure_notes,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,delivered_datetime,assign_driver_datetime,arrive_warehouse_datetime,driver_total,merchant_total,billed_kg,actual_kg,created_at')
        ->orderByRaw('(status_id = ?) DESC', [5])
        ->orderBy('arrive_warehouse_datetime','desc')
        ->orderByRaw("
            CASE
                WHEN status_id = 9 THEN delivered_datetime
                WHEN status_id IN (10, 19) THEN failed_datetime
                ELSE NULL
            END DESC
        ");
        $select = ['merchant_id','order_id','id','taxi_fee','delivery_type','qr_code','price','driver_id','product_type','dim_z','dim_x','dim_y','status_id','failed_datetime','failure_notes','payer','cod','delivery_fee','receiver_address','zone_code','zone_name','receiver_name','receiver_phone','delivered_datetime','assign_driver_datetime','arrive_warehouse_datetime','driver_total','merchant_total','billed_kg','actual_kg','created_at'];
        if($warehouse_id){
            $query->where('warehouse_id',$warehouse_id);
            // $query->whereHas('order',function($q) use($warehouse_id){
            //     $q->where('warehouse_id',$warehouse_id);
            // });
        }
        if($zoneCode) {
            $query->where('zone_code',$zoneCode);
        }
        if($statusId){
            $query->where('status_id',$statusId);
        }
        if($orderId){
            $query->where('order_id',$orderId);
        }
        if($merchantId){
            $query->where('merchant_id',$merchantId);
        }
        if($driverId){
            $query->where('driver_id',$driverId);
        }
        if($search){
            // $query->where(function ($q) use($search){
                $query->where('qr_code',$search)->orWhere('receiver_phone','ilike','%'.$search.'%');
            // });
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate).' 00:00:00';
            $endDate = Helper::dateYMD($endDate).' 23:59:59';
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                ->orWhereBetween('arrive_warehouse_datetime', [$startDate,$endDate])
                ->orWhereBetween('failed_datetime', [$startDate, $endDate]);
            });
        }
        $callbackMapper = function($pkg) use ($lang){
            $cod = $pkg->cod;
            $pkg->driver_name = $pkg->driver?->username;
            $pkg->merhcant_name = $pkg->merchant?->username;
            $pkg->merchant_phone = $pkg->merchant?->phone;
            $pkg->cod = $cod == true ? 1:0;

            if($lang == 'km'){
                $pkg->status_code = GeneralSettingService::$statusCodeTrans[$pkg->status_id];
            }else{
                $pkg->status_code = $pkg->status->name;
            }
            $pkg->has_image = PackageAttachment::where('hidden',0)->where('package_id',$pkg->id)->exists() ? 1 : 0;
            $pkg->total = Helper::getNumber(abs($pkg->driver_total - $pkg->merchant_total),2);//PickupCenterService::getDriverTotal($cod,$pkg->payer,$pkg->price,$pkg->delivery_fee,$pkg->additional_fee,$pkg->excharge_fee);
            $pkg->warehouse_timeago = Helper::timeAgo($pkg->arrive_warehouse_datetime,false);
            $pkg->arrive_warehouse_datetime = Helper::formatCustomDateTime($pkg->arrive_warehouse_datetime,null,false,$lang);
            if($pkg->status_id == 10 || $pkg->status_id == 19) $pkg->finished_date = Helper::formatCustomDateTime($pkg->failed_datetime);
            unset($pkg->status,$pkg->merchant,$pkg->driver);
            return $pkg;
        };
        return ApiResponse::PaginationV1($query,$req,__('messages.get_list',['info'=>'Package']),[],1000,$callbackMapper,$select,1800,$this->cacheTags);
    }

    public function getOnePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        // $isKm = $req->lang == 'km';
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
        $package->cod = $cod == false ? "0" : "1";
        $driver = $package->driver;
        if($driver){
            $package->driver_name = $driver->username;
        }
        // if($isKm) $package->payer = GeneralSettingService::$payerTrans[$package->payer] ?? '';
        $package->base_fee = $package->delivery_fee;
        $package->delivery_fee = $package->delivery_fee + $package->extra_charge + $package->taxi_fee;
        $package->warehouse_timeago = Helper::timeAgo($package->arrive_warehouse_datetime,false);
        unset($package->status,$package->driver);
        return ApiResponse::JsonResult($package);
    }

    public function getPackageImages(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $images = PackageAttachment::where('package_id', $id)
        ->where('hidden', 0)
        ->orderBy('created_at', 'desc') // Ensure most recent images are fetched
        ->take(2) // Limit to 2 images
        ->selectRaw('file_name, DATE(created_at) as date') // Correct usage of DATE()
        ->get();
        // ->toArray();
        // $imageUrls = array_map(fn($img) => Helper::getImageUrl($img, $user->company_id, 'submit_package'), $images);
        $imageUrls = $images->map(fn($img) => Helper::getImageUrl($img->file_name, $user->company_id, 'submit_package',$img->date))
                    ->toArray();
        return ApiResponse::JsonResult($imageUrls);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        if($package->status_id == TrackingStatus::ACCEPTED_FOR_PICKUP_DEL->value) return ApiResponse::Forbidden(__('messages.no_access',['info' => 'This package is already assigned to driver']));
        if($package->status_id == TrackingStatus::ON_DELIVERY_TRIP->value) return ApiResponse::Forbidden(__('messages.no_access',['info' => 'This package is on delivery']));
        // if($package->driver_payment_id || $package->driver_disbursement_id) return DataResponse::Duplicated(__('messages.info',[
        //     'info' => 'It seems like you try to update package which is on payment pending or paid with driver'
        // ]));
        if($package->merchant_payment_id || $package->merchant_disbursement_id) return DataResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to update package which is on payment pending or paid with merchant'
        ]));
        $req->merge(['merchant_id' => $package->merchant_id]);
        $validate = $this->pickupCenterService->packageValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['update_uid'] = $user->id;
        $price = $inputs['price'] ?? 0;
        // Log::info($price);
        // $inputs['price'] = $price;
        $actualKg = $inputs['actual_kg'] ?? 0;
        $billedKg = $inputs['billed_kg'] ?? 0;
        $inputs['actual_kg'] = $actualKg;
        $taxiFee = $inputs['taxi_fee'] ?? 0;
        $payer = $inputs['payer'];
        $cod = $inputs['cod'] ?? $package->cod;
        $inputs['billed_kg'] = $actualKg;
        $inputs['status_id'] = $package->status_id; //** add warehouse */
        $zoneCode = $inputs['zone_code'] ?? $package->zone_code;
        $inputs['zone_name'] = Zone::where('zone_code', $zoneCode)->where('is_deleted',0)->value('zone_name');
        $extra_charge = $inputs['extra_charge'] ?? 0;
        $calPrice = GeneralSettingService::calculatePackageFee($zoneCode,$price,$billedKg,$actualKg,$payer,$cod,$extra_charge,$user,$taxiFee,$package->merchant_id);
        if($calPrice->error) return $calPrice;
        $deliveryFee = $calPrice->delivery_fee;
        $inputs['delivery_fee'] = $calPrice->delivery_fee;
        // $inputs['driver_total'] = ($package->status_id == 19 && $package->cod) ? abs($package->price - $calPrice->driver_total):$calPrice->driver_total;
        $driverTotal = $this->pickupCenterService::getDriverTotal($cod,$payer,$price,$deliveryFee,$package->additional_fee,$extra_charge,$taxiFee);
        $inputs['driver_total'] = $driverTotal;
        $inputs['merchant_total'] = $this->pickupCenterService::getTotal('merchant',$cod,$payer,$price,$deliveryFee,$package->additional_fee,$extra_charge,$taxiFee);
        if($package->status_id == TrackingStatus::FAILED_WITH_FEE->value){
            $driverTotal = $this->pickupCenterService::getDriverTotal($cod,$payer,0,$deliveryFee,$package->additional_fee,$extra_charge,0);
            if($payer == 'receiver') {
                $inputs['driver_total'] = $driverTotal;
                $inputs['merchant_total'] = 0;
            }
            else {
                $inputs['driver_total'] = 0;
                $inputs['merchant_total'] = $this->pickupCenterService::getTotal('merchant',$cod,$payer,0,$deliveryFee,$package->additional_fee,$extra_charge,0);
            }
        }
        Helper::clearCacheByTags($this->cacheTags);
        $package->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function deletePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id != 5) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only package at warehouse can be deleted',
            'khInfo' => 'មានតែកញ្ចប់​នៅកន្លែងអាចលុបបាន'
        ]));
        $orderId = $package->order_id;
        $otherPackageCount = Package::where('is_deleted',0)->where('order_id',$orderId)->where('id','!=',$id)->count();

        $package->update([
            'is_deleted' => true,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);
        if($otherPackageCount == 0) Order::find($orderId)->update([
            'is_deleted' => true,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);
        Helper::clearCacheByTags($this->cacheTags);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Deleted',
            'khInfo' => 'លុបជោគជ័យ'
        ]));
    }

    public function returnPackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $driverId = $req->driver_id;
        if(!$driverId) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please choose driver'
        ]));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        if(!in_array($package->status_id,[
            TrackingStatus::returnable()
        ])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only failed package or at warehouse can be returned'
        ]));
        $statusId = TrackingStatus::RETURNING;
        if($package->status_id == TrackingStatus::FAILED_WITH_FEE->value){
            //** not change status but use returned_uid for tracking */
            $statusId = TrackingStatus::FAILED_WITH_FEE->value;
        }
        $package->update([
            'returned_uid' => $driverId,
            'status_id' => $statusId, // returned
            // 'returned_datetime' => now(),
            'update_uid' => $user->id,
        ]);
        Helper::clearCacheByTags($this->cacheTags);
        return ApiResponse::JsonResult(null,__('messages.info',['info' => 'Returned']));
    }

    public function setReturnPackageDrop(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $driverId = $req->driver_id;
        if(!$driverId) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please choose driver'
        ]));
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        if($package->status_id != TrackingStatus::RETURNING->value) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please ensure package is returning before setting drop point',
            'khInfo' => 'សូមបញ្ជាក់កញ្ចប់ត្រូវបានដាក់កំពុងត្រលប់​មុនពេលកំណត់ចំណុចទម្លាក់'
        ]));
        $statusId = 11;
        if($package->status_id == 19){
            $statusId = 19;
        }
        $package->update([
            'returned_uid' => $driverId,
            'status_id' => TrackingStatus::RETURNED->value, // returned
            'returned_datetime' => now(),
            'update_uid' => $user->id,
        ]);
        Helper::clearCacheByTags($this->cacheTags);
        return ApiResponse::JsonResult(null,__('messages.info',['info' => 'Returned']));
    }

    public function getPrintInfo(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('is_deleted',0)
        ->with(['driver:id,username,phone','merchant:id,phone,username','updateUser:id,username'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->selectRaw('id as package_id,cod,extra_charge,taxi_fee,delivery_fee,zone_name,zone_code,merchant_id,driver_id,receiver_phone,receiver_address,created_at,arrive_warehouse_datetime,qr_code,remarks,price,update_uid,payer')
        ->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $driver = $package->driver;
        if($driver){
            $package->driver_name = $driver->username;
        }
        $package->receiver_address = $package->receiver_address ?? $package->zone_name;
        $package->merchant_name = $package->merchant->username;
        $package->merchant_phone = $package->merchant->phone;
        $package->delivery_fee = $package->delivery_fee + $package->taxi_fee + $package->extra_charge;//($package->cod ? $package->price : 0);
        $package->base_fee = $package->delivery_fee;
        $package->created_by = $package->updateUser->username;
        $package->created_date = Helper::formatCustomDateTime($package->created_at,'d-M-Y');
        $package->warehouse_at = Helper::dateDMY($package->arrive_warehouse_datetime);
        $total = 0;
        if($package->cod) $total += $package->price;
        if($package->payer == 'receiver') $total += $package->delivery_fee;
        $package->total = $total;
        $exchange = GeneralSettingService::getLatestXRate();
        $package->total = $total;
        $package->total_khr = Helper::getNumber($total * $exchange->buy_rate);
        unset($package->status,$package->driver,$package->merchant,$package->arrive_warehouse_datetime,$package->updateUser,$package->create_uid,$package->created_at);

        $companyInfo = CompanyProfileService::profileInfo($user,true);
        $obj = (object)[
            'company_info' => $companyInfo,
            'package' => $package,
            'notes' => GeneralSettingService::disclaimerText($companyInfo?->disclaimer),//'រាល់ទំនិញខុសច្បាប់ ម្ចាស់ទំនិញត្រូវទទួលខុសត្រូវចំពោះមុខច្បាប់ដោយខ្លួនឯង ក្រុមហ៊ុនមិនទទួលខុសត្រូវឡេីយ។ អរគុណសម្រាប់ការប្រើប្រាស់សេវាកម្មដឹកជញ្ជូន JS Express របស់ខ្ញុំ។',
            'redirect' => asset('api/redirect-store')
        ];
        return ApiResponse::JsonResult($obj,__('messages.info',['info' => 'Print Information']));
    }

    public function getPackagesPrintInfo(Request $req){
        $user = UserService::getAuthUser();
        $packageIds = $req->packages;
        $orderByCase = collect($packageIds)
        ->map(function ($id, $index) {
            return "WHEN id = " . (int)$id . " THEN " . (int)$index;
        })
        ->implode(' ');
        $packages = Package::where('is_deleted',0)
        ->with(['driver:id,username,phone','merchant:id,phone,username','updateUser:id,username'])
        ->where('outstanding',0)
        // ->whereNotIn('status_id',[]) // at warehouse
        ->where('company_id',$user->company_id)
        ->selectRaw('id as package_id,cod,extra_charge,taxi_fee,delivery_fee,zone_name,zone_code,merchant_id,driver_id,receiver_phone,receiver_address,created_at,arrive_warehouse_datetime,qr_code,remarks,price,update_uid,payer')
        ->whereIn('id',$packageIds)
        ->orderByRaw("CASE $orderByCase END")
        ->get();
        // if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $exchange = GeneralSettingService::getLatestXRate();
        foreach($packages as $package){
            $driver = $package->driver;
            if($driver){
                $package->driver_name = $driver->username;
            }
            $package->receiver_address = $package->receiver_address ?? $package->zone_name;
            $package->merchant_name = $package->merchant->username;
            $package->merchant_phone = $package->merchant->phone;
            $package->delivery_fee = $package->delivery_fee + $package->taxi_fee + $package->extra_charge;//($package->cod ? $package->price : 0);
            $package->base_fee = $package->payer == 'receiver' ? $package->delivery_fee:0;
            $package->created_by = $package->updateUser->username;
            $package->created_date = Helper::formatCustomDateTime($package->created_at,'d-M-Y');
            $package->warehouse_at = Helper::dateDMY($package->arrive_warehouse_datetime);
            $total = 0;
            if($package->cod) $total += $package->price;
            if($package->payer == 'receiver') $total += $package->delivery_fee;
            $package->total = $total;
            $package->total_khr = Helper::getNumber($total * $exchange->buy_rate);
            unset($package->status,$package->driver,$package->merchant,$package->arrive_warehouse_datetime,$package->updateUser,$package->create_uid,$package->created_at);
        }
        $companyInfo = CompanyProfileService::profileInfo($user,true);
        $obj = (object)[
            'company_info' => $companyInfo,
            'packages' => $packages,
            'notes' => GeneralSettingService::disclaimerText($companyInfo?->disclaimer),//'រាល់ទំនិញខុសច្បាប់ ម្ចាស់ទំនិញត្រូវទទួលខុសត្រូវចំពោះមុខច្បាប់ដោយខ្លួនឯង ក្រុមហ៊ុនមិនទទួលខុសត្រូវឡេីយ។ អរគុណសម្រាប់ការប្រើប្រាស់សេវាកម្មដឹកជញ្ជូន JS Express របស់ខ្ញុំ។',
            'redirect' => asset('api/redirect-store')
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
        if($validDriver->lock) {
            return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Driver is currently inactive',
                'khInfo' => 'អ្នកដឹកជញ្ជូនត្រូវបានឈប់ដំណើរការ'
            ]));
        }

        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)
        ->where('outstanding',0)
        ->with('merchant:id,username,phone')
        // ->select(['id','status_id','merchant_id','driver_id','assign_uid','assign_driver_datetime','receiver_phone','order_id'])
        ->find($id);
        if(!$package) {
            return ApiResponse::NotFound(__('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
        }
        if($package->status_id == 9) {
            return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'This package is already delivered'
            ]));
        }
        if($package->status_id == 19) {
            return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'This package is already marked as failed with fee'
            ]));
        }
        if($package->status_id == 11) {
            return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'This package is already returned'
            ]));
        }
        if($package->status_id == 12) {
            return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'This package is in transit'
            ]));
        }
        if($package->driver_id == $driver_id && $package->status_id == 6) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'It seems like you are trying to assign this package to the same driver',
            'khInfo' => 'កញ្ចប់បានចាត់តាំងរួចម្ដងហើយ'
        ]));

        DB::beginTransaction();
        try{
            if($package->driver_id){
                $deliveryPackage = DeliveryPackage::where('package_id',$id)->where('is_deleted',0)->where('delay_count',0)->first();
                if($deliveryPackage){
                    if(!in_array($package->status_id,[6,5,10,19]) ) return ApiResponse::Duplicated(__('messages.has already assigned',['info' => 'Package','khInfo' => 'កញ្ចប់']));
                }
                $selfTrip = Delivery::where('driver_id',$package->driver_id)->where('status_id',14)->where(function($query) {
                    $query->where('finished', 0)
                    ->where('is_deleted', 0);
                })->orderByDesc('id')->first();
                //** remove self pacakge */
                if($selfTrip && $driver_id != $package->driver_id){
                    $toDelete = ($package->status_id == 6);
                    $pkgCount = $selfTrip->package_count;
                    $upArr = [];
                    if($toDelete){
                        $pkgCount -=1;
                        $upArr = [
                            'package_count' => $pkgCount
                        ];
                    }
                    if($pkgCount == 0){
                        $upArr['is_deleted'] = 1;
                        $upArr['deleted_datetime'] = now();
                        $upArr['deleted_uid'] = $user->id;
                        $upArr['tracking_notes'] = $selfTrip->tracking_notes.'|All packages were removed so trip is deleted';
                    }
                    if($pkgCount == ($selfTrip->failed_count + $selfTrip->delivered_count)) {
                        $upArr['finished'] = 1;
                        $upArr['is_completed'] = 1;
                        $upArr['status_id'] = 16;
                    }
                    $selfTrip->update($upArr);
                    DeliveryPackage::where('delivery_id',$selfTrip->id)
                    ->where('is_deleted',0)
                    ->where('package_id',$package->id)
                    ->where('delay_count',0)
                    ->update([
                        'is_deleted' => ($package->status_id == 6),
                        'deleted_uid' => ($package->status_id == 6) ? $user->id : null,
                        'has_swap' => $driver_id != $package->driver_id,
                        'delay_count' => 1,
                        'deleted_datetime' => ($package->status_id == 6) ? now():null,
                        'notes' => DB::raw('notes || \'| admin change driver\'')
                    ]);
                }else if($selfTrip && $driver_id == $package->driver_id){
                    $pkgCount = $selfTrip->package_count - 1;
                    $upArr = [
                        'package_count' => $pkgCount
                    ];
                    $selfTrip->update($upArr);
                    $toDelete = ($package->status_id == 10);
                    DeliveryPackage::where('delivery_id',$selfTrip->id)
                    ->where('package_id',$package->id)
                    ->where('delay_count',0)
                    ->update([
                        // 'is_deleted' => $toDelete,
                        // 'deleted_uid' => $toDelete ? $user->id : null,
                        'delay_count' => 1,
                        // 'deleted_datetime' => $toDelete ? now():null,
                        'notes' => DB::raw('notes || \'| admin re-assign driver\'')
                    ]);
                }
            }
            $package->update([
                'driver_id' => $driver_id,
                'assign_uid' => $user->id,
                'status_id' => 6, // On Delivery
                'assign_driver_datetime' => now(),
            ]);
            $trip = $this->createOrUpdateTrip($driver_id,$id,$validDriver->vehicle_type,$user,$notes,6,'assign',$package);
            if($trip->error) return ApiResponse::flex($trip);
            // $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$driver_id);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $driver_id,
                'title' => __('notification.assign_package.title'),
                'body' => __('notification.assign_package.body',[
                    'merchant' => $package->merchant->username,
                ])//'You have been assigned to deliver the package('.$package->qr_code.').'
            ]);
            // $notif->sendNotificationByTopic($notifReq,$user);
            SendNotificationJob::dispatch($notifReq, $user);
            Helper::clearCacheByTags($this->cacheTags);
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.assigned',['info' => '']));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return ApiResponse::Error(__('messages.error',['info' => 'Fail to assign driver']));
        }
    }

    public function changeMerchant(Request $req){
        $id = $req->id;
        $merchantId = $req->merchant_id;
        $merchant = User::where('is_deleted',0)->find($merchantId);
        if(!$merchant) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Merchant'
        ]));
        $user = UserService::getAuthUser();
        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return ApiResponse::NotFound();
        $package->update([
            'update_uid' => $user->id,
            'merchant_id' => $merchantId
        ]);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Merchant('.$merchant->username.') has owned package('.$package->qr_code.') now.'
        ]));
    }

    public function createOrUpdateTrip($driverId,$packageId,$vehicleType,$user,$notes,$statusId,$action=null,$package=null){
        $today = date('Y-m-d');
        $isNewPkg = true;
        $pendingTrip = Delivery::where(function($query) {
            $query->where('finished', 0)
            ->where('is_deleted', 0);
        })->where('company_id', $user->company_id)
        ->where('driver_id', $driverId)
        ->first();
        if(!$pendingTrip) {
            $oneTrip = Delivery::orderByDesc('id')->where('driver_id',$driverId)->where('is_deleted',0)->first();
            if($oneTrip){
                $stillHasPackage = DeliveryPackage::where('delivery_id',$oneTrip->id)
                ->where('delay_count',0)->where('has_swap',0)->where('is_deleted',0)
                ->where('status_id',6)->first();
                if($stillHasPackage) $pendingTrip = $oneTrip ?? null;
            }
        }

        if(!$pendingTrip){
            $QuerylastPackage = DeliveryPackage::where('package_id',$packageId)->where(function ($q){
                $q->where('delay_count',0)->where('is_deleted',0);
            });
            $hasFailPackage = $QuerylastPackage->orderByDesc('id')->get();
            if(isset($hasFailPackage[0])) {
                $isSwap = $hasFailPackage[0]->status_id == 6;
                $upFailArr = $isSwap? ['has_swap' => 1] : ['delay_count' => 1];

                $QuerylastPackage->update($upFailArr);
            }
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
            $existsPkg = DeliveryPackage::where('package_id',$packageId)->where('delivery_id',$deliveryId)
            ->where('delay_count',0)->where('has_swap',0)
            ->where('is_deleted',0)
            ->first();
            if($existsPkg) {
                if($driverId == $existsPkg->driver_id){
                    // $isNewPkg = true;
                    // if($existsPkg->status_id == 11) $delay = 1;
                    // if($statusId){
                        $toDelete = ($existsPkg->status_id == 6);
                        $existsPkg->update([
                            'is_deleted' => $toDelete ? 1 : 0,
                            'deleted_uid' => $toDelete ? $user->id:null,
                            'deleted_datetime' => $toDelete ? now() : null,
                            'delay_count' => $delay,
                            // 'status_id' => $statusId,
                            // 'assign_uid' => $action == 'assign' ? $user->id : null
                        ]);
                    // }
                }else $newPackageCount +=1;
            }else $newPackageCount +=1;

            $updateArr = [
                'driver_id' => $driverId,
                'delay_count' => $delay,
                'status_id' => 14,
                'is_completed' => false,
                'finished' => false,
                'package_count' => $newPackageCount,
                'update_uid' => $user->id,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id,
            ];
            $pendingTrip->update($updateArr);
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
                'order_id' => $package->order_id,
                'payer' => $package->payer,
                'receiver_phone' => $package->receiver_phone,
                'receiver_address' => $package->receiver_address,
                'zone_code' => $package->zone_code,
                'zone_name' => $package->zone_name,
                'merchant_id' => $package->merchant_id,
                'delivery_type' => $package->delivery_type,
                'product_type' => $package->product_type,
                'notes' => $notes,
                'assign_uid' => $action == 'assign' ? $user->id : null,
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
