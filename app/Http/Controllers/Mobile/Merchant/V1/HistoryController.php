<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    //
    public function getAllHistories(Request $req){
        $today = now();
        $dateaAgo = Helper::getDateDaysAgo(2);
        $qAt = PackageAttachment::where('hidden',0);
        $attachments = $qAt->limit(1000)
        ->whereBetween('updated_at',[$dateaAgo,$today])
        ->pluck('package_id')
        ->toArray();
        $attachmentsLookup = array_flip($attachments);
        $user = UserService::getAuthUser('merchant');
        $items =[];
        $status = $req->status ?? 'All';
        $isKm = $req->lang != 'en';
        if(in_array($status,['All','Pick Up'])){
            $orders = Order::where('merchant_id',$user->id)
            ->with(['tracking_status','driver'])
            ->where('is_deleted',0)
            ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,driver_id')
            ->whereIn('status_id',[2,3,4])->get();
            foreach($orders as $order){
                $order->status_code = $order->tracking_status->name;
                if($isKm){
                    $order->render_status = GeneralSettingService::$statusCodeTrans[$order->status_id];
                }else $order->render_status = 'Pick Up';
                $order->driver_phone = $order->driver->phone;
                $order->driver_name = $order->driver->user_name;
                unset($order->tracking_status,$order->driver);
                $items[] = $order;
            }
        }

        if(in_array($status,['All','On Delivery'])){
            Package::where('merchant_id', $user->id)
            ->with('driver')
            ->where('status_id', 6)
            ->where('is_deleted', 0)
            ->selectRaw('id,status_id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,arrive_warehouse_datetime')
            ->get()
            ->map(function ($package) use (&$items,$isKm) {
                // Cast price to float manually
                $package->price = (float) $package->price;
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = 'On Delivery';
                $package->driver_phone = $package->driver->phone ?? null; // Ensure driver relationship exists
                $package->driver_name = $package->driver->user_name ?? null;
                if($isKm){
                    $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
                }else $package->render_status = $package->status_code;
                $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
                $package->total = (float) $package->cod_fee;
                $package->delivery_fee = (float) $package->delivery_fee;
                $package->fee = $package->delivery_fee;
                // Remove the driver relationship if not needed in the response
                unset($package->driver);
                $items[] = $package;
                return $package;
            });
        }


        if(in_array($status,['All','Success'])){
            Package::where('merchant_id',$user->id)
            ->with('driver')
            ->where('status_id',9)
            ->where('is_deleted',0)
            ->selectRaw('id,status_id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,delivered_datetime,arrive_warehouse_datetime,delivery_remarks as notes')
            ->get()->map(function($package) use(&$items,$isKm,$attachmentsLookup){
                $package->price = (float) $package->price;
                $package->has_img = isset($attachmentsLookup[$package->package_id]);
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = 'Delivered';
                $package->driver_phone = $package->driver->phone;
                $package->driver_name = $package->driver->user_name;
                $package->total = (float) $package->cod_fee;
                $package->delivery_fee = (float)$package->delivery_fee;
                $package->fee = $package->delivery_fee;
                $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
                $package->delivered_datetime = Helper::formatCustomDateTime($package->delivered_datetime);
                if($isKm){
                    $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
                }else $package->render_status = 'Success';
                unset($package->driver);
                $items[] = $package;
                // return $package;
            });
        }

        if(in_array($status,['All','Failed'])){
            Package::where('merchant_id',$user->id)
            ->with(['driver','status'])
            ->where('status_id',10)
            ->where('is_deleted',0)
            ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,delivery_remarks as notes')
            ->get()
            ->map(function($package) use(&$items,$isKm,$attachmentsLookup){
                $package->has_img = isset($attachmentsLookup[$package->package_id]);
                $package->price = (float)$package->price;
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = $package->status->name;
                $package->driver_phone = $package->driver->phone;
                if($isKm){
                    $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
                }else $package->render_status = $package->status_code;
                $package->driver_name = $package->driver->user_name;
                $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
                $package->failed_datetime = Helper::formatCustomDateTime($package->failed_datetime);
                $package->total = (float) $package->cod_fee;
                $package->fee = (float)$package->delivery_fee;
                $package->delivery_fee = (float)$package->delivery_fee;
                unset($package->driver,$package->status);
                $items[] = $package;
            });
        }

        if(in_array($status,['All','Failed With Fee'])){
            Package::where('merchant_id',$user->id)
            ->with(['driver','status'])
            ->where('status_id',19)
            ->where('is_deleted',0)
            ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,delivery_remarks as notes')
            ->get()
            ->map(function($package) use(&$items,$isKm){
                $package->price = (float)$package->price;
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = $package->status->name;
                $package->driver_phone = $package->driver->phone;
                if($isKm){
                    $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
                }else $package->render_status = $package->status_code;
                $package->driver_name = $package->driver->user_name;
                $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
                $package->failed_datetime = Helper::formatCustomDateTime($package->failed_datetime);
                $package->total = (float) $package->cod_fee;
                $package->fee = (float)$package->delivery_fee;
                $package->delivery_fee = (float)$package->delivery_fee;
                unset($package->driver,$package->status);
                $items[] = $package;
            });
        }

        if(in_array($status,['All','Return','Returned'])){
            Package::where('merchant_id',$user->id)
            ->with(['driver','status'])
            ->where('status_id',11)
            ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,returned_datetime,updated_at,delivery_remarks as notes')
            ->get()
            ->map(function($package) use(&$items,$isKm){
                $package->price = (float)$package->price;
                $package->cod_fee = $package->cod ? $package->price : 0;
                $package->status_code = $package->status->name;
                $package->driver_phone = $package->driver?->phone;
                $package->driver_name = $package->driver?->user_name;
                if($isKm){
                    $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
                }else $package->render_status = $package->status_code;
                $package->total = (float) $package->cod_fee;
                $package->delivery_fee = (float)$package->delivery_fee;
                $package->fee = $package->delivery_fee;
                $returnDate = $package->return_datetime ? $package->return_datetime : $package->updated_at;
                $package->returned_date = Helper::dateDMY($returnDate);
                $package->return_time = Helper::formatCustomDateTime($returnDate, 'h:i:s');
                $package-> arrive_warehouse_datetime= Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
                unset($package->driver,$package->status);
                $items[] = $package;
            });
        }
        return ApiResponse::Pagination(collect($items),$req);
    }

    private function getHistoryPackage($merchantId,$packageIds){

    }
}
