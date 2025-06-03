<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Services\AppSetting;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class HistoryController extends Controller
{

    protected string $dateFmt;

    public function __construct(){
        $this->dateFmt = 'd M Y';
    }
    //
    // public function getAllHistories(Request $req){
    //     $today = now();
    //     $dateaAgo = Helper::getDateDaysAgo(2);
    //     $qAt = PackageAttachment::where('hidden',0);
    //     $attachments = $qAt->limit(1000)
    //     ->whereBetween('updated_at',[$dateaAgo,$today])
    //     ->pluck('package_id')
    //     ->toArray();
    //     $attachmentsLookup = array_flip($attachments);
    //     $user = UserService::getAuthUser('merchant');
    //     $items =[];
    //     $status = $req->status ?? 'All';
    //     $isKm = $req->lang != 'en';
    //     if(in_array($status,['All','Pick Up'])){
    //         $orders = Order::where('merchant_id',$user->id)
    //         ->with(['tracking_status','driver'])
    //         ->where('is_deleted',0)
    //         ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,driver_id')
    //         ->whereIn('status_id',[2,3,4])->get();
    //         foreach($orders as $order){
    //             $order->status_code = $order->tracking_status->name;
    //             if($isKm){
    //                 $order->render_status = GeneralSettingService::$statusCodeTrans[$order->status_id];
    //             }else $order->render_status = 'Pick Up';
    //             $order->driver_phone = $order->driver->phone;
    //             $order->driver_name = $order->driver->user_name;
    //             unset($order->tracking_status,$order->driver);
    //             $items[] = $order;
    //         }
    //     }

    //     if(in_array($status,['All','On Delivery'])){
    //         Package::where('merchant_id', $user->id)
    //         ->with('driver')
    //         ->where('status_id', 6)
    //         ->where('is_deleted', 0)
    //         ->selectRaw('id,status_id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,arrive_warehouse_datetime')
    //         ->get()
    //         ->map(function ($package) use (&$items,$isKm) {
    //             // Cast price to float manually
    //             $package->price = (float) $package->price;
    //             $package->cod_fee = $package->cod ? $package->price : 0;
    //             $package->status_code = 'On Delivery';
    //             $package->driver_phone = $package->driver->phone ?? null; // Ensure driver relationship exists
    //             $package->driver_name = $package->driver->user_name ?? null;
    //             if($isKm){
    //                 $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
    //             }else $package->render_status = $package->status_code;
    //             $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
    //             $package->total = (float) $package->cod_fee;
    //             $package->delivery_fee = (float) $package->delivery_fee;
    //             $package->fee = $package->delivery_fee;
    //             // Remove the driver relationship if not needed in the response
    //             unset($package->driver);
    //             $items[] = $package;
    //             return $package;
    //         });
    //     }


    //     if(in_array($status,['All','Success'])){
    //         Package::where('merchant_id',$user->id)
    //         ->with('driver')
    //         ->where('status_id',9)
    //         ->where('is_deleted',0)
    //         ->selectRaw('id,status_id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,delivered_datetime,arrive_warehouse_datetime,delivery_remarks as notes')
    //         ->get()->map(function($package) use(&$items,$isKm,$attachmentsLookup){
    //             $package->price = (float) $package->price;
    //             $package->has_img = isset($attachmentsLookup[$package->package_id]);
    //             $package->cod_fee = $package->cod ? $package->price : 0;
    //             $package->status_code = 'Delivered';
    //             $package->driver_phone = $package->driver->phone;
    //             $package->driver_name = $package->driver->user_name;
    //             $package->total = (float) $package->cod_fee;
    //             $package->delivery_fee = (float)$package->delivery_fee;
    //             $package->fee = $package->delivery_fee;
    //             $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
    //             $package->delivered_datetime = Helper::formatCustomDateTime($package->delivered_datetime);
    //             if($isKm){
    //                 $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
    //             }else $package->render_status = 'Success';
    //             unset($package->driver);
    //             $items[] = $package;
    //             // return $package;
    //         });
    //     }

    //     if(in_array($status,['All','Failed'])){
    //         Package::where('merchant_id',$user->id)
    //         ->with(['driver','status'])
    //         ->where('status_id',10)
    //         ->where('is_deleted',0)
    //         ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,delivery_remarks as notes')
    //         ->get()
    //         ->map(function($package) use(&$items,$isKm,$attachmentsLookup){
    //             $package->has_img = isset($attachmentsLookup[$package->package_id]);
    //             $package->price = (float)$package->price;
    //             $package->cod_fee = $package->cod ? $package->price : 0;
    //             $package->status_code = $package->status->name;
    //             $package->driver_phone = $package->driver->phone;
    //             if($isKm){
    //                 $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
    //             }else $package->render_status = $package->status_code;
    //             $package->driver_name = $package->driver->user_name;
    //             $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
    //             $package->failed_datetime = Helper::formatCustomDateTime($package->failed_datetime);
    //             $package->total = (float) $package->cod_fee;
    //             $package->fee = (float)$package->delivery_fee;
    //             $package->delivery_fee = (float)$package->delivery_fee;
    //             unset($package->driver,$package->status);
    //             $items[] = $package;
    //         });
    //     }

    //     if(in_array($status,['All','Failed With Fee'])){
    //         Package::where('merchant_id',$user->id)
    //         ->with(['driver','status'])
    //         ->where('status_id',19)
    //         ->where('is_deleted',0)
    //         ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,delivery_remarks as notes')
    //         ->get()
    //         ->map(function($package) use(&$items,$isKm){
    //             $package->price = (float)$package->price;
    //             $package->cod_fee = $package->cod ? $package->price : 0;
    //             $package->status_code = $package->status->name;
    //             $package->driver_phone = $package->driver->phone;
    //             if($isKm){
    //                 $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
    //             }else $package->render_status = $package->status_code;
    //             $package->driver_name = $package->driver->user_name;
    //             $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
    //             $package->failed_datetime = Helper::formatCustomDateTime($package->failed_datetime);
    //             $package->total = (float) $package->cod_fee;
    //             $package->fee = (float)$package->delivery_fee;
    //             $package->delivery_fee = (float)$package->delivery_fee;
    //             unset($package->driver,$package->status);
    //             $items[] = $package;
    //         });
    //     }

    //     if(in_array($status,['All','Return','Returned'])){
    //         Package::where('merchant_id',$user->id)
    //         ->with(['driver','status'])
    //         ->where('status_id',11)
    //         ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,returned_datetime,updated_at,delivery_remarks as notes')
    //         ->get()
    //         ->map(function($package) use(&$items,$isKm){
    //             $package->price = (float)$package->price;
    //             $package->cod_fee = $package->cod ? $package->price : 0;
    //             $package->status_code = $package->status->name;
    //             $package->driver_phone = $package->driver?->phone;
    //             $package->driver_name = $package->driver?->user_name;
    //             if($isKm){
    //                 $package->render_status = GeneralSettingService::$statusCodeTrans[$package->status_id];
    //             }else $package->render_status = $package->status_code;
    //             $package->total = (float) $package->cod_fee;
    //             $package->delivery_fee = (float)$package->delivery_fee;
    //             $package->fee = $package->delivery_fee;
    //             $returnDate = $package->return_datetime ? $package->return_datetime : $package->updated_at;
    //             $package->returned_date = Helper::dateDMY($returnDate);
    //             $package->return_time = Helper::formatCustomDateTime($returnDate, 'h:i:s');
    //             $package-> arrive_warehouse_datetime= Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
    //             unset($package->driver,$package->status);
    //             $items[] = $package;
    //         });
    //     }
    //     return ApiResponse::Pagination(collect($items),$req);
    // }

    public function getAllHistories(Request $req)
    {
        $today = now();
        $dateAgo = Helper::getDateDaysAgo(2);
        $user = UserService::getAuthUser('merchant');
        $status = $req->status ?? 'All';
        $isKm = $req->lang !== 'en';
        $items = [];

        $attachments = PackageAttachment::where('hidden', 0)
            ->whereBetween('updated_at', [$dateAgo, $today])
            ->limit(1000)
            ->pluck('package_id')
            ->flip();

        // Shared formatter
        $formatCommonFields = function ($pkg, $statusCode, $customDateFields = []) use ($attachments, $isKm) {
            $pkg->price = (float) $pkg->price;
            $pkg->cod_fee = $pkg->cod ? $pkg->price : 0;
            $pkg->status_code = $statusCode;
            $pkg->driver_phone = $pkg->driver->phone ?? null;
            $pkg->driver_name = $pkg->driver->user_name ?? null;
            $pkg->total = $pkg->cod_fee;
            $pkg->delivery_fee = (float) $pkg->delivery_fee;
            $pkg->fee = $pkg->delivery_fee;
            $pkg->has_img = isset($attachments[$pkg->package_id]);
            $pkg->render_status = $isKm ? GeneralSettingService::$statusCodeTrans[$pkg->status_id] : $statusCode;

            foreach ($customDateFields as $field) {
                if (!empty($pkg->$field)) {
                    $pkg->$field = Helper::formatCustomDateTime($pkg->$field);
                }
            }

            unset($pkg->driver, $pkg->status);
            return $pkg;
        };

        if (in_array($status, ['All', 'Pick Up'])) {
            Order::where('merchant_id', $user->id)
                ->with(['tracking_status', 'driver'])
                ->where('is_deleted', 0)
                ->whereIn('status_id', [2, 3, 4])
                ->select('id', 'code', 'qty', 'product_type', 'vehicle_type', 'order_datetime', 'status_id', 'driver_id')
                ->get()
                ->each(function ($order) use (&$items, $isKm) {
                    // $order->status_code = $order->tracking_status->name;
                    $order->status_code = $order->status_id == 3 ? 'Accepted' : $order->tracking_status->name;
                    $orderDatetime = strtotime($order->order_datetime);
                    $order->order_date = date($this->dateFmt,$orderDatetime);
                    $order->order_time = date('h:i A',$orderDatetime);
                    $order->render_status = $isKm ? GeneralSettingService::$statusCodeTrans[$order->status_id] : 'Pick Up';
                    $order->telegram_url = Helper::generateTelegramLink($order->driver->phone);
                    $order->driver_phone = $order->driver->phone ?? null;
                    $order->driver_name = $order->driver->user_name ?? null;
                    unset($order->tracking_status, $order->driver);
                    $items[] = $order;
                });
        }

        $statusGroups = [
            6  => 'On Delivery',
            9  => 'Success',
            10 => 'Failed',
            19 => 'Failed With Fee',
            11 => 'Return', // Also used for 'Returned'
        ];

        $targetStatusIds = $status === 'All'
            ? array_keys($statusGroups)
            : array_keys(array_filter($statusGroups, fn($name) => $name === $status));

        // Fields to select
        $fields = [
            'id', 'status_id', 'merchant_id', 'receiver_phone', 'receiver_address', 'receiver_name',
            'cod', 'price', 'delivery_fee', 'remarks', 'driver_id',
            'arrive_warehouse_datetime', 'delivery_remarks as notes',
            'delivered_datetime', 'failed_datetime', 'returned_datetime', 'updated_at'
        ];

        // Load packages once
        $packages = Package::where('merchant_id', $user->id)
            ->with(['driver:id,user_name,phone', 'status:id,name'])
            ->whereIn('status_id', $targetStatusIds)
            ->where('is_deleted', 0)
            ->select($fields)
            ->get();

        // Group by status name
        $groupedPackages = $packages->groupBy(function ($pkg) use ($statusGroups) {
            return $statusGroups[$pkg->status_id] ?? 'Other';
        });


        // Helper to split datetime fields based on status_id
        foreach ($groupedPackages as $statusName => $group) {
            foreach ($group as $pkg) {
                $pkg = $formatCommonFields($pkg, $statusName, [
                    'arrive_warehouse_datetime',
                    'delivered_datetime',
                    'failed_datetime',
                    'returned_datetime',
                ]);

                // Split datetime fields based on status_id
                $this->splitDatetimeFieldsByStatus($pkg, $statusName);
                $pkg->telegram_url = AppSetting::getTelegramLink('merchant',$pkg->receiver_phone,$pkg->driver?->phone);
                $items[] = $pkg;
            }
        }



        // $statusGroups = [
        //     'On Delivery' => 6,
        //     'Success' => 9,
        //     'Failed' => 10,
        //     'Failed With Fee' => 19,
        //     'Return' => 11,
        //     'Returned' => 11,
        // ];

        // foreach ($statusGroups as $key => $statusId) {
        //     if (!in_array($status, ['All', $key])) continue;

        //     $fields = [
        //         'id', 'status_id', 'merchant_id', 'receiver_phone', 'receiver_address', 'receiver_name',
        //         'cod', 'price', 'delivery_fee', 'remarks', 'driver_id',
        //         'arrive_warehouse_datetime', 'delivery_remarks as notes'
        //     ];

        //     if ($key === 'Success') {
        //         $fields[] = 'delivered_datetime';
        //     } elseif (in_array($key, ['Failed', 'Failed With Fee'])) {
        //         $fields[] = 'failed_datetime';
        //     } elseif (in_array($key, ['Return', 'Returned'])) {
        //         $fields[] = 'failed_datetime';
        //         $fields[] = 'returned_datetime';
        //         $fields[] = 'updated_at';
        //     }

        //     Package::where('merchant_id', $user->id)
        //         ->with(['driver', 'status'])
        //         ->where('status_id', $statusId)
        //         ->where('is_deleted', 0)
        //         ->selectRaw(implode(',', $fields))
        //         ->get()
        //         ->each(function ($pkg) use (&$items, $formatCommonFields, $key) {
        //             $pkg = $formatCommonFields($pkg, $key, ['arrive_warehouse_datetime', 'delivered_datetime', 'failed_datetime']);

        //             if (in_array($key, ['Return', 'Returned'])) {
        //                 $returnDate = $pkg->returned_datetime ?? $pkg->updated_at;
        //                 $pkg->returned_date = Helper::dateDMY($returnDate);
        //                 $pkg->return_time = Helper::formatCustomDateTime($returnDate, 'h:i:s');
        //             }

        //             $items[] = $pkg;
        //         });
        // }

        return ApiResponse::Pagination(collect($items), $req);
    }

    // Helper function to split datetime fields based on status_id
    protected function splitDatetimeFieldsByStatus(&$pkg, $statusName)
    {
        // Mapping status to relevant datetime fields
        if ($statusName === 'Success' || $pkg->status_id == 9) {
            // Split `delivered_datetime` for status_id 9 (Success)
            $this->splitDatetimeField($pkg, 'delivered_datetime', 'delivered');
        } elseif (in_array($pkg->status_id, [10, 19])) {
            // Split `failed_datetime` for status_id 10 (Failed) or 19 (Failed With Fee)
            $this->splitDatetimeField($pkg, 'failed_datetime', 'failed');
        } elseif ($pkg->status_id == 11) {
            // Split `returned_datetime` for status_id 11 (Return/Returned)
            $this->splitDatetimeField($pkg, 'returned_datetime', 'returned');
        }

        // Split `arrive_warehouse_datetime` for all packages
        $this->splitDatetimeField($pkg, 'arrive_warehouse_datetime', 'arrive_warehouse');
    }

    // Helper function to split a single datetime field into date and time
    protected function splitDatetimeField(&$pkg, $originalField, $prefix)
    {
        if (!empty($pkg->$originalField)) {
            $timestamp = strtotime($pkg->$originalField);
            $pkg->{$prefix . '_date'} = date($this->dateFmt, $timestamp);
            $pkg->{$prefix . '_time'} = date('h:i A', $timestamp);
        }
        unset($pkg->$originalField); // Optionally remove the original field
    }

    private function getHistoryPackage($merchantId,$packageIds){

    }
}
