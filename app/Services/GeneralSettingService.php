<?php

namespace App\Services;
use App\Models\City;
use App\Models\Commune;
use App\Models\District;
use App\Models\Zone;
use Helper;


class GeneralSettingService
{
    // Your service methods go here

    public static function optionsZone($user){
        return Zone::where('status',1)->where('company_id',$user->company_id)->orWhere('is_deleted',0)->selectRaw('zone_name,zone_code',)->get();
    }

    public static function optionsCityByCountry($countryId,$user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$countryId)->selectRaw('name,id')->get();
    }

    public static function optionsCountry($user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->get();
    }

    public static function optionsDistrictByCity($cityId,$user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->where('city_id',$cityId)->selectRaw('name,id')->get();
    }

    public static function optionsCommuneByDistrict($cityId,$user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)->where('district_id',$cityId)->selectRaw('name,id')->get();
    }

}
