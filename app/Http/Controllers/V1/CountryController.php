<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Commune;
use App\Models\Country;
use App\Models\District;
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
        $existCountry = Country::where('name',$req->name)->where('is_deleted',0)->where('company_id',$user->company_id)->take(1)->value('id');
        if($existCountry) return ApiResponse::Duplicated('Country ('.$req->name.') is already exists.');

        $create = Country::create($inputs);
        if($create) return ApiResponse::JsonResult(null,'Created');

        return ApiResponse::Error('Fail to create');
    }

    public function countries(Request $req){
        $user = UserService::getAuthUser();
        if($req->id){
            return $this->country($req);
        }
        $countries = Country::where('is_deleted',0)->where('company_id',$user->company_id)->get();
        return ApiResponse::Pagination($countries,$req);
    }


    public function country(Request $req){
        $user = UserService::getAuthUser();
        $country = Country::selectRaw('id,name,name_kh')->where('is_deleted',0)->where('company_id',$user->company_id)->where('id',$req->id)->first();
        return ApiResponse::JsonResult($country);
    }

    public function getCitiesByCountry(Request $req){
        $user = UserService::getAuthUser();
        $countryId = $req->id;
        $cities = City::where('country_id',$countryId)->where('is_deleted',0)
        ->orderByDesc('id')
        ->where('company_id',$user->company_id)->get();
        return ApiResponse::JsonResult($cities);
    }
    public function getDistrictsByCountry(Request $req){
        $user = UserService::getAuthUser();
        $countryId = $req->id;
        $city_id = $req->query('city_id');
        $cityIds = City::where('country_id',$countryId)->where('company_id',$user->company_id)->where('is_deleted',0)->pluck('id')->toArray();
        $qD = District::where('is_deleted',0)->whereIn('city_id',$cityIds)->selectRaw('id,name,updated_at');
        if($city_id) $qD->where('city_id',$city_id);
        $disctricts = $qD->get();

        return ApiResponse::JsonResult($disctricts);
    }

    public function getCommunesByCountry(Request $req){
        $user = UserService::getAuthUser();
        $countryId = $req->id;
        $city_id = $req->city_id;
        $district_id = $req->district_id;
        $qCityIds = City::where('country_id',$countryId)->where('is_deleted',0);
        if($city_id) $qCityIds->where('id',$city_id);
        $cityIds = $qCityIds->pluck('id')->toArray();
        $qDistrictIds = District::where('is_deleted',0)->whereIn('city_id',$cityIds);
        if($district_id) $qDistrictIds->where('id',$district_id);
        $districtIds = $qDistrictIds->pluck('id')->toArray();
        $qC = Commune::where('is_deleted',0)->whereIn('district_id',$districtIds)->selectRaw('id,name,updated_at');
        $communes = $qC->get();
        return ApiResponse::JsonResult($communes);
    }

    public function updateCountry(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->countryValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $req->id;
        $inputs['company_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['update_uid'] = $user->id;
        $country = Country::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$country) return ApiResponse::NotFound(__('messages.not_found'));
        $existCountry = Country::where('name',$req->name)->where('company_id',$user->company_id)->where('id','!=',$id)->take(1)->value('id');
        if($existCountry) return ApiResponse::Duplicated('Country ('.$req->name.') is already exists.');
        $update = $country->update($inputs);
        if($update) return ApiResponse::JsonResult(null,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function deleteCountry(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $country = Country::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$country) return ApiResponse::NotFound(__('messages.not_found'));
        $country->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,'Deleted');
    }

}
