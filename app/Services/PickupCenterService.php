<?php

namespace App\Services;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Models\PriceList;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Zone;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use Str;

class PickupCenterService
{
    // Your service methods go here

    public function packageValidation(Request $req){
        return validator($req->all(),[
            'photo_id' => 'nullable|int',
            'package_name' => 'nullable|string|max:100',
            'merchant_id' => 'required',
            'product_type' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'dim_z' => 'nullable|numeric',
            'dim_y' => 'nullable|numeric',
            'dim_x' => 'nullable|numeric',
            'status_id' => 'nullable|int',
            'taxi_fee' => 'nullable|numeric',
            'failure_notes' => 'nullable|string|max:250',
            'payer' => 'required|in:sender,receiver',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string',
            'zone_code' => 'required|string|exists:zones,zone_code',
            // 'zone_id' => 'required|string',
            'remarks' => 'nullable|string|max:250',
            'receiver_phone' => 'required|string|min:9',
            'receiver_name' => 'nullable|string',
            'pickup_notes' => 'nullable|string',
            'extra_charge' => 'nullable|numeric',
            'actual_kg' => 'nullable|numeric',
            'billed_kg' => 'nullable|numeric',
            'delivery_type' => 'nullable|in:fast,normal',
        ]);
    }
    public function orderValidation(Request $req){
        $vehicleTypes = implode(',',VehicleType::where('is_deleted',0)->pluck('name')->toArray());
        return validator($req->all(),[
            'merchant_id' => 'required',
            'warehouse_id' => 'nullable|int|exists:warehouses,id',
            'product_type' => 'nullable|string|exists:product_types,name',
            'qty' => 'required|int|min:1',
            'vehicle_type' => 'required|in:'.$vehicleTypes,
            'driver_id' => 'nullable',
            'loc_lat' => 'nullable|numeric',
            'loc_lng' => 'nullable|numeric',
            'pickup_address_google_map' => 'nullable|string',
            'pin_address' => 'nullable|string',
            'pickup_address' => 'nullable|string|max:300',
            'details' => 'nullable|array',
            'images' => 'nullable'
        ],[
            'merchant_id.required' => 'Please select the sender',
            'vehicle_type.in' => 'Please select one of ('.$vehicleTypes.')',
            'warehouse_id.required' => 'Please select the warehouse',
            'qty.required' => 'Please enter number of package'
        ]);
    }

