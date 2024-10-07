<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class PickUpCenterController extends Controller
{
    //
    protected $pkupService;
    public function __construct(PickupCenterService $pickupCenterService){
        $this->pkupService = $pickupCenterService;
    }
    private function orderValidation(Request $req){
        $vehicleTypes = implode(',',VehicleType::where('is_deleted',0)->pluck('name')->toArray());

        return validator($req->all(),[
            'merchant_id' => 'required|int',
            'warehouse_id' => 'required|int|exists:warehouses,id',
            'product_type' => 'nullable|string|exists:product_types,name',
            'qty' => 'required|int|min:1',
            'vehicle_type' => 'required|in:'.$vehicleTypes,
            'driver_id' => 'nullable|int',
            'pickup_address' => 'nullable|string|max:300'
        ],[
            'merchant_id.required' => 'Please select the sender',
            'vehicle_type.in' => 'Please select one of ('.$vehicleTypes.')',
            'warehouse_id.required' => 'Please select the warehouse',
            'qty.required' => 'Please enter number of package'
        ]);
    }

    public function createQuickOrder(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->orderValidation($req);
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
        $driverId = $inputs['driver_id'] ?? null;
        $inputs['status_id'] = 3; //** accepted for pick up*/
        if(!$driverId) $inputs['status_id'] = 1; //** available for pick */
        else{
            $validDriver = User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->find($driverId);
            if(!$validDriver) return ApiResponse::ValidateFail('Invalid driver identity!');
        }

        if($user->account_type == 'driver') $inputs['booking_channel'] = 'driver';
        else if($user->account_type == 'merchant') $inputs['booking_channel'] = 'merchant';

        $createOrder = Order::create($inputs);
        if(!$createOrder) return ApiResponse::Error('Fail to create order!');
        $code = Helper::generateCode('JS',1,'',8);
        Order::find($createOrder->id)->update([
            'code' => $code
        ]);
        return ApiResponse::JsonResult(null,false,'Order created');
    }

    public function getOrders(Request $req){
        $query = Order::with(['merchant','tracking_status'])->where('is_deleted',0)
            ->selectRaw('id,merchant_id,status_id,driver_id,warehouse_id,vehicle_type,product_type,qty,pickup_address,code,created_at');
        $orders = $query->get();
        foreach($orders as $order){
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_code = $order->merchant->code;
            if(!$order->product_type) $order->product_type = 'Others';
            $order->status = $order->tracking_status->name;
            unset($order->merchant,$order->tracking_status);
        }
        return ApiResponse::Pagination($orders,$req,__('messages.Get Orders'));
    }

    public function assignDriver(Request $req){
        $driverId = $req->driver_id ?? null;
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound('Order not found');
        if($driverId){
            $driver = GeneralSettingService::getDriverById($driverId);
            if(!$driver) return ApiResponse::ValidateFail('Invalid driver identity!');
            //* if order status = picked
            if($order->status_id == 2) return ApiResponse::Duplicated(__('messages.Order has already been picked'));
            //* if order status = Accepted For Pickup
            if($order->status_id == 3) return ApiResponse::Duplicated(__('messages.Order has already been accepted for picked'));
            //* if order status = Picked And Booked
            if($order->status_id == 4) return ApiResponse::Duplicated(__('messages.Order has already been Picked And Booked'));
            //* if order status = Picked And Booked
            if($order->status_id == 11) return ApiResponse::Duplicated(__('messages.Order has been cancled'));
        }

        $order->update([
            'status_id' => ($driverId != 0 && $driverId) ? 3 : 1
        ]);
        if(!$driverId) return ApiResponse::JsonResult(null,false,__('Order '.$order->code.' is available now'));
        return ApiResponse::JsonResult(null,false,__('Order '.$order->code.' has assigned to '.$driver->user_name));
    }

    public function addPackage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $create = $this->pkupService->createOrUpdatePackage($orderId,$req,$user);
        return ApiResponse::flex($create);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $packageId = $req->package_id;
        $create = $this->pkupService->createOrUpdatePackage($orderId,$req,$user,$packageId);
        return ApiResponse::flex($create);
    }
}
