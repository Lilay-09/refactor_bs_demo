<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\UserService;
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
        ->with(['merchant'])
        ->where('status_id',1)
        ->where('company_id',$user->company_id)
        ->orderByDesc('id')
        ->selectRaw('id,order_datetime,merchant_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->get();
        foreach($orders as $order){
            $order->merhant_name = $order->merchant->user_name;
            $order->merchant_code = $order->merchant->code;
            $order->merchant_phone = $order->merchant->phone;
            unset($order->merchant);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getAcceptedPickup(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status'])
        ->where('status_id',3)
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,driver_id,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->get();
        foreach($orders as $order){
            $order->status_code = $order->tracking_status->name;
            unset($order->merchant,$order->tracking_status);
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
        $images = $req->images ?? [];
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
        if($statusId == 2) {
            foreach($images as $image){
                var_dump($image);
            }
        }

        // $order->update($acceptArr);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'You have accepted for pick & book'
        ]));
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
