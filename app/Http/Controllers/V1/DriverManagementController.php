<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverCommission;
use App\Models\User;
use App\Models\UserTargetPolicy;
use App\Models\UserZone;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use App\Services\UserTargetPolicyService;
use Helper;
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
        $search = $req->search;
        $lang = $req->lang;
        $statusId = $req->status_id;
        $employeeType = $req->employee_type;
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type','driver')
        ->with('createUser:id,user_name')
        ->selectRaw('id,code,address,name_km,name_km as name_kh,user_name,email,gender,shift_type,vehicle_type,plate_number,phone,has_account,lock,photo_file_name,shift_type,employment_date,employee_type,dob,relative_name,national_id,create_uid,login_name');
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
        $query->orderByDesc('id');

        $callback = function ($driver) use($user,$lang){
            $driver->create_by = $driver->createUser->user_name;
            $driver->login_name = $driver->login_name ?? $driver->phone;
            $driver->employment_date = Helper::dateDMY($driver->employment_date,'d M Y',$lang);
            foreach($driver->bank_accounts as $b){
                if($b->is_primary) $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                if(!$b->bank_account) $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
            }
            $driver->image_url = Helper::getImageUrl($driver->photo_file_name,$user->company_id,'user_profile');
            unset($driver->bank_accounts,$driver->createUser);
            return $driver;
        };
        return ApiResponse::PaginationV1($query,$req,'',[],1000,$callback);
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
            'normal_pickup_commission_type' => 'nullable|in:percentage,amount',
            'normal_pickup_commission_start_date' => 'nullable',
            'normal_delivery_commission' => 'nullable|numeric',
            'normal_delivery_commission_type' => 'nullable|in:percentage,amount',
            'normal_delivery_commission_start_date' => 'nullable',
            'fast_pickup_commission' => 'nullable|numeric',
            'fast_pickup_commission_type' => 'nullable|in:percentage,amount',
            'fast_pickup_commission_start_date' => 'nullable',
            'fast_delivery_commission' => 'nullable|numeric',
            'fast_delivery_commission_type' => 'nullable|in:percentage,amount',
            'fast_delivery_commission_start_date' => 'nullable',
        ]);
    }

    public function saveDriverCommission(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->driverCommissionValidation($req);
        $driver_id = $req->id;
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $driverCommissions = DriverCommission::where('driver_id',$driver_id)->where('is_deleted',0)->first();
        $success = 0;
        $normal_pickup_commission = $inputs['normal_pickup_commission'] ?? 0;
        $normal_delivery_commission = $inputs['normal_delivery_commission'] ?? 0;
        $fast_pickup_commission = $inputs['fast_pickup_commission'] ?? 0;
        $fast_delivery_commission = $inputs['fast_delivery_commission'] ?? 0;

        $inputs['fast_delivery_commission_type'] = $inputs['fast_delivery_commission_type'] ?? 'amount';
        $inputs['fast_pickup_commission_type'] = $inputs['fast_pickup_commission_type'] ?? 'amount';
        $inputs['normal_delivery_commission_type'] = $inputs['normal_delivery_commission_type'] ?? 'amount';
        $inputs['normal_pickup_commission_type'] = $inputs['normal_pickup_commission_type'] ?? 'amount';

        $normal_pickup_commission_type = $inputs['normal_pickup_commission_type'];
        $normal_delivery_commission_type = $inputs['normal_delivery_commission_type'];
        $fast_pickup_commission_type = $inputs['fast_pickup_commission_type'];
        $fast_delivery_commission_type = $inputs['fast_delivery_commission_type'];

        $normal_pickup_commissionStartDate = $inputs['normal_pickup_commission_start_date'] ?? null;
        $normal_delivery_commissionStartDate = $inputs['normal_delivery_commission_start_date'] ?? null;
        $fast_pickup_commissionStartDate = $inputs['fast_pickup_commission_start_date'] ?? null;
        $fast_delivery_commissionStartDate = $inputs['fast_delivery_commission_start_date'] ?? null;
        $commissionArr = [
            [
                'driver_id' => $driver_id,
                'delivery_type' => 'normal',
                'pickup_commission' =>  $normal_pickup_commission,
                'pickup_commission_type' =>  $normal_pickup_commission_type,
                'pickup_commission_start_date' => $normal_pickup_commissionStartDate,
                'delivery_commission' => $normal_delivery_commission,
                'delivery_commission_type' => $normal_delivery_commission_type,
                'delivery_commission_start_date' => $normal_delivery_commissionStartDate
            ],
            [
                'driver_id' => $driver_id,
                'delivery_type' => 'fast',
                'pickup_commission' =>  $fast_pickup_commission,
                'pickup_commission_type' =>  $fast_pickup_commission_type,
                'pickup_commission_start_date' => $fast_pickup_commissionStartDate,
                'delivery_commission' => $fast_delivery_commission,
                'delivery_commission_type' => $fast_delivery_commission_type,
                'delivery_commission_start_date' => $fast_delivery_commissionStartDate
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

        $userTargetPolicyService = new UserTargetPolicyService();
        $setTargetPlc = $userTargetPolicyService->saveUserTargetPolicy(request()->merge([
            'target_value' => $req->monthly_target,
            'target_type' => 'package',
            'monthly_bonus' => $req->monthly_bonus,
            'monthly_bonus_type' => 'amount',
            'yearly_bonus' => $req->yearly_bonus,
            'yearly_bonus_type' => 'amount',
            'period_type' => 'monthly'
        ]), $driver_id, $user);

        if($setTargetPlc->error) return ApiResponse::flex($setTargetPlc);
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
        $userTargetPolicy = UserTargetPolicy::where('user_id',$id)->first();
        $driver->monthly_target = $userTargetPolicy->target_value ?? 0;
        $driver->monthly_bonus = $userTargetPolicy->monthly_bonus ?? 0;
        $driver->yearly_bonus = $userTargetPolicy->yearly_bonus ?? 0;
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'normal_pickup_commission_type' => 'percentage',
            'normal_delivery_commission_type' => 'percentage',
            'normal_pickup_commission_start_date' => 0,
            'normal_delivery_commission_start_date' => 0,
            'fast_pickup_commission' => 0,
            'fast_pickup_commission_type' => 'percentage',
            'fast_delivery_commission' => 0,
            'fast_delivery_commission_type' => 'percentage',
            'fast_pickup_commission_start_date' => 0,
            'fast_delivery_commission_start_date' => 0
        ];
        $driverCommissions = DriverCommission::where('driver_id',$id)->where('is_deleted',0)
        ->selectRaw('*,DATE(updated_at) as updated_date')
        ->orderByDesc('id')
        ->get();
        foreach($driverCommissions as $driverComm){
            if($driverComm->delivery_type == 'fast'){
                $dc->fast_pickup_commission = $driverComm->pickup_commission;
                $dc->fast_pickup_commission_type = $driverComm->pickup_commission_type;
                $dc->fast_delivery_commission = $driverComm->delivery_commission;
                $dc->fast_delivery_commission_type = $driverComm->delivery_commission_type;
                $dc->fast_pickup_commission_start_date = Helper::dateDMY($driverComm->pickup_commission_start_date) ?? $driverComm->updated_date;
                $dc->fast_delivery_commission_start_date = Helper::dateDMY($driverComm->delivery_commission_start_date) ?? $driverComm->updated_date;
            }
            if($driverComm->delivery_type == 'normal'){
                $dc->normal_pickup_commission = $driverComm->pickup_commission;
                $dc->normal_pickup_commission_type = $driverComm->pickup_commission_type;
                $dc->normal_delivery_commission = $driverComm->delivery_commission;
                $dc->normal_delivery_commission_type = $driverComm->delivery_commission_type;
                $dc->normal_pickup_commission_start_date = Helper::dateDMY($driverComm->pickup_commission_start_date) ?? $driverComm->updated_date;
                $dc->normal_delivery_commission_start_date = Helper::dateDMY($driverComm->delivery_commission_start_date) ?? $driverComm->updated_date;
            }
        }
        foreach($dc as $key=>$d){
            $driver->{$key} = $dc->{$key};
        }
        return ApiResponse::JsonResult($driver,__('messages.info',['info' => 'Get Diver Commissions']));
    }


    public function getDriverZones(Request $req){
        $driverZones = UserZone::where('user_id',$req->id)->select(['id','zone_id'])->with('sub_zones:user_zone_id,zone_id')->get();
        return ApiResponse::JsonResult($driverZones);
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

