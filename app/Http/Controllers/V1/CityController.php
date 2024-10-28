<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Services\UserService;
use Illuminate\Http\Request;

class CityController extends Controller
{
    //

    function cityValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string',
            'country_id' => 'required|int',
            'name_kh' => 'nullable|string'
        ]);
    }
    public function createCity(Request $req){
        $validate = $this->cityValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        $country_id = $inputs['country_id'];
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $existCity = City::where('name',$name)->where('company_id',$user->company_id)->where('is_deleted',0)->where('country_id',$country_id)->take(1)->value('id');
        if($existCity) return ApiResponse::Duplicated('City ('.$name.') is already exists.');
        $create = City::create($inputs);

        if($create) return ApiResponse::JsonResult(null,'Created');

        return ApiResponse::Error('Fail to save country');
    }

    public function cities(Request $req){
        $user = UserService::getAuthUser();
        $cities = City::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('id,name,name_kh')->orderByDesc('id')->get();
        return ApiResponse::Pagination($cities,$req,'Get cities');
    }

    public function getDistrictsByCity(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $cities = District::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('city_id',$id)
        ->selectRaw('id,name,name_kh')->orderByDesc('id')->get();
        return ApiResponse::Pagination($cities,$req,'Get districts');
    }

    public function city(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $city = City::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        return ApiResponse::JsonResult($city,'Get on city');
    }

    public function updateCity(Request $req){
        $validate = $this->cityValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first(),'Please input correct data.');
        $inputs = $validate->validated();
        $name = $inputs['name'];
        $id = $req->id;
        $country_id = $inputs['country_id'];
        $user = UserService::getAuthUser();
        $city = City::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$city) return ApiResponse::NotFound(__('messages.not_found'));

        $existCity = City::where('name',$req->name)->where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$country_id)->where('id','!=',$id)->take(1)->value('id');
        if($existCity) return ApiResponse::Duplicated('City('.$name.') is already taken.');
        // return $user;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $city->update($inputs);
        if($update) return ApiResponse::JsonResult(null,'Update');

        return ApiResponse::Error('Fail to update');
    }

    public function deleteCity(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $city = City::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$city) return ApiResponse::NotFound(__('messages.not_found'));
        $city->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,'Deleted');
    }
}
