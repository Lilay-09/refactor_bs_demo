<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class PackageTrailController extends Controller
{
    //
    public function getPackages(Request $req){
        $user = UserService::getAuthUser();
        $query = Package::where('is_deleted',0)
        ->with(['status'])
        ->where('outstanding',0)
        ->where('company_id',$user->company_id)
        ->selectRaw('qr_code,product_type,dim_z,dim_x,dim_y,status_id,failure_notes,payer,cod,delivery_fee,receiver_address,zone_code,zone_name,receiver_name,receiver_phone,delivery_datetime,arrive_warehouse_datetime,driver_total,merchant_total');
        $packages = $query->get();
        foreach($packages as $pkg){
            $pkg->status_code = $pkg->status->name;
            $pkg->warehouse_timeago = Helper::timeAgo($pkg->arrive_warehouse_datetime);
            unset($pkg->status);
        }
        return ApiResponse::Pagination($packages,$req,__('messages.get_list',['info'=>'Package']));
    }

    public function assignDriver(Request $req){
        $user  = UserService::getAuthUser();
        $id = $req->id;
        $pacakge = Package::where('company_id',$user->company_id)->where('is_deleted',0)->where('outstanding',0)->find($id);
        if(!$pacakge) return ApiResponse::NotFound(__('messages.not_found'));
        $pacakge->update([
            'assign_driver_datetime' => now(),
        ]);
    }
}
