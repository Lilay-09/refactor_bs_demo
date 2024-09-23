<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Models\City;
use App\Models\Country;
use App\Services\UserService;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    //

    function countryValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100'
        ]);
    }

    public function createCountry(Request $req){
        $validate = $this->countryValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $existCountry = Country::where('name',$req->name)->where('void',0)->where('company_id',$user->company_id)->take(1)->value('id');
        if($existCountry) return ApiResponse::Duplicated('Country ('.$req->name.') is already exists.');

        $create = Country::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');

        return ApiResponse::Error('Fail to create');
    }

    public function countries(Request $req){
        $user = UserService::getAuthUser();
        if($req->id){
            return $this->country($req);
        }
        $countries = Country::with('cities:id,country_id,name,name_kh')->where('void',0)->where('company_id',$user->company_id)->get();
        return ApiResponse::JsonResult($countries);
    }


    public function country(Request $req){
        $user = UserService::getAuthUser();
        $country = Country::selectRaw('id,name,name_kh')->where('void',0)->where('company_id',$user->company_id)->where('id',$req->id)->first();
        return ApiResponse::JsonResult($country);
    }

    public function getCities(Request $req,$id = null){
        $user = UserService::getAuthUser();
        $cities = City::where('country_id',$req->country_id)->where('void',0)->where('company_id',$user->company_id)->get();
        return ApiResponse::JsonResult($cities);
    }

    public function updateCountry(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $validate = $this->countryValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $id ? $id : $req->id;

        $inputs['company_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['update_uid'] = $user->id;
        $country = Country::where('company_id',$user->company_id)->where('void',0)->find($id);
        if(!$country) return ApiResponse::NotFound('Country not found');

        $existCountry = Country::where('name',$req->name)->where('company_id',$user->company_id)->where('id','!=',$id)->take(1)->value('id');
        if($existCountry) return ApiResponse::Duplicated('Country ('.$req->name.') is already exists.');
        $update = $country->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function voidCountry(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $country = Country::where('company_id',$user->company_id)->where('void',0)->find($id);
        if(!$country) return ApiResponse::NotFound('Country not found');
        $country->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);

        return ApiResponse::JsonResult(null,false,'Voided');
    }

}
