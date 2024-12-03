<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    //

    public function getAllHistories(Request $req){
        $user = UserService::getAuthUser('merchant');
        $items =[];
        $status = $req->status ?? 'All';
        if(in_array($status,['All','Pick Up'])){
            $orders = Order::where('merchant_id',$user->id)
            ->with(['tracking_status','driver'])
            ->where('is_deleted',0)
            ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,driver_id')->whereIn('status_id',[2,3,4])->get();
            foreach($orders as $order){
                $order->status_code = $order->tracking_status->name;
                $order->driver_phone = $order->driver->phone;
                $order->driver_name = $order->driver->user_name;
                unset($order->tracking_status,$order->driver);
                $items[] = $order;
            }
        }

        if(in_array($status,['All','On Delivery'])){
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
                $items[] = $package;
            }
        }


        if(in_array($status,['All','Success'])){
            $successPackages = Package::where('merchant_id',$user->id)
            ->with('driver')
            ->where('status_id',9)
            ->where('outstanding',0)
            ->where('is_deleted',0)
            ->selectRaw('id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,delivered_datetime,arrive_warehouse_datetime')
            ->get();
            foreach($successPackages as $package){
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = 'Delivered';
                $package->driver_phone = $package->driver->phone;
                $package->driver_name = $package->driver->user_name;
                $package->total = $package->cod_fee + $package->delivery_fee;
                unset($package->driver);
                $items[] = $package;
            }
        }

        if(in_array($status,['All','Failed'])){
            $packages = Package::where('merchant_id',$user->id)
            ->with(['driver','status'])
            ->where('outstanding',0)
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
                $items[] = $package;
            }
        }

        if(in_array($status,['All','Return','Returned'])){
            $packages = Package::where('merchant_id',$user->id)
            ->with(['driver','status'])
            ->where('outstanding',0)
            ->whereIn('status_id',[11])
            ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,returned_datetime,updated_at')
            ->get();
            foreach($packages as $package){
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = $package->status->name;
                $package->driver_phone = $package->driver?->phone;
                $package->driver_name = $package->driver?->user_name;
                $package->total = $package->cod_fee + $package->delivery_fee;
                $returnDate = $package->return_datetime ? $package->return_datetime : $package->updated_at;
                $package->returned_date = Helper::dateDMY($returnDate);
                $package->return_time = Helper::formatCustomDateTime($returnDate, 'h:i:s');
                unset($package->driver,$package->status);
                $items[] = $package;
            }
        }
        return ApiResponse::JsonResult($items);
    }
}
