<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\UserService;
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
            $order->merhant_name = $order->merchant->user_name;
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
        ->where('status_id',3)
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,warehouse_id,driver_id,pickup_address_google_map,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->get();
        foreach($orders as $order){
            $order->warehouse_address = $order->warehouse->address;
            $order->status_code = $order->tracking_status->name;
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_phone = $order->merchant->phone;
            $latLng = Helper::getLatLongFromGoogleMapsUrl($order->pickup_address_google_map);
            $order->latitude = $latLng->latitude;
            $order->longitude = $latLng->longitude;
            unset($order->merchant,$order->tracking_status,$order->warehouse);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getDelivery(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $driverId = $user->id;
        $orders = Order::fromRaw('orders as o')->join('packages as p','p.order_id','o.id')->where('o.is_deleted',0)
        ->join('users as m','m.id','o.merchant_id')
        // ->with(['packages:id,cod,price,delivery_fee,payer,zone_code,zone_name,receiver_phone,delivery_type,status_id,order_id,arrive_warehouse_datetime','packages.status'])
        ->selectRaw('o.id,o.code,m.user_name,m.phone')->groupByRaw('m.phone,o.id,o.code,m.user_name')->where('p.driver_id',$driverId)->get();
        foreach($orders as $order){
            // foreach($order->packages as $package){
            //     $package->status_code = $package->status->name;
            //     unset($package->status);
            // }
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getDeliveryItem(Request $req){
        $user = $this->user;
        $oderId = $req->order_id;
        $driverId = $user->id;
        $packages = Package::where('order_id',$oderId)->where('is_deleted',0)
        ->where('status_id',9)
        ->with('status')
        ->selectRaw('id,cod,price,delivery_fee,payer,zone_code,zone_name,receiver_phone,delivery_type,status_id,order_id,arrive_warehouse_datetime')
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

        $order->update([
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
        ->where('status_id',3)
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id')
        ->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.info',[
            'info' => 'No order was found'
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
        $order = Order::where('is_deleted',0)->where('status_id',3)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.info',[
            'info' => 'Couldn\'t find your accepted task',
        ]));

        $qty = $qty ?? $order->qty;
        $acceptArr = [
            'status_id' => $statusId,
            'qty' => $qty,
            'driver_id' => $user->id // the requester is driver
        ];
        if($statusId == 4) $acceptArr['booking_channel'] = 'driver';
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
                $inputs['create_uid'] = $user->id;
                $inputs['update_uid'] = $user->id;
                $inputs['company_id'] = $user->company_id;
                $inputs['branch_id'] = $user->branch_id;
                Package::create($inputs);
            }
        }
        if($statusId == 2) {
            $imgCount = count($images);
            if($imgCount != $qty) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Your package quantity is not matching the number of photos.',
                'khInfo' => 'ចំនួនកញ្ចប់និងចំនួនរូបភាពមិនត្រូវគ្នា'
            ]));
            foreach($images as $image){
                $photoFileName = Helper::saveImageFile($image,$user->company_id,'order_image')->filename;
                OrderImage::create([
                    'order_id' => $orderId,
                    'original_name' => $image->getOriginalName(),
                    'photo_file_name' => $photoFileName,
                    'create_uid' => $user->id,
                    'update_uid' => $user->id,
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id
                ]);
            }
        }

        $order->update($acceptArr);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'You have accepted for pick & book',
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
        $validStatusIds = [9,10,19];
        $status_id = $req->status_id;
        if(!in_array($status_id,$validStatusIds)) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please choose the valid status'
        ]));
        $package = Package::where('is_deleted',0)->where('')->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($package->driver_id !== $user->id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Please submit package that belongs to you'
        ]));
        return ApiResponse::JsonResult(null);
    }

    public function getOptionsStatus(Request $req){
        $user = UserService::getAuthUser('driver');
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick');
        return ApiResponse::JsonResult($statuses);
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    // public function
}
