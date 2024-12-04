<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPackage;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Services\CloudMessagingService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use Google\Rpc\Help;
use Helper;
use Illuminate\Http\Request;

class HomeScreenController extends Controller
{
    //
    protected $user;
    public function __construct(){
        $this->user = UserService::getAuthUser('driver');
    }
    public function getAvailableOrders(Request $req){
        $user = UserService::getAuthUser('driver');
        $orders = Order::where('is_deleted',0)
        ->with(['merchant','warehouse'])
        ->where('status_id',1)
        ->where('company_id',$user->company_id)
        ->orderByDesc('id')
        ->selectRaw('id,order_datetime,merchant_id,warehouse_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->get();
        foreach($orders as $order){
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_code = $order->merchant->code;
            $order->merchant_phone = $order->merchant->phone;
            $order->warehouse_address = $order->warehouse->address;
            unset($order->merchant,$order->warehouse);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getAcceptedPickup(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status','warehouse'])
        ->whereIn('status_id',[2,3,4])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->orderByDesc('id')
        ->selectRaw('id,warehouse_id,driver_id,pickup_address_google_map,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type,loc_lat,loc_lng')
        ->get();
        foreach($orders as $order){
            $order->warehouse_address = $order->warehouse->address;
            $order->status_code = $order->tracking_status->name;
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_phone = $order->merchant->phone;
            // $latLng = Helper::getLatLongFromGoogleMapsUrl($order->pickup_address_google_map);
            $order->latitude = $order->loc_lat ;//? $order->loc_lat : 11.552692;//;
            $order->longitude = $order->loc_lng ;// ? $order->loc_lng : 104.901413;//$order->loc_lng;

            unset($order->merchant,$order->tracking_status,$order->warehouse);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getDriverBalance(Request $req){
        $user = UserService::getAuthUser('driver');
        $trx = new TransactionService();
        $deliveryCommission = $trx->getDriverCommissionBalance($user,$user->id,'delivery');
        $pickUpComission = $trx->getDriverCommissionBalance($user,$user->id,'pick_up');
        $obj = [
            'earning' => $deliveryCommission->total + $pickUpComission->total,
            'settlement' => 250
        ];

        return ApiResponse::JsonResult($obj);
    }

    public function getOneAcceptedPickup(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        // ->with(['merchant','tracking_status','warehouse'])
        ->whereIn('status_id',[2,3,4])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,warehouse_id,driver_id,pickup_address_google_map,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->find($orderId);

        return ApiResponse::JsonResult($orders);
    }

    public function getDelivery(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $driverId = $user->id;
        $orders = Order::fromRaw('orders as o')->join('packages as p','p.order_id','o.id')->where('o.is_deleted',0)
        ->join('users as m','m.id','o.merchant_id')
        // ->join('tracking_statuses as ts','ts.id','o.status_id')
        // ->where('is_completed',0)
        // ->with(['packages:id,cod,price,delivery_fee,payer,zone_code,zone_name,receiver_phone,delivery_type,status_id,order_id,arrive_warehouse_datetime','packages.status'])
        ->selectRaw('o.qty,o.order_datetime,o.id as order_id,o.id,o.code,m.user_name,m.phone')
        ->groupByRaw('m.phone,o.id,o.code,m.user_name')
        ->orderByDesc('o.id')
        ->where('p.driver_id',$driverId)->get();
        // foreach($orders as $order){
        //     // foreach($order->packages as $package){
        //     //     $package->status_code = $package->status->name;
        //     //     unset($package->status);
        //     // }
        // }

        return ApiResponse::Pagination($orders,$req);
    }

    public function getDeliveryItems(Request $req){
        $user = $this->user;
        $oderId = $req->order_id;
        $driverId = $user->id;
        $packages = Package::where('order_id',$oderId)->where('is_deleted',0)
        ->with('status')
        ->selectRaw('qr_code,receiver_address,id,cod,price,delivery_fee,payer,zone_code,zone_name,receiver_phone,delivery_type,driver_total as total,status_id,order_id,arrive_warehouse_datetime,is_contact,priority_level')
        ->where('driver_id',$driverId)->get();
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            unset($package->status);
        }
        return ApiResponse::JsonResult($packages);
    }

    public function acceptOrder(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        $user = UserService::getAuthUser('driver');
        if($user->error) return ApiResponse::flex($user);
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));

        if($order->status_id != 1){
            if($user->id != $order->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'This order is not available'
            ]));
            else return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'You have already accepted order ('.$order->code.')'
            ]));
        }

        $trackingNotes = $order->tracking_notes.'|Driver has accepted order ('.$order->code.') '.date('d-M-Y h:i:s A');

        $order->update([
            'tracking_notes' => $trackingNotes,
            'status_id' => 3,
            'driver_id' => $user->id
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Order accepted'
        ]));
    }


    //** Pick or Pick & Book */
    public function updateAcceptedOrder(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        $statusId = $req->status_id;
        // return $req;
        $order = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status'])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,status_id')
        ->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.info',[
            'info' => 'No order was found'
        ]));
        if($order->status_id == 2) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This order has already been picked'
        ]));
        $qty = $req->qty;
        $images = $req->file('images') ?? [];
        $details = $req->details ?? null;
        if(!in_array($statusId,[2,4])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' =>'Please choose the correct status'
        ]));
        if($statusId == 4 && !$details) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please add details when you choose pick & book'
        ]));
        $qty = $req->qty ?? null;
        $user = UserService::getAuthUser('driver');
        // $order = Order::where('is_deleted',0)->where('status_id',3)->find($orderId);
        // if(!$order) return ApiResponse::NotFound(__('messages.info',[
        //     'info' => 'Couldn\'t find your accepted task',
        // ]));

        $qty = $qty ?? $order->qty;
        if($qty <=0) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Order quantity must be atleast 1'
        ]));
        $acceptArr = [
            'status_id' => $statusId,
            'pickup_datetime' => now(),
            'qty' => $qty,
            'driver_id' => $user->id // the requester is driver
        ];
        if($statusId == 4){
            $acceptArr['booking_channel'] = 'driver';
            $pickMsg = 'Pick & Book';
        }
        if($details){
            $detailsCount = count($details);
            if($qty !== $detailsCount) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Your quantity and details is not matching',
                'khInfo' => 'ចំនួនកញ្ចប់និងទិន្នន័យកញ្ចប់មិនត្រូវគ្នា, ទិន្នន័យបញ្ចូលរកឃើញតែ('.$detailsCount.')'
            ]));
            foreach($details as $d){
                $rD = new Request($d);
                $validate = $this->validatePackageDetails($rD);
                if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
                $inputs = $validate->validated();
                $inputs['order_id'] = $orderId;
                $inputs['status_id'] = 4;
                $inputs['create_uid'] = $user->id;
                $inputs['update_uid'] = $user->id;
                $inputs['company_id'] = $user->company_id;
                $inputs['branch_id'] = $user->branch_id;
                $inputs['actual_kg'] = 0;
                $inputs['billed_kg'] = 0;
                // $inputs['actual_kg'] = 0;
                Package::create($inputs);
            }
        }
        if($statusId == 2) {
            $pickMsg = 'Pickup';
            // $imgCount = count($images);
            // if($imgCount != $qty) return ApiResponse::ValidateFail(__('messages.info',[
            //     'info' => 'Your package quantity is not matching the number of photos.',
            //     'khInfo' => 'ចំនួនកញ្ចប់និងចំនួនរូបភាពមិនត្រូវគ្នា'
            // ]));
            foreach($images as $image){
                $photoFileName = Helper::saveImageFile($image,$user->company_id,'order_image')->filename;
                if($photoFileName){
                    OrderImage::create([
                        'order_id' => $orderId,
                        'original_name' => $image->getClientOriginalName(),
                        'photo_file_name' => $photoFileName,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id
                    ]);
                }
            }
        }

        $order->update($acceptArr);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'You have accepted for '.$pickMsg,
            'khInfo' => 'បានបញ្ចូលទិន្នន័យកញ្ចប់'
        ]));
    }


    private function validatePackageDetails(Request $req){
        return validator($req->all(),[
            'price' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string|max:250',
            'receiver_phone' => 'required|string|max:20|min:8',
            'receiver_name' => 'nullable|string|max:50',
            'actual_kg' => 'nullable|numeric',
            'pickup_notes' => 'nullable|string|max:250'
        ],[
            'receiver_phone.min' => __('messages.info',[
                'info' => 'Please enter a valid phone number',
                'khInfo' => 'សូមបញ្ចូលលេខទូរស័ព្ទដែរត្រឹមត្រូវ'
            ])
        ]);
    }

    public function submitDeliveryPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->package_id;
        $validate = validator($req->all(),[
            'status_id' => 'required|in:9,10,19',
            'delivery_remarks' => 'required|string',
            'image' => 'nullable',
            'amount' => 'nullable|numeric'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $status_id = $inputs['status_id'];
        $amount = $inputs['amount'] ?? 0;
        $inputs['price'] = $amount;
        $inputs['cod'] = $amount > 0 ? true:false;
        $codChange = $amount > 0 ? true:false;
        $inputs['cod_changed'] = $codChange;
        $photo = $inputs['image'] ?? null;
        $deliveryRemarks = $inputs['delivery_remarks'] ?? null;


        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($photo) {
            // \Log::error($photo->getClientMimeType());
            $inputs['photo_file_name'] = Helper::saveImageFileOrBase64($photo,$user->company_id,'submit_package')->filename;
            Helper::deleteImageFile($package->photo_file_name,$user->company_id,'submit_package');
        }
        if($package->status_id == 9) return ApiResponse::Duplicated('This package has already been delivered!');
        if($package->status_id == 11) return ApiResponse::Duplicated('This package has already been returned!');
        if($package->driver_id !== $user->id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Please submit package that belongs to you'
        ]));
        $todayDt = Helper::getDateTime();
        $driverName = $user->user_name;
        $statusCode = $status_id == 9 ? 'Delivered' : ($status_id == 10 ? 'Failed':($status_id == 19 ? 'Failed with fee':''));
        $inputs['tracking_notes'] = $package->tracking_notes."|[$user->id]Driver ($driverName) submit $statusCode ($todayDt)[Remark: $deliveryRemarks]";
        if($codChange && $package->price != $amount){
            $inputs['tracking_notes'] .= "|[$user->id]Driver ($driverName) change cod $package->price to $amount ($todayDt)";
        }
        if($status_id == 9) {
            $inputs['delivered_datetime'] = now();
            $inputs['delivery_remarks'] = $deliveryRemarks;
        }
        if($status_id == 10) {
            $inputs['failed_datetime'] = now();
            $inputs['failure_notes'] = $deliveryRemarks;
        }
        if($status_id == 19) {
            $inputs['failed_datetime'] = now();
            $inputs['failure_notes'] = $deliveryRemarks;
        }
        $package->update($inputs);
        $dp = DeliveryPackage::where('package_id',$id)->where('delay_count',0)->first();
        $dp->update([
            'notes' => $inputs['tracking_notes'],
            'status_id' => $status_id
        ]);
        GeneralSettingService::updateTripStatus($dp->delivery_id,$user);
        return ApiResponse::JsonResult(null,__('messages.submitted'));
    }

    public function cancelOrder(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $reason = $req->reason;
        if(!$reason) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please enter a reason'
        ]));
        $order = Order::where('is_deleted',0)->where('driver_id',$user->id)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));
        if($order->status_id == 20) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package has already been canceled'
        ]));
        $order->update([
            'cancel_notes' => $reason,
            'status_id' => 20 // canceled
        ]);
        return ApiResponse::JsonResult(null,__('messages.canceled'));
    }

    public function dropOrderAtWarehouse(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->where('driver_id',$user->id)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));
        if($order->status_id == 21) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Order has already dropped'
        ]));
        if(!in_array($order->status_id,[2,4])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You can not mark as dropped'
        ]));
        $driverName = $user->name;
        $todayDt = Helper::getDateTime();
        $tracking_notes = $order->tracking_notes."|[$user->id]Driver ($driverName) dropped order ($todayDt)";
        $order->update([
            'status_id' => 21,
            'tracking_notes' => $tracking_notes
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Dropped',
        ]));
    }

    public function markPackageContact(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $packageRef = $req->package_ref;
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found'));
        $package = Package::where('is_deleted',0)->where('order_id',$orderId)->where('driver_id',$user->id)->find($packageRef);
        if(!$package) Package::where('is_deleted',0)->where('order_id',$orderId)->where('driver_id',$user->id)->where('qr_code',$packageRef);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($package->is_contact) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This package has already contacted'
        ]));
        if(!in_array($package->status_id,[6])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You can not mark as dropped'
        ]));
        $driverName = $user->user_name;
        $todayDt = Helper::getDateTime();
        $tracking_notes = $package->tracking_notes."|[$user->id]Driver Marked contact $todayDt";
        $package->update([
            'is_contact' => 1,
            'contact_datetime' => now(),
            'tracking_notes' => $tracking_notes
        ]);

        //** Send Notif */
        $notif = new CloudMessagingService();
        $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$order->merchant_id);
        $notifReq = new Request([
            'topic' => $topics->private,
            'type' => 'private',
            'target_uid' => $package->merchant_id,
            'title' => 'Contact receiver',
            'body' => 'Driver contacted receiver '.$package->receiver_phone
        ]);
        $notif->sendNotificationByTopic($notifReq,$user);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Marked as contact',
        ]));

    }

    public function getOptionsStatus(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick');
        return ApiResponse::JsonResult($statuses);
    }

    public function setArriveWarehouse(Request $req){
        $user = UserService::getAuthUser('driver');
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick');
        return ApiResponse::JsonResult($statuses);
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    public function pinPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $sortList = $req->sort_list;
        if(empty($sortList)) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Sort list is required'
        ]));
        foreach($sortList as $sl){
            if(!isset($sl['package_id'])) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Package Identity is required'
            ]));
            $id = $sl['package_id'];
            $package = Package::where('is_deleted',0)->where('driver_id',$user->id)->find($id);
            if(!$package) return ApiResponse::ValidateFail(__('messages.not_found',[
                'info' => 'Package'
            ]));
        }
            // $package = PackageService::getPackage($sl['package_id']);
    }

    public function booking(Request $req){
        $user = UserService::getAuthUser('driver');
        $pckService = new PickupCenterService();
        $createOrder = $pckService->createOrder($req,$user);
        return ApiResponse::flex($createOrder);
    }

}
