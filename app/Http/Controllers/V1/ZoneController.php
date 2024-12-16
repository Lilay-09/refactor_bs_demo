<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PriceListZone;
use App\Models\Zone;
use App\Services\UserService;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    //
    public function zoneValidation(Request $req){
        return validator($req->all(),[
            'zone_code' => 'nullable|string|max:30',
            'zone_name' => 'required|string|max:50',
            'zone_type' => 'required|string|max:30',
            'district' => "nullable|string|exists:districts,name",
            'commune' => "nullable|string|exists:communes,name",
            'city' => "required|string|exists:cities,name",
            'country_id' => "required|exists:countries,id",
            'description' => "nullable|string|max:250",
        ]);
    }

    public function createZone(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->zoneValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputZoneCode = $inputs['zone_code'] ?? null;
        if($inputZoneCode){
            $existZone = Zone::where('zone_code',$inputs['zone_code'])->first();
            if($existZone) return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'Zone code ('.$inputs['zone_code'].') is already exists.'
            ]));
        }
        $existZoneName = Zone::where('zone_name',$inputs['zone_name'])->first();
        if($existZoneName) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'Zone name ('.$inputs['zone_name'].') is already exists.'
        ]));
        $create = Zone::create($inputs);
        if(!$create) return ApiResponse::Error('Fail to create zone');
        if(!$inputZoneCode) Zone::find($create->id)->update([
            'zone_code' => 'C'.$create->id
        ]);
        return ApiResponse::JsonResult(null,__('messages.created'));
    }

    public function getZones(Request $req){
        $user = UserService::getAuthUser();
        $search = $req->search;
        $query = Zone::where('is_deleted',0)->with('country:id,name')->selectRaw('id,zone_code,zone_type,zone_name,commune,description,city,district,country_id,status')->where('company_id',$user->company_id);
        if($search){
            $query->where('zone_name','ilike','%'.$search.'%')->orWhere('zone_code','ilike','%'.$search.'%');
        }
        $zones = $query->get();
        foreach($zones as $zone){
            $zone->country_name = $zone->country->name;
            unset($zone->country);
        }
        return ApiResponse::Pagination($zones,$req,'Get Zones');
    }

    public function getOneZone(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $zone = Zone::where(function($q){
            $q->where('is_deleted',0)->orWhere('status',1);
        })->where('company_id',$user->company_id)->selectRaw('id,zone_code,zone_type,zone_name,commune,description,city,district,country_id,status')->find($id);
        if(!$zone) return ApiResponse::NotFound(__('messages.not_found'));
        return ApiResponse::JsonResult($zone,__('messages.get one'));
    }

    public function updateZone(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $zone = Zone::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$zone) return ApiResponse::NotFound(__('messages.not_found'));
        $validate = $this->zoneValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $existsType = Zone::where('zone_code',$inputs['zone_code'])->where('id','!=',$id)->first();
        if($existsType) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'Zone code ('.$inputs['zone_code'].') is already exists.'
        ]));
        $existZoneName = Zone::where('zone_name',$inputs['zone_name'])->where('id','!=',$id)->first();
        if($existZoneName) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'Zone name ('.$inputs['zone_name'].') is already exists.'
        ]));
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $zone->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to create zone');
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function deleteZone(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $zone = Zone::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$zone) return ApiResponse::NotFound(__('messages.not_found'));
        $deletedArr = [
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now(),
        ];
        $zone->update($deletedArr);
        PriceListZone::where('zone_id',$id)->update($deletedArr);
        return ApiResponse::JsonResult(null,__('messages.deleted'));
    }
}
