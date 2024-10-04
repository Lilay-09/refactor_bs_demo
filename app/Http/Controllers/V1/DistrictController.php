<?php

namespace App\Http\Controllers\V1;
use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Services\UserService;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    //

    function districtValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100',
            'city_id' => 'required|int|exists:cities,id'
        ]);
    }
    public function createDistrict(Request $req){
        $validate = $this->districtValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        $name_kh = $inputs['name_kh'];
        $city_id = $inputs['city_id'];
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $create = City::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('failed to create');
    }


    public function getDistricts(Request $req){
        $user = UserService::getAuthUser();
        $districts = District::where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('name,id,name_kh,city_id')->get();
        return ApiResponse::Pagination($districts,$req,'get all districts');
    }

    public function district(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $districts = District::where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('name,id,name_kh,city_id')->find($id);
        return ApiResponse::JsonResult($districts,false,'get one district');
    }


    public function updateDistrict(Request $req){
        $validate = $this->districtValidation($req);

        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $id = $req->id;
        $district = District::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$district) return ApiResponse::NotFound('District not found');
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $district->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('failed to update');
    }

    public function voidDistrict(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $district = District::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$district) return ApiResponse::NotFound('City not found');
        $district->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,false,'Deleted');
    }
}
