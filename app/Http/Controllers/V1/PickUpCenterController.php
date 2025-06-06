<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Models\User;
use App\Services\CloudMessagingService;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterServiceImpl;
use App\Services\UserService;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;

class PickUpCenterController extends Controller
{
    //
    protected $pkupService;
    public function __construct(PickupCenterServiceImpl $pickupCenterService){
        $this->pkupService = $pickupCenterService;
    }

    public function createQuickOrder(Request $req){
        $user = UserService::getAuthUser();
        $createOrder = $this->pkupService->createOrder($req,$user);
        return ApiResponse::flex($createOrder);
    }

    public function deleteOrder(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $order = Order::with('tracking_status')->where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        $status = $order->tracking_status->name;
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.error',['info' => 'Order packages have arrived warehouse']));
        if($order->status_id == 2) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order has '.$status]));
        if($order->status_id == 3) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order has '.$status]));
        if($order->status_id == 4) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order has '.$status]));
        $order->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        Package::where('order_id',$id)->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);

        return ApiResponse::JsonResult(null,__('messages.deleted',['info' => 'Order']));
    }

    public function updateQuickOrder(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $order = Order::where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.error',['info' => 'Order has already inputed details!']));
        $validate = $this->pkupService->orderValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $merchantId = $inputs['merchant_id'];
        $validMerchant = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','merchant')->find($merchantId);
        if(!$validMerchant) return ApiResponse::ValidateFail('Invalid sender identity!');
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['booking_channel'] = 'admin';
        $pickupAddress = $inputs['pickup_address'] ?? null;
        $driverId = $inputs['driver_id'] ?? null;
        $pickup_address_google_map = $inputs['pickup_address_google_map'] ?? null;
        $inputs['status_id'] = 3; //** accepted for pick up*/
        if(!$driverId) $inputs['status_id'] = 1; //** available for pick */
        else{
            $validDriver = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->find($driverId);
            if(!$validDriver) return ApiResponse::ValidateFail('Invalid driver identity!');
            if($validDriver->vehicle_type != $inputs['vehicle_type']) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Driver vehicle type and chosen vehicle type is different!']));
        }
        if($user->account_type == 'driver') $inputs['booking_channel'] = 'driver';
        else if($user->account_type == 'merchant') $inputs['booking_channel'] = 'merchant';
        $latLng = Helper::getLatLongFromGoogleMapsUrl($pickup_address_google_map);
        $inputs['loc_lat'] = $latLng->latitude;
        $inputs['loc_lng'] = $latLng->longitude;
        if(!$pickupAddress) $inputs['pickup_address'] = $latLng->address;
        $update = $order->update($inputs);
        if(!$update) return ApiResponse::Error(__('messages.error',['info' => 'Fail to update order']));
        return ApiResponse::JsonResult(null,__('messages.updated',['info' => 'Order has']));
    }

    public function setOrderStatus(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $status_id = $req->status_id;
        $driver_id = $req->driver_id;
        // if($status_id == 1 && $driver_id) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Status Available for Pickup cannot assign to driver']));
        if(!$status_id) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Please']));
        $order = Order::with('tracking_status')->where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        $status = $order->tracking_status->name;
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.error',['info' => 'Order packages have arrived warehouse']));
        if($order->status_id == $status_id){
            return ApiResponse::Duplicated(__('messages.error',['info' => 'Order has '.$status]));
        }
        $availableDriver = GeneralSettingService::getDriverById($driver_id);
        if($availableDriver && $availableDriver->lock) {
            return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Driver is currently inactive',
                'khInfo' => 'អ្នកដឹកជញ្ជូនត្រូវបានឈប់ដំណើរការ'
            ]));
        }
        $message = [
            'info' => 'Status Changed!',
            'khInfo' => 'បានប្ដូរ!'
        ];
        // if($order->status_id == 2) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order has '.$status]));
        if($order->status_id != 1 && $status_id == 1) {
            // return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order has '.$status]));
            if($driver_id){
                return ApiResponse::ValidateFail(__('messages.error',[
                    'info' => 'If status Available For Pick you must unselect driver !',
                    'khInfo' => 'ប្រសិនបើប្តូរការ Order ទៅទំនេរសូមកុំជ្រើសរើសអ្នកដឹក !'
                ]));
            }
            $driverName = $order->driver?->username;
            $message = [
                'info' => 'Status changed but order is related to a delivery person, suggest contacting the delivery person ('.$driverName.')',
                'khInfo' => 'ស្ថានភាពត្រូវបានផ្លាស់ប្តូរ ប៉ុន្តែការបញ្ជាទិញនេះទាក់ទងនឹងបុគ្គលិកដឹកជញ្ជូន សូមផ្តល់អនុសាសន៍ឲ្យទាក់ទងបុគ្គលិកដឹកជញ្ជូន ('.$driverName.')'
            ];
        }
        // if($order->status_id == 4) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order has '.$status]));

        $order->update([
            'driver_id' => $driver_id,
            'udpate_uid' => $user->id,
            'status_id' => $status_id,
            'branch_id' => $user->branch_id
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',$message));
    }

    public function getOrders(Request $req){
        $user = UserService::getAuthUser();
        $lang = $req->lang;
        $search = $req->search;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $statusId = $req->status_id;
        $driverId = $req->driver_id;
        $merchantId = $req->merchant_id;
        $warehouseId = $req->warehouse_id;
        $query = Order::query()->with(['merchant','tracking_status','driver','createdBy'])->where('is_deleted',0)
            ->whereIn('status_id',[1,2,3,4,21])
            ->where('company_id',$user->company_id)
            ->orderByDesc('id')
            ->select([
                'booking_channel','id','merchant_id','status_id','order_datetime','driver_id','warehouse_id','vehicle_type','product_type',
                'original_qty as qty','qty as actual_qty','pickup_address','code','created_at','create_uid','delivery_type'
            ]);
        if($search){
            $query->where(function($q) use ($search){
                $q->whereHas('merchant',function ($q) use ($search){
                    $q->where('phone','ilike','%'.$search.'%');
                })->orWhere('code',$search);
            });
        }
        if($driverId){
            $query->where('driver_id',$driverId);
        }
        if($merchantId){
            $query->where('merchant_id',$merchantId);
        }
        if($warehouseId){
            $query->where('warehouse_id',$warehouseId);
        }
        if($statusId){
            $query->where('status_id',$statusId);
        }
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $query->whereBetween('order_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);

        }
        $packages = Package::where('outstanding',1)->where('is_deleted',0)->get();
        // $orders = $query->get();
        $callbackMapper = function($order) use($lang,$packages){
            $order->created_user = $order->createdBy?->username;
            $order->order_date = Helper::dateDMY($order->order_datetime);
            // $order->created_at = Helper::formatCustomDateTime($order->created_at);
            $order->order_time = Helper::formatCustomDateTime($order->order_datetime,'h:i:s A');
            $order->merchant_name = $order->merchant->username;
            $order->merchant_code = $order->merchant->code;
            $order->merchant_code = $order->merchant->code;
            $order->default = [
                'cod' => $order->merchant->cod ? "1":"0",
                'code' => $order->merchant->merchantPriceList?->zone_code
            ];
            if(!$order->product_type) $order->product_type = 'Others';

            if($lang != 'en'){
                $statusCode = GeneralSettingService::$statusCodeTrans[$order->status_id] ?? null;
                $order->status_code = $statusCode;
            }else $order->status_code = $order->tracking_status->name;
            // $order->status_code_kh = $order->tracking_status->name;
            $order->driver_name = $order->driver?->username;
            $order->driver_code = $order->driver?->code;
            $order->package_count = $this->getPackageCountByOrder($packages,$order->id);
            unset($order->merchant,$order->driver,$order->tracking_status,$order->createdBy);
            return $order;
        };
        // foreach($orders as $order){
        //     $order->created_user = $order->createdBy?->username;
        //     $order->order_date = Helper::dateDMY($order->order_datetime);
        //     // $order->created_at = Helper::formatCustomDateTime($order->created_at);
        //     $order->order_time = Helper::formatCustomDateTime($order->order_datetime,'h:i:s A');
        //     $order->merchant_name = $order->merchant->username;
        //     $order->merchant_code = $order->merchant->code;
        //     $order->merchant_code = $order->merchant->code;
        //     $order->default = [
        //         'cod' => $order->merchant->cod ? 1:0,
        //         'code' => $order->merchant->merchantPriceList?->zone_code
        //     ];
        //     if(!$order->product_type) $order->product_type = 'Others';

        //     if($lang != 'en'){
        //         $statusCode = GeneralSettingService::$statusCodeTrans[$order->status_id] ?? null;
        //         $order->status_code = $statusCode;
        //     }else $order->status_code = $order->tracking_status->name;
        //     // $order->status_code_kh = $order->tracking_status->name;
        //     $order->driver_name = $order->driver?->username;
        //     $order->driver_code = $order->driver?->code;
        //     $order->package_count = $this->getPackageCountByOrder($packages,$order->id);
        //     unset($order->merchant,$order->driver,$order->tracking_status,$order->createdBy);
        // }
        return ApiResponse::PaginationV1($query,$req,__('messages.Get Orders'),[],1000,$callbackMapper);
    }

    private function getPackageCountByOrder($packages,$oderId){
        $count = 0;
        foreach($packages as $pkg){
            if($pkg->order_id == $oderId){
                $count +=1;
            }
        }
        return $count;
    }

    public function getOneOrder(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $order = Order::with(['merchant','tracking_status'])->where('is_deleted',0)
            ->where('company_id',$user->company_id)
            ->selectRaw('id,merchant_id,status_id,order_datetime,driver_id,warehouse_id,vehicle_type,product_type,qty,pickup_address,code,created_at')
            ->find($id);
        return ApiResponse::JsonResult($order,__('messages.get one'));
    }

    public function changeMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $merchant_id = $req->merchant_id;
        $order = Order::with('tracking_status')->where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::Error(__('messages.not_found',['info' => 'Order']));
        $status_id = $order->status_id;
        if($order->status_id !== 1) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Order is '.$order->tracking_status->name]));
        if($status_id == 1){
            $order->update([
                'update_uid' => $user->id,
                'merchant_id' => $merchant_id
            ]);
        }
        return ApiResponse::JsonResult(null,false,__('messages.updated'));
    }

    public function assignDriver(Request $req){
        $user = UserService::getAuthUser();
        $driverId = $req->driver_id ?? null;
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->with('merchant')->whereIn('status_id',[1,3])->find($orderId);
        if(!$order) return ApiResponse::NotFound('Order not found');
        if($driverId){
            $driver = GeneralSettingService::getDriverById($driverId);
            if(!$driver) return ApiResponse::ValidateFail('Invalid driver identity!');
            if($driver->lock) {
                return ApiResponse::ValidateFail(__('messages.info',[
                    'info' => 'Driver is currently inactive',
                    'khInfo' => 'អ្នកដឹកជញ្ជូនត្រូវបានឈប់ដំណើរការ'
                ]));
            }
            //* if order status = picked
            if($order->status_id == 2 && $order->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'Order has already been picked'
            ]));
            //* if order status = Accepted For Pickup
            // if($order->status_id == 3 && $order->driver_id) return ApiResponse::Duplicated(__('messages.Order has already been accepted for picked'));
            //* if order status = Picked And Booked
            if($order->status_id == 4 && $order->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'Order has already been Picked And Booked'
            ]));
            //* if order status = Picked And Booked
            if($order->status_id == 11) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'Order has been cancled'
            ]));

            if($driver->vehicle_type != $order->vehicle_type) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Your Order vehicle type is ('.$order->vehicle_type.') and driver vehicle is '.$driver->vehicle_type]));
        }
        $trackingNotes = $order->tracking_notes.'|Admin assign ('.$order->code.') '.date('d-M-Y h:i:s A');
        $order->update([
            'pickup_datetime' => now(),
            'assign_uid' => $user->id,
            'tracking_notes' => $trackingNotes,
            'driver_id' => $driverId,
            'status_id' => ($driverId != 0 && $driverId) ? 3 : 1
        ]);
        $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$driverId);
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $driverId,
                'title' => 'Assigned Order',
                'body' => 'You have been assigned to pickup the order('.$order->code.'). Merchant:'.$order->merchant->username
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
        if(!$driverId) return ApiResponse::JsonResult(null,__('Order '.$order->code.' is available now'));
        return ApiResponse::JsonResult(null,__('Order '.$order->code.' has assigned to '.$driver->username));
    }

    public function setAtWarehouse(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;

        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Order']));
        $order->update([
            'update_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,'Arrived warehouse');
    }

    public function addPackage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $create = $this->pkupService->createOrUpdatePackage($req,$user,null,$orderId);
        return ApiResponse::flex($create);
    }

    public function addOrderImage(Request $req){
        $user = UserService::getAuthUser();
        $photos = $req->photos;
        $orderId = $req->order_id;
        $companyId = $user->company_id;
        if(!isset($photos[0])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please add image'
        ]));
        $deleteImgs = [];
        try{
            DB::beginTransaction();
            foreach($photos as $photo){
                $img = Helper::saveImageFile($photo,$companyId,'order_image',date('Y-m-d'));
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
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.created'));
        }catch(Exception $e){
            Log::error($e->getMessage());
            DB::rollBack();
            foreach($deleteImgs as $img){
                Helper::deleteImageFile($img,$companyId,'order_image');
            }
            return ApiResponse::JsonResult(null,__('messages.error',[
                'info' => 'Fail to add photo'
            ]));
        }
    }

    public function getOrderImages(Request $req){
        $orderId = $req->order_id;
        $user = UserService::getAuthUser();
        $orderImages = OrderImage::where('order_id',$orderId)->selectRaw('photo_file_name,created_at')->get();
        foreach($orderImages as $img){
            $imageAt = Helper::dateYMD($img->created_at);
            $img->image_url = Helper::getImageUrl($img->photo_file_name,$user->company_id,'order_image',$imageAt);
        }
        return ApiResponse::JsonResult($orderImages);
    }

    public function getOnePackageById(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        // $calFee = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod,$package->extra_charge,$user,$package->taxi_fee,$package->merchant_id);
        $package->total = $package->merchant_total + $package->driver_total;
        $package->cod = $package->cod? "1":"0";
        return ApiResponse::JsonResult($package,__('messages.get one'));
    }

    public function getPackagesByOrderId(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $qP = Package::query()->where('order_id',$orderId)->whereIn('status_id',[1,3,7])->with(['status'])->where('company_id',$user->company_id)
                ->selectRaw('merchant_id,order_id,id,id as package_id,taxi_fee,delivery_type,qr_code,price,driver_id,product_type,dim_z,dim_x,dim_y,status_id,failure_notes,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,delivered_datetime,assign_driver_datetime,arrive_warehouse_datetime,driver_total,merchant_total,extra_charge,additional_fee,remarks,billed_kg,actual_kg,pickup_notes')
                ->orderByDesc('id')
        ->where('is_deleted',0);
        $count = $qP->count();
        // $packages = $qP->get();
        $clbMapper = function ($pkg){
            $pkg->cod = $pkg->cod? "1":"0";
            $pkg->total = $pkg->driver_total;//PickupCenterService::getDriverTotal($pkg->cod,$pkg->payer,$pkg->price,$pkg->delivery_fee,$pkg->additional_fee,$pkg->extra_charge);
            $pkg->fee = ($pkg->payer == 'receiver' ? $pkg->delivery_fee : 0) + $pkg->extra_charge + $pkg->additional_fee;
            unset($pkg->status);
            return $pkg;
        };
        $req->query->set('per_page', $count);
        return ApiResponse::PaginationV1($qP,$req,null,[],250,$clbMapper);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $packageId = $req->id;
        $update = $this->pkupService->createOrUpdatePackage($req,$user,$packageId,$orderId);
        return ApiResponse::flex($update);
    }

    public function printOrderPackages(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->order_id;
        $order = Order::where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::NotFound();
        $packages = Package::where('is_deleted',0)
        ->with(['driver:id,username,phone','merchant:id,phone,username','updateUser:id,username'])
        // ->whereNotIn('status_id',[]) // at warehouse
        // ->where('company_id',$user->company_id)
        ->selectRaw('cod,extra_charge,taxi_fee,delivery_fee,zone_name,zone_code,merchant_id,driver_id,receiver_phone,receiver_address,created_at,arrive_warehouse_datetime,qr_code,remarks,price,update_uid,payer')
        ->where('order_id',$id)
        // ->orderByRaw("CASE $orderByCase END")
        ->get();
        // if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $exchange = GeneralSettingService::getLatestXRate();
        foreach($packages as $package){
            $driver = $package->driver;
            if($driver){
                $package->driver_name = $driver->username;
            }
            $package->merchant_name = $package->merchant->username;
            $package->merchant_phone = $package->merchant->phone;
            $package->receiver_address = $package->receiver_address ?? $package->zone_name;
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
        $obj = [
            'company_info' => $companyInfo,
            'packages' => $packages,
            'notes' => GeneralSettingService::disclaimerText($companyInfo?->disclaimer),//'រាល់ទំនិញខុសច្បាប់ ម្ចាស់ទំនិញត្រូវទទួលខុសត្រូវចំពោះមុខច្បាប់ដោយខ្លួនឯង ក្រុមហ៊ុនមិនទទួលខុសត្រូវឡេីយ។ សូមអរគុណសម្រាប់ការប្រើប្រាស់សេវាកម្មដឹកជញ្ជូន JS Express របស់ខ្ញុំ។',
            'redirect' => asset('api/redirect-store')
        ];
        return ApiResponse::JsonResult($obj,__('messages.info',['info' => 'Print Information']));
    }

    public function arriveWarehouse(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->id;
        $order = Order::where('company_id',$user->company_id)->where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.already_at_warehouse'));
        $query = Package::where('order_id',$orderId)->where('outstanding',1)->where('company_id',$user->company_id);
        $count = $query->count();
        if($count < 1) return ApiResponse::NotFound(__('messages.info',['info' => 'No package found, you need to add package']));
        $query->update([
            'arrive_warehouse_datetime' => now(),
            'outstanding' => 0,
            'status_id' => 5 //** at warehouse */
        ]);
        $order->update([
            'status_id' => 5, //* at warehouse
            'qty' => $count
        ]);

        Helper::clearCacheByTags([
            'package_trail'
        ]);
        return ApiResponse::JsonResult(null,__('messages.arrived',['info' => 'Packages have']));
    }

    public function deletePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $order_id = $req->order_id;
        $order = Order::where('company_id',$user->company_id)->where('is_deleted',0)->find($order_id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.already_at_warehouse'));
        if(!$user->system_admin){
            if($order->status_id == 2) return ApiResponse::Forbidden(__('messages.no_access'));
            if($order->status_id == 3) return ApiResponse::Forbidden(__('messages.no_access'));
            if($order->status_id == 4) return ApiResponse::Forbidden(__('messages.no_access'));
        }
        $package = Package::where('company_id',$user->company_id)->where('order_id',$order_id)->where('is_deleted',0)->where('outstanding',1)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $package->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        $this->pkupService->updateOrderQty($package->order_id);
        return ApiResponse::JsonResult(null,__('messages.deleted',['info' => 'Package','khInfo'=>'កញ្ចប់']));
    }
}
