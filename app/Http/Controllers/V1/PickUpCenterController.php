<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
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
            if($validDriver->vehicle_type != $inputs['vehicle_type']) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Driver vehicle type and chosen vehicle type is different!']));
        }

        if($user->account_type == 'driver') $inputs['booking_channel'] = 'driver';
        else if($user->account_type == 'merchant') $inputs['booking_channel'] = 'merchant';

        $createOrder = Order::create($inputs);
        if(!$createOrder) return ApiResponse::Error('Fail to create order!');
        $code = Helper::generateCode('JS',$createOrder->id,'',8);
        Order::find($createOrder->id)->update([
            'code' => $code
        ]);
        return ApiResponse::JsonResult(null,false,'Order created ('.$code.')');
    }

    public function updateQuickOrder(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $order = Order::where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.error',['info' => 'Order has already input details!']));
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
            if($validDriver->vehicle_type != $inputs['vehicle_type']) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Driver vehicle type and chosen vehicle type is different!']));
        }

        if($user->account_type == 'driver') $inputs['booking_channel'] = 'driver';
        else if($user->account_type == 'merchant') $inputs['booking_channel'] = 'merchant';
        $update = $order->update($inputs);
        if(!$update) return ApiResponse::Error(__('messages.error',['info' => 'Fail to update order']));
        return ApiResponse::JsonResult(null,false,__('messages.updated',['info' => 'Order has']));
    }

    public function getOrders(Request $req){
        $query = Order::with(['merchant','tracking_status'])->where('is_deleted',0)
            ->whereIn('status_id',[1,3])
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

    public function changeMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $merchant_id = $req->merchant_id;
        $order = Order::where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::Error(__('messages.not_found',['info' => 'Order']));
        $status_id = $order->status_id;
        if($order->status_id == 5) return ApiResponse::JsonResult(null,false,__('messages.at_warehouse'));
        if($order->status_id == 6) return ApiResponse::JsonResult(null,false,__('messages.on_delivery'));
        if($status_id == 1){
            $order->update([
                'update_uid' => $user->id,
                'merchant_id' => $merchant_id
            ]);
        }
        return ApiResponse::JsonResult(null,false,__('messages.updated'));
    }

    public function assignDriver(Request $req){
        $driverId = $req->driver_id ?? null;
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->whereIn('status_id',[1,3])->find($orderId);
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

            if($driver->vehicle_type != $order->vehicle_type) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Your Order vehicle type is ('.$order->vehicle_type.') and driver vehicle is '.$driver->vehicle_type]));
        }

        $order->update([
            'status_id' => ($driverId != 0 && $driverId) ? 3 : 1
        ]);
        if(!$driverId) return ApiResponse::JsonResult(null,false,__('Order '.$order->code.' is available now'));
        return ApiResponse::JsonResult(null,false,__('Order '.$order->code.' has assigned to '.$driver->user_name));
    }

    public function setAtWarehouse(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Order']));
        $order->update([
            'update_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null, false,'Arrived warehouse');
    }

    public function addPackage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $create = $this->pkupService->createOrUpdatePackage($orderId,$req,$user);
        return ApiResponse::flex($create);
    }

    public function getOnePackageById(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $package = Package::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $calFee = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
        $package->total = $calFee->total;
        return ApiResponse::JsonResult($package,false,__('messages.get one'));
    }

    public function getPackagesByOrderId(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $packages = Package::where('order_id',$orderId)->whereIn('status_id',[1,3,7])->with(['status'])->where('company_id',$user->company_id)->where('is_deleted',0)->get();
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            unset($package->status);
        }
        return ApiResponse::Pagination($packages,$req);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->order_id;
        $packageId = $req->id;
        $update = $this->pkupService->createOrUpdatePackage($orderId,$req,$user,$packageId);
        return ApiResponse::flex($update);
    }

    public function arriveWarehouse(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->id;
        $order = Order::where('company_id',$user->company_id)->where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.already_at_warehouse'));
        $query = Package::where('order_id',$orderId)->where('outstanding',1)->where('company_id',$user->company_id);
        $count = $query->count();
        if($count < 1) return ApiResponse::NotFound(__('messages.no_found',['info' => 'Package']));
        $query->update([
            'arrive_warehouse_datetime' => now(),
            'outstanding' => 0,
            'status_id' => 5 //** at warehouse */
        ]);
        $order->update([
            'status_id' => 5 //* at warehouse
        ]);
        return ApiResponse::JsonResult(null,false,__('messages.arrived',['info' => 'Packages have']));
    }

    public function deletePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $order_id = $req->order_id;
        $order = Order::where('company_id',$user->company_id)->where('is_deleted',0)->find($order_id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Order']));
        if($order->status_id == 5) return ApiResponse::Duplicated(__('messages.already_at_warehouse'));
        if($order->status_id == 2) return ApiResponse::Forbidden(__('messages.no_access'));
        if($order->status_id == 3) return ApiResponse::Forbidden(__('messages.no_access'));
        if($order->status_id == 4) return ApiResponse::Forbidden(__('messages.no_access'));
        $package = Package::where('company_id',$user->company_id)->where('order_id',$order_id)->where('is_deleted',0)->where('outstanding',1)->find($id);
        if(!$package) return ApiResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់​']));
        $package->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        $this->pkupService->updateOrderQty($package->order_id);
        return ApiResponse::JsonResult(null,false,__('messages.deleted',['info' => 'Package','khInfo'=>'កញ្ចប់']));
    }
}
