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
        $driverSettled = "
            CASE
                WHEN p.driver_payment_id IS NOT NULL AND dpmt.is_settled = true THEN 'Settled'
                WHEN p.driver_payment_id IS NOT NULL AND dpmt.is_settled = false THEN 'Approved'
                WHEN p.driver_disbursement_id IS NOT NULL AND dbur.is_settled = true THEN 'Settled'
                WHEN p.driver_disbursement_id IS NOT NULL AND dbur.is_settled = false THEN 'Approved'
                ELSE 'No Payment/Disbursement'
            END as driver_status
        ";
        $merchantSettled = "CASE
            WHEN p.merchant_payment_id IS NOT NULL AND dpmt.is_settled = true THEN 'Settled'
            WHEN p.merchant_payment_id IS NOT NULL AND dpmt.is_settled = false THEN 'Approved'
            WHEN p.merchant_disbursement_id IS NOT NULL AND dbur.is_settled = true THEN 'Settled'
            WHEN p.merchant_disbursement_id IS NOT NULL AND dbur.is_settled = false THEN 'Approved'
            ELSE 'No Payment/Disbursement'
        END as merchant_status";
        $qP = Package::from('packages as p')->where('p.company_id',$user->company_id)
        ->with('returnUser')
        ->leftJoin('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('orders as o','o.id','p.order_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->leftJoin('disbursements as dbur','dbur.id','p.driver_disbursement_id') //** if driver paid or unpaid */
        ->leftJoin('disbursements as mbur','mbur.id','p.merchant_disbursement_id') //** if driver paid or unpaid */
        ->orderByDesc('p.id')
        ->whereIn('p.status_id',[9,11,19]) //* delivered and failed with fee
        ->selectRaw('p.receiver_address,p.driver_disbursement_id,p.driver_payment_id,p.delivered_datetime,m.user_name as merchant_name,m.phone as merchant_phone,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,p.price,ts.name as status_code,p.product_type,p.delivered_datetime,p.failed_datetime,p.taxi_fee,p.payer,p.cod,p.zone_code,p.zone_name,p.receiver_phone,p.delivery_type,p.delivery_fee,p.driver_total,p.merchant_total,'.$driverSettled.','.$merchantSettled);
        //** Filter */
        if($search){
            $qP->where(function ($q) use ($search){
                $q->where('p.qr_code',$search)->orWhere('p.receiver_phone','ilike','%'.$search.'%');
            });
        }
        if($driverId) $qP->where('p.driver_id',$driverId);
        if($merchantId) $qP->where('p.merchant_id',$merchantId);
        if($warehouseId) $qP->where('o.warehouse_id',$warehouseId);

        $this->finishPackagePaymentStatus($qP,$driverId,$merchantId,$paymentStatusId);

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use($startDate,$endDate){
                $q->whereRaw('delivered_datetime::DATE >= ? AND delivered_datetime::DATE <= ?', [$startDate, $endDate])
                ->orWhereRaw('failed_datetime::DATE >= ? AND failed_datetime::DATE <= ?', [$startDate, $endDate]);
            });
        }
        //** --------- */
        $packages = $qP->get();
        foreach($packages as $pkg){
            if($pkg->returnUser){
                $pkg->driver_name = $pkg->returnUser->user_name;
            }
            $pkg->total = number_format(abs($pkg->driver_total - $pkg->merchant_total),2);
            unset($pkg->returnUser);
        }
        return ApiResponse::Pagination($packages,$req,null,[]);
    }

    private function finishPackagePaymentStatus($query,$driverId,$merchantId,$paymentStatusId){
        if($driverId && $paymentStatusId == 2 && !$merchantId){
            $query->where(function ($q): void {
                $q->whereNotNull('p.driver_payment_id')->orWhereNotNull('p.driver_disbursement_id')->orWhere('dpmt.approved',1)->orWhere('dbur.approved',1);
            });
        }else if($driverId && $paymentStatusId == 1 && !$merchantId){
            $query->where(function ($q): void {
                $q->whereNull('p.driver_payment_id')->whereNull('p.driver_disbursement_id');
            });
        }else if($merchantId && $paymentStatusId == 2 && !$driverId){
            $query->where(function ($q): void {
                $q->whereNotNull('p.merchant_payment_id')->orWhereNotNull('p.merchant_disbursement_id')->orWhere('dpmt.approved',1)->orWhere('dbur.approved',1);
            });
        }else if($merchantId && $paymentStatusId == 1 && !$driverId){
            $query->where(function ($q): void {
                $q->whereNull('p.merchant_payment_id')->whereNull('p.merchant_disbursement_id');
            });
        }
    }

    public function getOneFinishedPackage(Request $req){
        $user = UserService::getAuthUser();
        $packageId = $req->id;
        $package = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)
        ->leftJoin('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->orderByDesc('p.id')
        ->whereIn('p.status_id',[9,11,19]) //* delivered and failed with fee
        ->selectRaw('p.delivered_datetime,m.user_name as merchant_name,m.phone as merchant_phone,dpmt.approved as approved_driver_pmt,mpmt.approved as approved_merchant_pmt,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,p.price,ts.name as status_code,p.product_type,p.delivered_datetime,p.failed_datetime,p.taxi_fee,p.payer,p.cod,p.zone_code,p.zone_name,p.receiver_phone,p.delivery_type,p.delivery_fee as base_fee,p.driver_total,p.merchant_total,p.extra_charge,p.actual_kg,p.billed_kg,p.receiver_address,p.additional_fee,p.dim_z,p.dim_x,p.dim_y,p.remarks,p.receiver_name')
        ->where('p.id',$packageId)->first();
        if(!$package) return ApiResponse::NotFound();
        $package->cod = $package->cod ? 1 : 0;
        $deliveryFee = GeneralSettingService::sumDeliveryFee($package->base_fee,$package->extra_charge,$package->payer);
        $package->delivery_fee = number_format($deliveryFee,2);
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
