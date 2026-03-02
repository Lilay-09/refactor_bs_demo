<?php

namespace App\Services;
use App\Enums\ImageDirectory;
use App\Enums\TrackingStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Traits\LogsActivity;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PickupCenterServiceImpl implements PickupCenterService
{
    // Your service methods go here
    private string $orderCodePrefix;
    private string $packageCodePrefix;

    public function __construct(){
        $this->orderCodePrefix = config('app.code_prefix').'XO';
        $this->packageCodePrefix = config('app.code_prefix').'XP';
    }

    public function packageValidation(Request $req){
        return validator($req->all(),[
            'photo_id' => 'nullable|int',
            'package_name' => 'nullable|string|max:100',
            'merchant_id' => 'required',
            'image_id' => 'nullable',
            'image' => 'nullable',
            'photo' => 'nullable',
            'product_type' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'pass_duplicate_phone' => 'required|boolean',
            'price_khr' => 'numeric|min:0',
            'driver_cod_usd' => 'nullable|numeric|min:0',
            'driver_cod_khr' => 'nullable|numeric|min:0',
            'pickup_uid' => 'nullable|int',
            'dim_z' => 'nullable|numeric',
            'dim_y' => 'nullable|numeric',
            'dim_x' => 'nullable|numeric',
            'status_id' => 'nullable|int',
            'taxi_fee' => 'numeric|min:0',
            'other_fee' => 'numeric|min:0',
            'failure_notes' => 'nullable|string|max:250',
            'payer' => 'required|in:sender,receiver',
            'cod' => 'required|in:0,1',
            'branch_id' => 'int',
            'warehouse_id' => 'int',
            'receiver_address' => 'nullable|string',
            'zone_code' => 'required|string|exists:zones,zone_code',
            // 'zone_id' => 'required|string',
            'remarks' => 'nullable|string|max:250',
            'receiver_phone' => 'required|string|min:9',
            'receiver_name' => 'nullable|string',
            'pickup_notes' => 'nullable|string',
            'noted' => 'nullable|string',
            'extra_charge' => 'nullable|numeric',
            'actual_kg' => 'nullable|numeric',
            'billed_kg' => 'nullable|numeric',
            'delivery_type' => 'required|in:fast,normal',
        ]);
    }
    public function orderValidation(Request $req){
        $vehicleTypes = implode(',',VehicleType::where('is_deleted',0)->pluck('name_en')->toArray());
        return validator($req->all(),[
            'merchant_id' => 'required',
            'warehouse_id' => 'required|int|exists:warehouses,id',
            'product_type' => 'nullable|string|exists:product_types,name',
            'qty' => 'required|int|min:1',
            'vehicle_type' => 'required|in:'.$vehicleTypes,
            'branch_id' => 'required',
            'driver_id' => 'nullable',
            'loc_lat' => 'nullable|numeric',
            'loc_lng' => 'nullable|numeric',
            'delivery_type' => 'nullable|string',
            'pickup_address_google_map' => 'nullable|string',
            'pin_address' => 'nullable|string',
            'pickup_address' => 'nullable|string|max:300',
            'details' => 'nullable|array',
            'images' => 'nullable'
        ],[
            'merchant_id.required' => 'Please select the sender',
            'vehicle_type.in' => 'Please select one of ('.$vehicleTypes.')',
            'warehouse_id.required' => 'Please select the warehouse',
            'branch_id.required' => 'Please select the branch',
            'qty.required' => 'Please enter number of package'
        ]);
    }

    public function createOrder(Request $req,object $user): object{
        $validate = $this->orderValidation($req);
        $companyId = $user->company_id;
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first(),$validate->errors());
        $inputs = $validate->validated();
        $merchantId = $inputs['merchant_id'];
        $validMerchant = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','merchant')
        ->select(['id','username','phone'])
        ->find($merchantId);
        if(!$validMerchant) return DataResponse::ValidateFail('Invalid sender identity!');
        $userType = $user->account_type;
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        // $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['booking_channel'] = $userType;
        $details = $inputs['details'] ?? [];
        $images = $inputs['images'] ?? [];
        $inputs['original_qty'] = $inputs['qty'];
        $inputs['order_datetime'] = now();
        $productType = $inputs['product_type'] ?? null;
        if($userType == 'driver') $inputs['driver_id'] = $user->id;
        $driverId = $inputs['driver_id'] ?? null;
        if($driverId == 0){
            $driverId = null;
            unset($inputs['driver_id']);
        }
        $statusId = 3; //** accepted for pick up*/
        if(!$driverId) $statusId = 1; //** available for pick */
        else{
            $inputs['pickup_datetime'] = now();
            $inputs['assign_uid'] = $user->id;
            $validDriver = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->find($driverId);
            if(!$validDriver) return DataResponse::ValidateFail('Invalid driver identity!');
            if($validDriver->lock) {
                return DataResponse::ValidateFail(__('messages.info',[
                    'info' => 'Driver is currently inactive',
                    'khInfo' => 'អ្នកដឹកជញ្ជូនត្រូវបានឈប់ដំណើរការ'
                ]));
            }
            // if($validDriver->vehicle_type != $inputs['vehicle_type']) return DataResponse::ValidateFail(__('messages.error',['info' => 'Driver vehicle type and chosen vehicle type is different!']));
        }
        $dateTime = Helper::getDateTime();
        if($userType == 'driver') {
            $inputs['tracking_notes'] = 'Driver create order ('.$dateTime.')';
        }
        else if($userType == 'merchant') {
            $inputs['tracking_notes'] = 'Merchant create order ('.$dateTime.')';
        }
        else if($userType == 'admin') $inputs['tracking_notes'] = 'Admin create order ('.$dateTime.')';
        $deleteImgs = [];
        $pickupAddress = $inputs['pickup_address'] ?? null;
        $pickup_address_google_map = $inputs['pickup_address_google_map'] ?? $inputs['pin_address'] ?? null;
        if(Helper::isShortGoogleMapUrl($pickup_address_google_map)){
            $geoRes = new GeoResolverService();
            $xM = $geoRes->fromShortUrl($pickup_address_google_map);
            $inputs['loc_lat'] = $xM['lat'] ?? 0;
            $inputs['loc_lng'] = $xM['lng'] ?? 0;
        }else{
            $latLng = Helper::getLatLongFromGoogleMapsUrl($pickup_address_google_map);
            $inputs['loc_lat'] = (float) ($inputs['loc_lat'] ?? $latLng->latitude);
            $inputs['loc_lng'] = (float) ($inputs['loc_lng'] ?? $latLng->longitude);
        }
        // $lang = $req->lang;
        $inputs['delivery_type'] = $inputs['delivery_type'] ?? 'normal';
        if(!$pickupAddress) $inputs['pickup_address'] = $latLng->address;
        try{
            DB::beginTransaction();
            $createOrder = Order::create($inputs);
            // $createOrder->skipLog = true;
            if(!$createOrder) return DataResponse::Error('Fail to create order!');
            $orderId = $createOrder->id;
            $code = Helper::generateCode($this->orderCodePrefix,$orderId,'',8);
            // $statusId = $inputs['status_id'];
            if(isset($details[0])){
                if($userType == 'driver') $statusId = 4;
                $isMobile = $userType !== 'admin';
                // if($inputs['qty'] != count($details)) return DataResponse::ValidateFail('Your quantity is not matching the details');
                foreach($details as $d){
                    $d['merchant_id'] = $merchantId;
                    $d['product_type'] = $productType;
                    $price = $d['price'] ?? 0;
                    $d['cod'] = 0;
                    if($isMobile && $price > 0){
                        $d['cod'] = 1;
                    }
                    $dReq = new Request($d);
                    $savePkg = $this->createOrUpdatePackage($dReq,$user,null,$orderId);
                    if($savePkg->error) return $savePkg;
                }
            }
            $createOrder->update([
                'code' => $code,
                'status_id' => $statusId
            ]);

            // LogsActivity::logActivity([
            //     'action'   => 'Order created with code and status updated',
            //     'module'   => 'Order',
            //     'ref_id'   => $createOrder->id,
            //     'ref_code' => $code,
            //     'after'    => [
            //         'code' => $code,
            //         'status_id' => $statusId
            //     ]
            // ]);
            $saveOrderImages = [];
            if(isset($images[0])){
                foreach($images as $idx => $photo){
                    $isValidUpload = Helper::isValidUploadImage($photo,0.8);
                    if($isValidUpload->error) return DataResponse::ValidateFail($isValidUpload->message.', check your Image #'.($idx + 1));
                    $img = Helper::saveImageFile($photo,$companyId,ImageDirectory::ORDER_IMAGE->value,date('Y-m-d'));
                    //** if something went wrong so this will take action on catch block */
                    $deleteImgs[] = $img->filename;
                    $saveOrderImages[] = [
                        'order_id' => $orderId,
                        'photo_file_name' => $img->filename,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $companyId,
                        'branch_id' => $user->branch_id,
                    ];
                    // OrderImage::create();
                }
            }
            if(!empty($saveOrderImages)){
                OrderImage::insert($saveOrderImages);
            }
            $inputQty = $inputs['qty'];
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$merchantId);
            $clmsgReq = new Request([
                'topic' => $topics->private,
                'title' => __('notification.create_order.title'),
                'body' => __('notification.create_order.body',[
                    'create_user' => $user->account_type,
                    'count' => $inputQty
                ]),//$lang == 'km' ? ucfirst($user->account_type).' បានបង្កើតការកម្មង់ឲ្យ​អ្នកចំនួន'.$inputQty.'កញ្ចប់' : ucfirst($user->account_type).' has created an order for you.',
                'type' => 'private',
                'target_uid' => $merchantId
            ]);
            // SendNotificationJob::dispatch($clmsgReq, $user);
            $queueFCMName = config('queue_job_names.'.config('app.env').'.notification');
            SendNotificationJob::dispatch($clmsgReq, $user)->onQueue($queueFCMName);
            if($driverId){
                $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$driverId);
                $notifReq = new Request([
                    'topic' => $topics->private,//$driverId ? $topics->private:$topics->public,
                    'type' =>  'private',//$driverId ? 'private':'public',
                    'target_uid' => $driverId,
                    'title' => __('notification.assign_order.title'),//$notifTitle,
                    'body' => __('notification.assign_order.body',[
                        'merchant' => $validMerchant->username,
                        'create_user' => $user->username,
                        'count' => $inputQty
                    ])
                ]);
                // $clmsg->sendNotificationByTopic($notifReq,$user);
                // SendNotificationJob::dispatch($notifReq, $user);
                SendNotificationJob::dispatch($notifReq, $user)->onQueue($queueFCMName);
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Order created ('.$code.')',
                'khInfo' => 'បានបង្កើតការកម្មង់លេខ ('.$code.')'
            ]));
        }catch(Exception $e){
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            foreach($deleteImgs as $img){
                Helper::deleteImageFile($img,$companyId,'order_image');
            }
            DB::rollBack();
            return DataResponse::Error('Failed to create a new order');
        }
    }

    // public function calculatePackageFee($zone_code,$price,$billedKg,$actualKg,$payer){
    //     $priceList = GeneralSettingService::getZonePriceByCode($zone_code);
    //     if(!$priceList) return DataResponse::NotFound('Zone price not found');
    //     $zPrice = $priceList->price > 0 ? $priceList->price : $priceList->base_fee;
    //     $selectKg = $billedKg ?? $actualKg;
    //     $additionalPrice = 0;
    //     $merchant_total = $zPrice;
    //     if($selectKg >= $priceList->above_kg){
    //         $additionalPrice = $priceList->above_kg_price;
    //     }else if($selectKg < $priceList->above_kg && $selectKg >= $priceList->below_kg){
    //         $additionalPrice = $priceList->below_kg_price;
    //     }

    //     $driverTotal = $price;
    //     $merchant_total += $additionalPrice;
    //     $total = $price + $additionalPrice;
    //     if($payer == 'receiver'){
    //         $total += $zPrice;
    //     }

    //     return (object)[
    //         'delivery_fee' => $zPrice,
    //         'driver_total' => $driverTotal,
    //         'merchant_total' => $merchant_total,
    //         'total' => $total
    //     ];
    // }

    public function updateOrderQty($orderId,$count=null){
        $count = $count ? $count : Package::where('is_deleted',0)->where('order_id',$orderId)->count();
        Order::where('is_deleted',0)->find($orderId)->update([
            'qty' => $count
        ]);
    }

    public static function getDriverTotal($cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$taxi=0,$otherFee=0){
        $total = $additional_fee;
        if($cod) $total += $price;
        if($payer == 'receiver') {
            $total += $extra_charge;
            $total += $deliveryFee;
            $total += $otherFee;
        }
        return $total - $taxi;
    }

    public static function getTotal($type,$cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$otherFee=0,$taxi=0){
        $total = $additional_fee;
        if($type == 'driver'){
            if($cod) $total += $price;
            if($payer == 'receiver') {
                $total += $extra_charge;
                $total += $deliveryFee + $otherFee;
            }
        }else if($type == 'merchant'){
            if($payer == 'sender') {
                $total += $extra_charge;
                $total += $deliveryFee + $otherFee;
            }
        }
        return $total;
    }


    public static function getFees($payer,$deliveryFee,$additional_fee,$extra_charge,$pair='receiver'){
        $total = 0;
        if($payer == $pair) {
            $total += $deliveryFee;
            $total += $extra_charge;
        }
        return $total;
    }


    /**
     * Summary of createOrUpdatePackage
     * @param \Illuminate\Http\Request $req
     * @param mixed $user ** This one is auth user *SESSION*
     * @param int $packageId => it depends on action **IF UPDATE packageId must be provided
     * @param int $orderId => optional *-- might use only in pickup center module --*
     * @param ?array $statusIds => status can be differenct by module | By Default $statusIds=[1,7] = available for pick up or package is pending,
     * @param ?callback $whereClause => for additional queries condition
     * @return object
     *
     *  => ------ for reusable on action update package --------
     */
    public function createOrUpdatePackage(Request $req,$user,?int $packageId,?int $orderId,?array $statusIds=[1,7],?callable $whereClause=null): object{
        if($orderId){
            $order = Order::where('is_deleted',0)->select(['merchant_id','delivery_type','warehouse_id','branch_id','driver_id'])->find($orderId);
            $req->merge([
                'merchant_id' => $order->merchant_id,
                'delivery_type' => $req->delivery_type ?? $order->delivery_type,
                'product_type' => $req->product_type ?? $order->product_type,
                'warehouse_id' => $order->warehouse_id,
                'branch_id' => $order->branch_id,
                'pickup_uid' => $order->driver_id,
            ]);
            if(!$order) return DataResponse::NotFound('Order not found');
        }
        // Log::info('Package Request: '.json_encode($req->all()));
        $validate = $this->packageValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $warehouseId = $inputs['warehouse_id'] ?? null;
        // $branchId = $inputs['branch_id'];
        $warehouse = Warehouse::where('is_deleted',false)
        ->find($warehouseId);
        if(!$warehouse){
            return DataResponse::NotFound(__('messages.not_found',['info' => 'Warehouse','khInfo' => 'ឃ្លាំង']));
        }
        $passDuplicatePhone = $inputs['pass_duplicate_phone'];
        if($orderId) $inputs['merchant_id'] = $order->merchant_id;
        $inputs['update_uid'] = $user->id;
        if($orderId) $inputs['order_id'] = $orderId;
        $price = $inputs['price'] ?? 0;
        $inputs['price'] = $price;
        $actualKg = $inputs['actual_kg'] ?? 0;
        $billedKg = $inputs['billed_kg'] ?? 0;
        $inputs['actual_kg'] = $actualKg;
        if(isset($inputs['noted'])) $inputs['pickup_notes'] = $inputs['noted'];
        $payer = $inputs['payer'];
        $inputs['pickup_datetime'] = now();
        $inputs['billed_kg'] = $actualKg;
        $cod = $inputs['cod'];
        $codUsd = $inputs['cod_usd'] ?? 0;
        $inputs['cod_usd'] = $codUsd;
        $codKhr = $inputs['cod_khr'] ?? 0;
        $inputs['cod_khr'] = $codKhr;
        $dCodUsd = $inputs['driver_cod_usd'] ?? 0;
        $inputs['driver_cod_usd'] = $dCodUsd;
        $dCodKhr = $inputs['driver_cod_khr'] ?? 0;
        $inputs['driver_cod_khr'] = $dCodKhr;
        $inputs['extra_charge'] = $inputs['extra_charge'] ?? 0;
        $zoneCode = $inputs['zone_code'];
        $inputs['delivery_type'] = $inputs['delivery_type'] ?? 'normal';
        $inputs['booking_channel'] = 'admin';
        $taxiFee = $inputs['taxi_fee'] ?? 0;
        $imageId = $inputs['image_id'] ?? null;
        $image = $inputs['image'] ?? $inputs['photo'] ?? null;
        // $inputs['tracking_notes'] = '['.$user->id.']Admin ('.$user->username.') add new package ('.date('d-M-Y h:i:s A').')';
        if($user->account_type == 'driver') $inputs['booking_channel'] = 'driver';
        if($user->account_type == 'merchant') {
            $inputs['cod'] = $price > 0 ? true : false;
            $inputs['booking_channel'] = 'merchant';
        }
        $zone = Zone::where('zone_code',$zoneCode)
        ->orderByDesc('id')->where('is_deleted',0)->first(['zone_name','zone_code','parent_id']);
        if(!$zone) return DataResponse::NotFound('Zone not found');
        $zone->load('parent');
        $inputs['zone_name'] = $zone->zone_name;
        $inputs['main_zone_code'] = $zone->parent?->zone_code;
        $inputs['main_zone_name'] = $zone->parent?->zone_name;
        $extraCharge = $inputs['extra_charge'] ?? 0;
        $otherFee = $inputs['other_fee'] ?? 0;
        $calPrice = GeneralSettingService::calculatePackageFee($zoneCode,$price,$billedKg,$actualKg,$payer,$cod,$extraCharge,$user,$taxiFee,$inputs['merchant_id'],null,$otherFee);
        if($calPrice->error) return $calPrice;
        $inputs['driver_total'] = $calPrice->driver_total;
        $inputs['merchant_total'] = $calPrice->merchant_total;
        $inputs['delivery_fee'] = $calPrice->delivery_fee;
        $productType = $inputs['product_type'] ?? ($orderId ? $order->product_type:null);
        $inputs['product_type'] = $productType;

        if(!$packageId){
            if(!$passDuplicatePhone){
                $checkDupPhone = $this->checkDuplicateReceiverPhoneByOrder($orderId,$inputs['receiver_phone']);
                if($checkDupPhone) {
                    return DataResponse::JsonResult(data:null,message:'Duplicated phone number',additionalKey:[
                        'duplicate_number' => true
                    ],error:true);
                }
            }
            $inputs['status_id'] = 7;
            $inputs['create_uid'] = $user->id;
            if($user->account_type == 'merchant'){
                $inputs['tracking_notes'] = 'Merchant add new package ('.date('d-M-Y h:i:s A').')';
            }else if($user->account_type == 'driver'){
                $inputs['tracking_notes'] = 'Driver add new package ('.date('d-M-Y h:i:s A').')';
            }
            $shortcut = $warehouse?->shortcut ?? null;
            if(!$shortcut){
                return DataResponse::ValidateFail("Please set a shortcut for your warehouse — it’ll be used when generating package codes.");
            }
            $createPackage = Package::create($inputs);
            if(!$createPackage) return DataResponse::Error(__('messages.Fail to create package'));
            if($imageId){
                OrderImage::find($imageId)->update([
                    'package_id' => $createPackage->id,
                    'user_type' => $user->account_type,
                ]);
                $inputs['photo_id'] = $imageId;
                $inputs['image_date'] = now();
            }
            if($image){
                $isValidUpload = Helper::isValidUploadImage($image,0.8);
                if($isValidUpload->error) return DataResponse::ValidateFail($isValidUpload->message);
                $imageDate = date('Y-m-d');
                $img = Helper::saveImageFileOrBase64($image,$user->company_id,ImageDirectory::ORDER_IMAGE->value,$imageDate);

                if($img->filename){
                    $imgId = OrderImage::insertGetId([
                        'package_id' => $createPackage->id,
                        'order_id' => $orderId,
                        'user_type' => $user->account_type,
                        'photo_file_name' => $img->filename,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id,
                    ]);
                    $createPackage->update([
                        'photo_id' => $imgId,
                        'image_date' => $imageDate,
                    ]);
                }
            }
            $qrCode = Helper::generateBarcodeString($createPackage->id,$user->company_id,$this->packageCodePrefix.$warehouse->shortcut);
            $createPackage->update([
                'qr_code' => $qrCode
            ]);
            $count = Package::where('order_id',$orderId)->where('is_deleted',0)->count();
            if($count > $order->qty){
                $this->updateOrderQty($orderId,$count);
            }
            return DataResponse::JsonResult(null,false,__('messages.created',['info' => 'Package Number ('.$qrCode.').']));
        }else{
            $qP = Package::where('is_deleted',0)->whereIn('status_id',$statusIds);
            if($whereClause){
                $qP->$whereClause;
            }

            if($imageId){
                OrderImage::find($imageId)->update([
                    'package_id' => $packageId,
                    'user_type' => $user->account_type,
                ]);
                $inputs['photo_id'] = $imageId;
                $inputs['image_date'] = now();
            }

            if(!$passDuplicatePhone){
                $checkDupPhone = $this->checkDuplicateReceiverPhoneByOrder($orderId,$inputs['receiver_phone'],$packageId);
                if($checkDupPhone) {
                    return DataResponse::JsonResult(data:null,message:'Duplicated phone number',additionalKey:[
                        'duplicate_number' => true
                    ],error:true);
                }
            }

            $package = $qP->find($packageId);
            if($image){
                $isValidUpload = Helper::isValidUploadImage($image,0.8);
                if($isValidUpload->error) return DataResponse::ValidateFail($isValidUpload->message);
                $imageDate = date('Y-m-d');
                $img = Helper::saveImageFileOrBase64($image,$user->company_id,ImageDirectory::ORDER_IMAGE->value,$imageDate);
                if($img->filename){
                    $imgId = OrderImage::insertGetId([
                        'package_id' => $packageId,
                        'order_id' => $orderId,
                        'user_type' => $user->account_type,
                        'photo_file_name' => $img->filename,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id,
                    ]);
                    $inputs['photo_id'] = $imgId;
                    $inputs['image_date'] = $imageDate;
                }
            }
            $inputs['status_id'] = $package->status_id;
            if(!$package) return DataResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
            // if($package->status_id == 5) return DataResponse::Forbidden(__('messages.no_access',['info' => 'This package has already assigned to driver']));
            $package->update($inputs);
            return DataResponse::JsonResult(null,false,__('messages.updated'));
        }
    }

    private function checkDuplicateReceiverPhoneByOrder(int $orderId, string $phone, ?int $pkgId = null): bool
    {
        if ($pkgId) {
            $package = Package::where('is_deleted', false)
                ->where('id', $pkgId)
                ->where('order_id', $orderId)
                ->first();

            if (!$package) {
                // If package not found, treat as no duplicate
                return false;
            }

            // If phone not changed, no duplicate
            if ($package->receiver_phone === $phone) {
                return false;
            }

            // Check if another package has the same phone
            return Package::where('is_deleted', false)
                ->where('order_id', $orderId)
                ->where('id', '!=', $pkgId)
                ->where('receiver_phone', $phone)
                ->exists();
        }

        // For new package, check if any package has the phone
        return Package::where('is_deleted', false)
            ->where('order_id', $orderId)
            ->where('receiver_phone', $phone)
            ->exists();
    }


    public function createOrUpdateTrip($driverId,$packageId,$vehicleType,$user,$notes,$statusId,$action=null,$package=null){
        // $today = date('Y-m-d');
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
                'status_id' => TrackingStatus::ON_DELIVERY_TRIP->value,
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
                'delivery_id' => $deliveryId,
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


    public function deleteOrderImage(int $orderId,int $imageId):object{
        $foundImage = OrderImage::where('order_id',$orderId)->find($imageId);
        if(!$foundImage) {
            return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Image',
                'khInfo' => 'រូបភាព'
            ]));
        }
        Helper::deleteImageFile($foundImage->photo_file_name,1,ImageDirectory::ORDER_IMAGE->value,$foundImage->updated_at->format('Y-m-d'));
        $foundImage->delete();
        return DataResponse::JsonResult(null,false);
    }

    public function replaceOrderImage(object $user,Request $req): object{
        $image = $req->image ?? null;
        $packageId = $req->package_id ?? null;
        if(!$image) {
            return DataResponse::ValidateFail(__('messages.error',[
                'info' => 'Please provide an image',
                'khInfo' => 'សូមផ្ដល់រូបភាព'
            ]));
        }
        $isValidUpload = Helper::isValidUploadImage($image,0.8);
        if($isValidUpload->error) {
            return DataResponse::ValidateFail($isValidUpload->message);
        }
        $imageDate = date('Y-m-d');
        $img = Helper::saveImageFileOrBase64($image,$user->company_id,ImageDirectory::ORDER_IMAGE->value,$imageDate);
        if(!$img->filename) {
            return DataResponse::Error(__('messages.error',[
                'info' => 'Fail to save image',
                'khInfo' => 'រក្សាទុករូបភាពមិនបាន'
            ]));
        }
        $foundImage = OrderImage::find($req->image_id);
        if(!$foundImage) {
            return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Image',
                'khInfo' => 'រូបភាព'
            ]));
        }
        Helper::deleteImageFile($foundImage->photo_file_name,$user->company_id,ImageDirectory::ORDER_IMAGE->value,$foundImage->created_at->format('Y-m-d'));
        $foundImage->update([
            'photo_file_name' => $img->filename,
            'package_id' => $packageId,
            'update_uid' => $user->id,
            'image_date' => $imageDate,
        ]);
        return DataResponse::JsonResult(null,false,__('messages.info',[
            'info' => 'Image replaced successfully',
            'khInfo' => 'បានជំនួសរូបភាពដោយជោគជ័យ'
        ]));
    }
}
