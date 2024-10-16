<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class CompletedPackageController extends Controller
{
    //
    public function getFinishedPackages(Request $req){
        $query = Package::where('is_deleted',0)->whereIn('status_id',[9,10,11])
        ->selectRaw('qr_code,id,status_id,dim_x,dim_y,dim_z,order_id,failure_notes,payer,cod,price,delivery_fee,receiver_address,zone_code,zone_name,receiver_phone,delivery_type,actual_kg,billed_kg,delivered_datetime,failed_datetime,driver_total,merchant_total');
        $packages = $query->get();
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            unset($package->status);
        }
        return ApiResponse::Pagination($packages,$req);
    }
}
