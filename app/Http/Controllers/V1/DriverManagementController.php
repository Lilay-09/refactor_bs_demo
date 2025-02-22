<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\GeneralSettingService;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
use Log;

class DriverManagementController extends Controller
{
    public function createDriver(Request $req){
        $user = UserService::getAuthUser();
        $createDriver = UserService::createOrUpdateUser($req,'driver',$user);
        return ApiResponse::flex($createDriver);
    }
    public function getDrivers(Request $req){
        $user = UserService::getAuthUser();
        $search = $req->search;
        $statusId = $req->status_id;
        $employeeType = $req->employee_type;
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type','driver')
        ->with('createUser:id,user_name')
        ->selectRaw('id,code,address,name_km,name_km as name_kh,user_name,email,gender,shift_type,vehicle_type,plate_number,phone,has_account,lock,photo_file_name,shift_type,employment_date,employee_type,dob,relative_name,national_id,create_uid');
        if($employeeType){
            $query->where('employee_type',$employeeType);
        }

        if($statusId !== null && $statusId>=0) {
            $query->where('lock',$statusId ? 0 : 1);
        }

        if($search){
            $query->where(function($q) use ($search){
                $q->where('code','ilike','%'.$search.'%')
                ->orWhere('user_name','ilike','%'.$search.'%')
                ->orWhere('name_km','ilike','%'.$search.'%')
                ->orWhere('phone','ilike','%'.$search.'%');
            });
        }

        $drivers = $query->orderByDesc('id')->get();

        foreach ($drivers as $driver){
            $driver->create_by = $driver->createUser->user_name;
            foreach($driver->bank_accounts as $b){
                if($b->is_primary) $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                if(!$b->bank_account) $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
            }
            $driver->image_url = Helper::getImageUrl($driver->photo_file_name,$user->company_id,'user_profile');
            unset($driver->bank_accounts,$driver->createUser);
        }
        return ApiResponse::Pagination($drivers,$req);
    }

    public function getOneDriver(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $driver = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type','driver')
        ->with(['bank_accounts:id,user_id,bank_name,bank_number,account_name,is_primary'])
        ->selectRaw('*,driver_warehouse_id as warehouse_id')
        ->find($id);
        if(!$driver) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        $driver->image_url = Helper::getImageUrl($driver->photo_file_name,$user->company_id,'user_profile');
        return ApiResponse::JsonResult($driver,__('messages.get one'));
    }

    private function driverCommissionValidation(Request $req){
        return validator($req->all(),[
            'salary' => 'nullable|numeric',
            'normal_pickup_commission' => 'nullable|numeric',
            'normal_delivery_commission' => 'nullable|numeric',
            'fast_pickup_commission' => 'nullable|numeric',
            'fast_delivery_commission' => 'nullable|numeric'
        ]);
    }

    public function saveDriverCommission(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->driverCommissionValidation($req);
        $driver_id = $req->id;
        if($validate->fails()) return ApiResponse::ValidateFail($validate);
        $inputs = $validate->validated();
        $driverCommissions = DriverCommission::where('driver_id',$driver_id)->where('is_deleted',0)->first();
        $success = 0;
        $normal_pickup_commission = $inputs['normal_pickup_commission'] ?? 0;
        $normal_delivery_commission = $inputs['normal_delivery_commission'] ?? 0;
        $fast_pickup_commission = $inputs['fast_pickup_commission'] ?? 0;
        $fast_delivery_commission = $inputs['fast_delivery_commission'] ?? 0;
        $commissionArr = [
            [
                'driver_id' => $driver_id,
                'delivery_type' => 'normal',
                'pickup_commission' =>  $normal_pickup_commission,
                'delivery_commission' => $normal_delivery_commission
            ],
            [
                'driver_id' => $driver_id,
                'delivery_type' => 'fast',
                'pickup_commission' =>  $fast_pickup_commission,
                'delivery_commission' => $fast_delivery_commission
            ],
        ];
        if($driverCommissions){
            foreach($commissionArr as $c){
                $c['update_uid'] = $user->id;
                $c['branch_id'] = $user->branch_id;
                $c['company_id'] = $user->company_id;
                DriverCommission::where('driver_id',$driver_id)->where('is_deleted',0)->where('delivery_type',$c['delivery_type'])->update($c);
                $success = 1;
            }
        }else{
            foreach($commissionArr as $c){
                $c['create_uid'] = $user->id;
                $c['update_uid'] = $user->id;
                $c['branch_id'] = $user->branch_id;
                $c['company_id'] = $user->company_id;
                DriverCommission::create($c);
                $success = 1;
            }
        }
        User::find($driver_id)->update([
            'salary' => $inputs['salary'] ?? 0
        ]);
        if($success) return ApiResponse::JsonResult(null,__('messages.saved'));
        return ApiResponse::Error(__('messages.error',['info' => 'Fail to save commission']));
    }

    public function getDriverCommissions(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $driver = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('code,user_name,employment_date,shift_type,salary,employee_type')
        ->where('account_type','driver')->find($id);
        if(!$driver) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        $driverCommissions = DriverCommission::where('driver_id',$id)->where('is_deleted',0)->orderByDesc('id')->get();
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

    public function createDriverAccount(Request $req){
        $user = UserService::getAuthUser();
        $driverId = $req->id;
        $createDriver = UserService::createLoginAccount($req,$driverId,'driver',$user);
        return ApiResponse::flex($createDriver);
    }

    public function setLockDriver(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setLockUser($user,$req->id,'driver'));
    }


    public function setPassword(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setNewPassword($req,$req->id,'driver',$user));
    }

    public function deleteDriver(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::deleteUser($req->id,'driver',$user));
    }
}

