<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    //
    public function formOptionDriver (){
        $user = UserService::getAuthUser();
        $obj =(object)[
            'warehouse' => GeneralSettingService::optionsWarehouse($user),
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function driverList(Request $req){
        $qD = User::where('account_type','driver')
        ->selectRaw('code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $drivers = $qD->get();
        return ApiResponse::JsonResult($drivers,'Driver List');
    }

    public function optionsWarehouse(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsWarehouse($user));
    }

    public function driverDeliverySummary(Request $req){
        $user = UserService::getAuthUser();
        $packages = Package::where('is_deleted',0)->whereIn('status_id',[9,10,19])->get();
        $qD = User::where('account_type','driver')
        ->selectRaw('id,code,user_name,gender,shift_type,phone,address,vehicle_type,plate_number,lock');
        $drivers = $qD->get();
        foreach($drivers as $d){
            $details = $this->getDriverSummaryDetails($packages,$d->id);
            $d->delivered_count = $details->delivered_count;
            $d->failed_with_fee_count = $details->failed_with_fee_count;
            $d->returned_count = $details->returned_count;
        }
        return ApiResponse::JsonResult($drivers);
    }

    private function getDriverSummaryDetails($rows,$driverId){
        $c = null;
        $i = 0;
        $delivered_count = 0;
        $failed_with_fee_count = 0;
        $returned_count = 0;
        do{
            if(!isset($rows[$i])) break;
            $c = $rows[$i];
            if($c->driver_id == $driverId){
                if($c->status_id == 9) $delivered_count +=1;
                if($c->status_id == 19) $failed_with_fee_count +=1;
                if($c->status_id == 11) $returned_count +=1;
            }
            $i++;
        }while($c);

        return (object)[
            'delivered_count' => $delivered_count,
            'failed_with_fee_count' => $failed_with_fee_count,
            'returned_count' => $returned_count
        ];
    }
}