    public function createOrder(Request $req,$user){
        $validate = $this->orderValidation($req);
        $companyId = $user->company_id;
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first(),$validate->errors());
        $inputs = $validate->validated();
        $merchantId = $inputs['merchant_id'];
        $validMerchant = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','merchant')->find($merchantId);
        if(!$validMerchant) return DataResponse::ValidateFail('Invalid sender identity!');
        $userType = $user->account_type;
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['booking_channel'] = $userType;
        $details = $inputs['details'] ?? [];
        $images = $inputs['images'] ?? [];
        $inputs['original_qty'] = $inputs['qty'];
        $inputs['order_datetime'] = now();
        $productType = $inputs['product_type'] ?? null;
        $inputs['warehouse_id'] = GeneralSettingService::getWarehouse($user)->id;
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
            $validDriver = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->find($driverId);
            if(!$validDriver) return DataResponse::ValidateFail('Invalid driver identity!');
            if($validDriver->vehicle_type != $inputs['vehicle_type']) return DataResponse::ValidateFail(__('messages.error',['info' => 'Driver vehicle type and chosen vehicle type is different!']));
        }
        $dateTime = Helper::getDateTime();
        if($userType == 'driver') $inputs['tracking_notes'] = 'Driver create order ('.$dateTime.')';
        else if($userType == 'merchant') $inputs['tracking_notes'] = 'Merchant create order ('.$dateTime.')';
        else if($userType == 'admin') $inputs['tracking_notes'] = 'Admin create order ('.$dateTime.')';
        $deleteImgs = [];
        $pickupAddress = $inputs['pickup_address'] ?? null;
        $pickup_address_google_map = $inputs['pickup_address_google_map'] ?? $inputs['pin_address'] ?? null;
        $latLng = Helper::getLatLongFromGoogleMapsUrl($pickup_address_google_map);
        $inputs['loc_lat'] = $inputs['loc_lat'] ?? $latLng->latitude;
        $inputs['loc_lng'] = $inputs['loc_lng'] ?? $latLng->longitude;
        if(!$pickupAddress) $inputs['pickup_address'] = $latLng->address;
        DB::beginTransaction();
        try{
            $createOrder = Order::create($inputs);
            if(!$createOrder) return DataResponse::Error('Fail to create order!');
            $orderId = $createOrder->id;
            $code = Helper::generateCode('JS',$orderId,'',8);


            // $statusId = $inputs['status_id'];
            if(isset($details[0])){
                if($userType == 'driver') $statusId = 4;
                // if($inputs['qty'] != count($details)) return DataResponse::ValidateFail('Your quantity is not matching the details');
                foreach($details as $d){
                    $d['merchant_id'] = $merchantId;
                    $d['product_type'] = $productType;
                    $dReq = new Request($d);
                    $savePkg = $this->createOrUpdatePackage($dReq,$user,null,$orderId);
                    if($savePkg->error) return $savePkg;
                }
            }
            Order::find($orderId)->update([
                'code' => $code,
                'status_id' => $statusId
            ]);

            if(isset($images[0])){
                foreach($images as $photo){
                    $img = Helper::saveImageFile($photo,$companyId,'order_image');
                    $deleteImgs[] = $img->filename;
                    OrderImage::create([
                        'order_id' => $orderId,
                        'photo_file_name' => $img->filename,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $companyId,
                        'branch_id' => $user->branch_id,
                    ]);
                }
            }
            $clmsg = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$merchantId);
            $clmsgReq = new Request([
                'topic' => $topics->private,
                'title' => 'Create Order',
                'body' => ucfirst($user->account_type).' has created an order for you.',
                'type' => 'private',
                'target_uid' => $merchantId
            ]);
            $clmsg->sendNotificationByTopic($clmsgReq,$user);
            // if($driverId){
                $notif = new CloudMessagingService();
                $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$driverId);
                $notifBody = "$validMerchant->user_name: ".$inputs['qty']."PCS, \nPickup Address:".Str::limit($pickupAddress, 25, '...');
                $notifTitle = 'New Order Available';
                if($driverId) {
                    $notifBody = 'You have been assigned to deliver the order('.$code.') has '.$inputs['qty'].' package(s).';
                    $notifTitle = 'Assigned Order';
                }
                $notifReq = new Request([
                    'topic' => $driverId ? $topics->private:$topics->public,
                    'type' => $driverId ? 'private':'public',
                    'target_uid' => $driverId,
                    'title' => $notifTitle,
                    'body' => $notifBody
                ]);
                $notif->sendNotificationByTopic($notifReq,$user);
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Order created ('.$code.')',
                'khInfo' => 'បានបង្កើត ('.$code.')'
            ]));
        }catch(Exception $e){
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            foreach($deleteImgs as $img){
                Helper::deleteImageFile($img,$companyId,'order_image');
            }
            DB::rollBack();
            return DataResponse::Error('Faile to create a new order');
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

    public static function getDriverTotal($cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$taxi=0){
        $total = $additional_fee;
        if($cod) $total += $price;
        if($payer == 'receiver') {
            $total += $extra_charge;
            $total += $deliveryFee;
        }
        return $total - $taxi;
    }

    public static function getTotal($type,$cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$taxi=0){
        $total = $additional_fee;
        if($type == 'driver'){
            if($cod) $total += $price;
            if($payer == 'receiver') {
                $total += $extra_charge;
                $total += $deliveryFee;
            }
        }else if($type == 'merchant'){
            if($payer == 'sender') {
                $total += $extra_charge;
                $total += $deliveryFee;
            }
        }
        return $total;
    }


    public static function getFees($payer,$deliveryFee,$additional_fee,$extra_charge){
        $total = $extra_charge + $additional_fee;
        if($payer == 'receiver') $total += $deliveryFee;
        return $total;
    }


    /**
     * Summary of createOrUpdatePackage
     * @param \Illuminate\Http\Request $req
     * @param mixed $user ** This one is auth user *SESSION*
     * @param mixed $packageId => it depends on action **IF UPDATE packageId must be provided
     * @param mixed $orderId => optional *-- might use only in pickup center module --*
     * @param mixed $statusIds => status can be differenct by module | By Default $statusIds=[1,7] = available for pick up or package is pending,
     * @param mixed $whereClause => for additional queries condition
     * @return object
     *
     *  => ------ for reusable on action update package --------
     */
    public function createOrUpdatePackage(Request $req,$user,$packageId=null,$orderId=null,$statusIds=[1,7],$whereClause=null){
        if($orderId){
            $order = Order::where('is_deleted',0)->find($orderId);
            $req->merge(['merchant_id' => $order->merchant_id]);
            if(!$order) return DataResponse::NotFound('Order not found');
        }
        $validate = $this->packageValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        if($orderId) $inputs['merchant_id'] = $order->merchant_id;
        $inputs['update_uid'] = $user->id;
        if($orderId) $inputs['order_id'] = $orderId;
        $price = $inputs['price'] ?? 0;
        $inputs['price'] = $price;
        $actualKg = $inputs['actual_kg'] ?? 0;
        $billedKg = $inputs['billed_kg'] ?? 0;
        $inputs['actual_kg'] = $actualKg;
        $payer = $inputs['payer'];
        $inputs['billed_kg'] = $actualKg;
        $cod = $inputs['cod'];
        $inputs['extra_charge'] = $inputs['extra_charge'] ?? 0;
        $zoneCode = $inputs['zone_code'];
        $inputs['delivery_type'] = $inputs['delivery_type'] ?? 'normal';
        $inputs['booking_channel'] = 'admin';
        $taxiFee = $inputs['taxi_fee'] ?? 0;
        $inputs['tracking_notes'] = '['.$user->id.']Admin ('.$user->user_name.') add new package ('.date('d-M-Y h:i:s A').')';
        if($user->account_type == 'driver') $inputs['booking_channel'] = 'driver';
        if($user->account_type == 'merchant') $inputs['booking_channel'] = 'merchant';
        $zoneName = Zone::where('zone_code',$zoneCode)->value('zone_name');
        $inputs['zone_name'] = $zoneName;
        $extraCharge = $inputs['extra_charge'] ?? 0;
        $calPrice = GeneralSettingService::calculatePackageFee($zoneCode,$price,$billedKg,$actualKg,$payer,$cod,$extraCharge,$user,$taxiFee,$inputs['merchant_id']);
        if($calPrice->error) return $calPrice;
        $inputs['driver_total'] = $calPrice->driver_total;
        $inputs['merchant_total'] = $calPrice->merchant_total;
        $inputs['delivery_fee'] = $calPrice->delivery_fee;
        $productType = $inputs['product_type'] ?? ($orderId ? $order->product_type:null);
        $inputs['product_type'] = $productType;
        // if(!$productType) unset($inputs['product_type']);
        // Log::error($productType);
        if(!$packageId){
            $inputs['status_id'] = 7;
            $inputs['create_uid'] = $user->id;
            if($user->account_type == 'merchant'){
                $inputs['tracking_notes'] = 'Merchant add new package ('.date('d-M-Y h:i:s A').')';
            }else if($user->account_type == 'driver'){
                $inputs['tracking_notes'] = 'Driver add new package ('.date('d-M-Y h:i:s A').')';
            }
            $createPackage = Package::create($inputs);
            if(!$createPackage) return DataResponse::Error(__('messages.Fail to create package'));
            $qrCode = Helper::generateBarcodeString($createPackage->id,$user->company_id);
            Package::find( $createPackage->id)->update([
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
            $package = $qP->find($packageId);
            $inputs['status_id'] = $package->status_id;
            if(!$package) return DataResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
            // if($package->status_id == 5) return DataResponse::Forbidden(__('messages.no_access',['info' => 'This package has already assigned to driver']));
            $package->update($inputs);
            return DataResponse::JsonResult(null,false,__('messages.updated'));
        }
    }
}
