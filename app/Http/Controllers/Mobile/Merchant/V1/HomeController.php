<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    //
    public function createBooking(Request $req){
        $user = UserService::getAuthUser('merchant');
        $pck = new PickupCenterService();
        $details = $req->details ?? [];
        $req->merge(['merchant_id' => $user->id]);
        if($details){
            $details = Helper::convertJsonTextToJson($details);
            if($details->error) return ApiResponse::ValidateFail($details->message);
            $req->merge(['details' => $details->result]);//$details->result;
        }
        $create = $pck->createOrder($req,$user);
        if($create->error) return ApiResponse::flex($create);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Your order has been submitted'
        ]));
    }

    public function trackingActivitySummary(Request $req){
        $user = UserService::getAuthUser('merchant');
        $pendingCount = Order::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',1)->count();
        $pickCount = Order::where('merchant_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[2,3,4])->count();
        $onDeliveryCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',6)->count();
        $successCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',9)->count();
        $failCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[10,19])->count();
        $returnCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[11])->count();
        $totalCount = $pendingCount + $pickCount + $onDeliveryCount + $successCount + $failCount + $returnCount;
        $obj = [
            'pending' => $pendingCount,
            'pick' => $pickCount,
            'on_delivery' => $onDeliveryCount,
            'success' => $successCount,
            'fail' => $failCount,
            'return' => $returnCount,
            'total' => $totalCount,
            'date' => Helper::getDateTime('d-M-Y')
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPendingOrders(Request $req){
        $user = UserService::getAuthUser('merchant');
        $orders = Order::where('merchant_id',$user->id)
        ->where('is_deleted',0)
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id')->where('status_id',1)->get();
        foreach($orders as $order){
            $order->status_code = 'Pending';
        }
        return ApiResponse::Pagination($orders);
    }

    public function getPickOrders(Request $req){
        $user = UserService::getAuthUser('merchant');
        $orders = Order::where('merchant_id',$user->id)
        ->with(['tracking_status','driver'])
        ->where('is_deleted',0)
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,driver_id')->whereIn('status_id',[2,3,4])->get();
        foreach($orders as $order){
            $order->status_code = $order->tracking_status->name;
            $order->driver_phone = $order->driver->phone;
            $order->driver_name = $order->driver->user_name;
            unset($order->tracking_status,$order->driver);
        }
        return ApiResponse::Pagination($orders);
    }

    public function getOnDeliveryPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with('driver')
        ->where('status_id',6)
        ->where('is_deleted',0)
        ->selectRaw('id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,arrive_warehouse_datetime')
        ->get();
        foreach($packages as $package){
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = 'On Delivery';
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = $package->cod_fee + $package->delivery_fee;
            unset($package->driver);
        }
        return ApiResponse::Pagination($packages);
    }

    public function getSuccessPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with('driver')
        ->where('status_id',9)
        ->where('is_deleted',0)
        ->selectRaw('id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,delivered_datetime')
        ->get();
        foreach($packages as $package){
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = 'Delivered';
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = $package->cod_fee + $package->delivery_fee;
            unset($package->driver);
        }
        return ApiResponse::Pagination($packages);
    }

    public function getFailPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with(['driver','status'])
        ->whereIn('status_id',[10,19])
        ->where('is_deleted',0)
        ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime')
        ->get();
        foreach($packages as $package){
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = $package->status->name;
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = $package->cod_fee + $package->delivery_fee;
            unset($package->driver,$package->status);
        }
        return ApiResponse::Pagination($packages);
    }

    public function getReturnPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with(['driver','status'])
        ->whereIn('status_id',[11])
        ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,updated_at as returned_date')
        ->get();
        foreach($packages as $package){
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = $package->status->name;
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = $package->cod_fee + $package->delivery_fee;
            $package->returned_date = Helper::formatCustomDateTime($package->returned_date, 'Y-m-d H:i:s');
            unset($package->driver,$package->status);
        }
        return ApiResponse::Pagination($packages);
    }

    public function getPromotions(Request $req){
        $user = UserService::getAuthUser('merchant');
        $today = date('Y-m-d');
        $promotions = Promotion::where('is_deleted',0)->selectRaw('id,title,description,photo_file_name,start_date,end_date')
        ->where('channel','merchant')
        ->whereRaw('DATE(start_date) >= ? AND DATE(end_date) <= ?',[$today,$today])
        ->orWhereDate('start_date','>=',$today)
        ->get();
        foreach($promotions as $promotion){
            $xDays = Helper::getDateDifference($promotion->start_date,$promotion->end_date,'days');
            $promotion->expires_at = $xDays. ($xDays > 0 ? ' days' : ' day');
            $promotion->time_ago = Helper::timeAgo($promotion->start_date,false);
            $promotion->image_url = Helper::getImageUrl($promotion->photo_file_name,$user->company_id,'promotion');
        }

        return ApiResponse::Pagination($promotions);
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::JsonResult(GeneralSettingService::optionsZone($user));
    }

    public function getZonePrice(Request $req){
        $user = UserService::getAuthUser('merchant');
        $id = $req->zone_id;
        return ApiResponse::JsonResult(GeneralSettingService::priceByZone($id,$user));
    }
}
