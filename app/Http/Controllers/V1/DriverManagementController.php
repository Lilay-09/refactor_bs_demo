<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverCommission;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class DriverManagementController extends Controller
{


    public function createDriver(Request $req){
        $user = UserService::getAuthUser();
        $createDriver = UserService::createOrUpdateUser($req,'driver',$user);
        return ApiResponse::flex($createDriver);
    }
    public function getDrivers(Request $req){
        $user = UserService::getAuthUser();
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)->where('account_type','driver');
        $drivers = $query->orderByDesc('id')->get();
        return ApiResponse::Pagination($drivers,$req);
    }

    public function getOneDriver(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $driver = User::where('is_deleted',0)->where('company_id',$user->company_id)->where('account_type','driver')->find($id);
        if(!$driver) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        return ApiResponse::JsonResult($driver,__('messages.get one'));
    }

    public function getDriverCommissions(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $driver = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('code,user_name,employment_date,shift_type,salary')
        ->where('account_type','driver')->find($id);
        if(!$driver) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        $driverCommissions = DriverCommission::where('driver_id',$id)->where('is_deleted',0)->get();
        foreach($driverCommissions as $driverComm){
            if($driverComm->delivery_type == 'fast'){
                $dc->fast_pickup_commission = $driverComm->pickup_commission;
                $dc->fast_delivery_commission = $driverComm->delivery_commission;
            }
            if($driverComm->delivery_type == 'normal'){
                $dc->normal_pickup_commission = $driverComm->pickup_commission;
                $dc->normal_delivery_commission = $driverComm->delivery_commission;
            }
        }
        foreach($dc as $key=>$d){
            $driver->{$key} = $dc->{$key};
        }

        return ApiResponse::JsonResult($driver,__('messages.info',['info' => 'Get Diver Commissions']));
    }

    public function updateDriver(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $createDriver = UserService::createOrUpdateUser($req,'driver',$user,$id);
        return ApiResponse::flex($createDriver);
    }
}
