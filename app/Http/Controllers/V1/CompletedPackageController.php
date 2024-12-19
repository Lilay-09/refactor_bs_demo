<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class CompletedPackageController extends Controller
{
    //
    public function getFinishedPackages(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $merchantId = $req->merchant_id;
        $driverId = $req->driver_id;
        $warehouseId = $req->warehouse_id;
        $paymentStatusId = $req->payment_status_id;
        $search = $req->search;
        // $query = Package::where('is_deleted',0)->whereIn('status_id',[9,19])
        // ->orderByDesc('id')
        // ->selectRaw('qr_code,id,status_id,dim_x,dim_y,dim_z,order_id,failure_notes,payer,cod,price,delivery_fee,receiver_address,zone_code,zone_name,receiver_phone,delivery_type,actual_kg,billed_kg,delivered_datetime,failed_datetime,driver_total,merchant_total');
        $qP = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('orders as o','o.id','p.order_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->orderByDesc('p.id')
        ->whereIn('p.status_id',[9,11,19]) //* delivered and failed with fee
        ->selectRaw('p.delivered_datetime,m.user_name as merchant_name,m.phone as merchant_phone,dpmt.approved as approved_driver_pmt,mpmt.approved as approved_merchant_pmt,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,p.price,ts.name as status_code,p.product_type,p.delivered_datetime,p.failed_datetime,p.taxi_fee,p.payer,p.cod,p.zone_code,p.zone_name,p.receiver_phone,p.delivery_type,p.delivery_fee,p.driver_total,p.merchant_total');

        //** Filter */
        if($search) $qP->where('p.qr_code',$search);
        if($driverId) $qP->where('p.driver_id',$driverId);
        if($merchantId) $qP->where('p.merchant_id',$merchantId);
        if($warehouseId) $qP->where('o.warehouse_id',$warehouseId);
        if($paymentStatusId == 1){
            $qP->whereNotNull('driver_payment_id')->whereNotNull('driver_payment_id')->where('approved_driver_pmt',0)->where('merhchant_pmt_status',0);
        }else if($paymentStatusId == 2){
            $qP->where('dpmt.approved_driver_pmt',1)->where('mpmt.merhchant_pmt_status',1);
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use($startDate,$endDate){
                $q->whereBetween('failed_datetime',[$startDate,$endDate])->orWhereBetween('delivered_datetime',[$startDate,$endDate])
                ->orWhereDate('failed_datetime',$endDate)->orWhereDate('delivered_datetime',$endDate);
            });
        }
        //** --------- */
        $packages = $qP->get();
        foreach($packages as $pkg){
            if($pkg->status_id == 9 || $pkg->status_id == 19){
                if($pkg->approved_driver_pmt) $pkg->driver_pmt_status = 'Paid';
                else $pkg->driver_pmt_status = 'Unpaid';
                if($pkg->approved_merchant_pmt) $pkg->merhchant_pmt_status = 'Paid';
                else $pkg->merhchant_pmt_status = 'Unpaid';
            }
            $pkg->total = $pkg->driver_total + $pkg->merchant_total;
        }
        return ApiResponse::Pagination($packages,$req);
    }

    public function getOneFinishedPackage(Request $req){
        $user = UserService::getAuthUser();
        $packageId = $req->id;
        $package = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->orderByDesc('p.id')
        ->whereIn('p.status_id',[9,11,19]) //* delivered and failed with fee
        ->selectRaw('p.delivered_datetime,m.user_name as merchant_name,m.phone as merchant_phone,dpmt.approved as approved_driver_pmt,mpmt.approved as approved_merchant_pmt,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,p.price,ts.name as status_code,p.product_type,p.delivered_datetime,p.failed_datetime,p.taxi_fee,p.payer,p.cod,p.zone_code,p.zone_name,p.receiver_phone,p.delivery_type,p.delivery_fee as base_fee,p.driver_total,p.merchant_total,p.extra_charge,p.actual_kg,p.billed_kg,p.receiver_address,p.dim_z,p.dim_x,p.dim_y,p.remarks,p.receiver_name')
        ->where('p.id',$packageId)->first();
        if(!$package) return ApiResponse::NotFound();
        $package->cod = $package->cod ? 1 : 0;
        $package->delivery_fee = GeneralSettingService::sumDeliveryFee($package->base_fee,$package->price,$package->cod,$package->payer);
        return ApiResponse::JsonResult($package);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updateDeliveryPackage($req,null,$user));
    }

    public function createOrUpdatePackage(Request $req){
        $validate = validator($req->all(),[
            'driver_id' => 'required|int',
            'barcode' => 'required|string',
            'vehicle_type' => 'required|exists:vehicle_types,name',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $driverid = $inputs['driver_id'];
        $barcode = $inputs['barcode'];
        $today = date('Y-m-d');
        // $todayDelivery = Delivery::whereDate('depart_datetime',$today)->where('company_id',$user->company_id)->where('driver_id',$driverId)->first();
        // if(!$todayDelivery){
        //     $QuerylastPackage = DeliveryPackage::where('package_id',$packageId)->where('is_deleted',0);
        //     $hasFailPackage = $QuerylastPackage->get();
        //     if(isset($hasFailPackage[0])) $QuerylastPackage->update([
        //         'delay_count' => 1,
        //     ]);
        //     $create = Delivery::create([
        //         'driver_id' => $driverId,
        //         'depart_datetime' => now(),
        //         'package_count' => 1,
        //         'status_id' => 14, //** On Delivery */
        //         'warehouse_id' => 1,
        //         'vehicle_type' => $vehicleType,
        //         'branch_id' => $user->branch_id,
        //         'company_id' => $user->company_id,
        //         'update_uid' => $user->id,
        //         'create_uid' => $user->id,
        //     ]);
        //     if(!$create) return DataResponse::Error(__('messages.error',['info' => 'Fail to add fleet']));
        //     $deliveryId = $create->id;
        //     Helper::setFleetNumber($user->branch_id,'fleet_code_controls','deliveries',$deliveryId,'fleet_tracking_number');
        // }else{
        //     $deliveryId = $todayDelivery->id;
        //     $todayDelivery->update([
        //         'driver_id' => $driverId,
        //         'delay_count' => $todayDelivery->delay_count + 1,
        //         'package_count' => $todayDelivery->package_count + 1,
        //         'update_uid' => $user->id,
        //         'branch_id' => $user->branch_id,
        //         'company_id' => $user->company_id,
        //     ]);
        // }
    }
}
