<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\VehicleType;
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
            'sender_id' => 'required|int',
            'warehouse_id' => 'required|int|exists:warehouses,id',
            'product_type' => 'nullable|string',
            'qty' => 'required|int|min:1',
            'vehicle_type' => 'required|exists:vehicle_types,name',
            'driver_id' => 'nullable|int',
            'pickup_address' => 'nullable|string|max:300'
        ],[
            'sender_id.required' => 'Please select the sender',
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
        $senderId = $inputs['sender_id'];
        $validSender = User::where('is_deleted',0)->where('delete_account',0)->find($senderId);
        if(!$validSender) return ApiResponse::ValidateFail('Invalid sender identity!');
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['booking_channel'] = 'admin';
        $driverId = $inputs['driver_id'] ?? null;
        $inputs['status_id'] = 3;
        if(!$driverId) $inputs['status_id'] = 1;
        else{
            $validSender = User::where('is_deleted',0)->where('delete_account',0)->find($senderId);
            if(!$validSender) return ApiResponse::ValidateFail('Invalid sender identity!');
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
        $query = Order::with(['sender','tracking_status'])->where('is_deleted',0)
            ->selectRaw('id,sender_id,status_id,driver_id,warehouse_id,vehicle_type,product_type,qty,pickup_address,code,created_at');
        $orders = $query->get();
        foreach($orders as $order){
            $order->sender_name = $order->sender->user_name;
            $order->sender_code = $order->sender->code;
            if(!$order->product_type) $order->product_type = 'Others';
            $order->status = $order->tracking_status->name;
            unset($order->sender,$order->tracking_status);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function addPackage(Request $req){
        $user = UserService::getAuthUser();
        $create = $this->pkupService->createOrUpdatePackage($req);
        return ApiResponse::flex($create);
    }
}
