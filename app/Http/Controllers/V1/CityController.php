<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
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

        if($create) return ApiResponse::JsonResult(null,false,'Created');

        return ApiResponse::JsonResult([
            'error' => true,
            'message' => 'Fail to save country'
        ],500);
    }

    public function cities(Request $req){
        $user = UserService::getAuthUser();
        $cities = City::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('id,name,name_kh')->get();
        return ApiResponse::Pagination($cities,$req,'Get cities');
    }

    public function city(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $city = City::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        return ApiResponse::JsonResult($city,false,'Get on city');
    }

    public function updateCity(Request $req,$id=null){
        $validate = $this->cityValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first(),'Please input correct data.');
        $inputs = $validate->validated();
        $name = $inputs['name'];
        $id = $id ? $id :$req->id;
        $country_id = $inputs['country_id'];
        $user = UserService::getAuthUser();
        $city = City::find($id)->where('branch_id',$user->branch_id);
        if(!$city) return ApiResponse::NotFound(__('messages.not_found'));

        $existCity = City::where('name',$req->name)->where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$country_id)->where('id','!=',$id)->take(1)->value('id');
        if($existCity) return ApiResponse::Duplicated('City('.$name.') is already taken.');
        // return $user;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $city->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Update');

        return ApiResponse::Error('Fail to update');
    }

    public function voidCity(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $city = City::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$city) return ApiResponse::NotFound(__('messages.not_found'));
        $city->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,false,'Deleted');
    }
}
