<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Http\Request;

class CompletedPackageController extends Controller
{
    //
    public function getFinishedPackages(Request $req){
        $user = UserService::getAuthUser();
        // $query = Package::where('is_deleted',0)->whereIn('status_id',[9,19])
        // ->orderByDesc('id')
        // ->selectRaw('qr_code,id,status_id,dim_x,dim_y,dim_z,order_id,failure_notes,payer,cod,price,delivery_fee,receiver_address,zone_code,zone_name,receiver_phone,delivery_type,actual_kg,billed_kg,delivered_datetime,failed_datetime,driver_total,merchant_total');
        $packages = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->orderByDesc('p.id')
        ->whereIn('p.status_id',[9,19]) //* delivered and failed with fee
        ->selectRaw('m.user_name as merchant_name,m.phone as merchant_phone,dpmt.approved as approved_driver_pmt,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,p.price,ts.name as status_code,p.delivered_datetime,p.failed_datetime,p.taxi_fee,p.payer,p.cod,p.zone_code,p.receiver_phone,p.delivery_type,p.delivery_fee,p.driver_total,p.merchant_total')
        ->get();
        return ApiResponse::Pagination($packages,$req);
    }


    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updateDeliveryPackage($req,null,$user));
    }
}
