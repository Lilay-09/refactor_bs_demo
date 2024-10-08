<?php

namespace App\Services;
use App\Models\City;
use App\Models\Commune;
use App\Models\District;
use App\Models\PriceList;
use App\Models\ProductType;
use App\Models\TrackingStatus;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Warehouse;
use App\Models\Zone;
use Helper;


class GeneralSettingService
{
    // Your service methods go here

    protected static $deliveryTypes = [
        ['value' => 'fast','label' => 'Fast'],
        ['value' => 'normal','label' => 'Normal'],
    ];

    public static function optionsZone($user){
        return Zone::where('status',1)->where('company_id',$user->company_id)->orWhere('is_deleted',0)->selectRaw('id,zone_name,zone_code',)->get();
    }

    public static function optionsWarehouse($user){
        return Warehouse::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->get();
    }

    public static function optionsPickupStatus($user){
        return TrackingStatus::where(function($q){
            $q->where('is_deleted',0)->orWhere('hidden',0);
        })->where('stage','pick')->selectRaw('id,name')->get();
    }

    public static function optionsDriver($user){
        return User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('company_id',$user->company_id)->where('account_type','driver')->selectRaw('id,user_name,phone')->get();
    }

    public static function getDriverById($id){
        return User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->find($id);
    }


    public static function optionsMerchant($user){
        return User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('company_id',$user->company_id)->where('account_type','merchant')->selectRaw('id,user_name,phone')->get();
    }

    public static function optionsVehicleType($user){
        return VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('name,name as value')->get();
    }

    public static function optionsProductType($user){
        return ProductType::where('company_id',$user->company_id)->where('is_deleted',0)->get();
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

    public static function priceByZone($zone_id,$user){
        $row =  PriceList::with(['zones'])
        ->where('status',1)
        ->where('company_id',$user->company_id)
        ->where('is_deleted',0)
        ->whereHas('zones',function($q) use($zone_id){
            $q->where('zone_id',$zone_id);
        })
        ->orderByDesc('id')
        ->selectRaw('base_fee,id,price')
        ->first();
        if($row) {
            $row->base_fee = $row->price > 0 ? $row->price : $row->base_fee;
            unset($row->zones,$row->price);
        }
        return $row;

    }

    public static function optionsCOD(){
        return [
            [
                'value' => 0,
                'lable' => 'No'
            ],
            [
                'value' => 1,
                'lable' => 'Yes'
            ],
        ];
    }

    public static function optionsPayer(){
        return [
            [
                'value' => 'sender',
                'lable' => 'Sender'
            ],
            [
                'value' => 'receiver',
                'lable' => 'Receiver'
            ],
        ];
    }

    public static function optionsDeliveryType(){
        return self::$deliveryTypes;
    }

    public static function getZonePriceByCode($zone_code){
        $user = UserService::getAuthUser();
        return PriceList::where('is_deleted',0)
        ->where('status',1)
        ->where('company_id',$user->company_id)
        ->whereHas('zones.zone',function ($q) use ($zone_code){
            $q->where('zone_code',$zone_code);
        })
        ->first();
    }

}
