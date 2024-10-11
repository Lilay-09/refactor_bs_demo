<?php

namespace App\Services;
use App\Models\City;
use App\Models\Commune;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\District;
use App\Models\PriceList;
use App\Models\ProductType;
use App\Models\TrackingStatus;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Warehouse;
use App\Models\Zone;
use DataResponse;
use Helper;


class GeneralSettingService
{
    // Your service methods go here

    protected static $deliveryTypes = [
        ['value' => 'fast','label' => 'Fast'],
        ['value' => 'normal','label' => 'Normal'],
    ];

    public static function optionsZone($user){
        return Zone::where('status',1)->where('company_id',$user->company_id)->orWhere('is_deleted',0)->selectRaw('id,zone_name,zone_code')->orderByDesc('id')->get();
    }

    public static function optionsWarehouse($user){
        return Warehouse::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsPickupStatus($user){
        return TrackingStatus::where(function($q){
            $q->where('is_deleted',0)->orWhere('hidden',0);
        })->where('stage','pick')->selectRaw('id,name')->orderByDesc('id')->get();
    }

    public static function optionsDriver($user){
        return User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('company_id',$user->company_id)->where('account_type','driver')->selectRaw('id,user_name,phone')->orderByDesc('id')->get();
    }

    public static function getDriverById($id){
        return User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->orderByDesc('id')->find($id);
    }


    public static function optionsMerchant($user){
        return User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('company_id',$user->company_id)->where('account_type','merchant')->selectRaw('id,user_name,phone')->orderByDesc('id')->get();
    }

    public static function optionsVehicleType($user){
        return VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('name,name as value')->orderByDesc('id')->get();
    }

    public static function optionsProductType($user){
        return ProductType::where('company_id',$user->company_id)->where('is_deleted',0)->orderByDesc('id')->get();
    }

    public static function optionsCityByCountry($countryId,$user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$countryId)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCountry($user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsDistrictByCity($cityId,$user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->where('city_id',$cityId)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCommuneByDistrict($cityId,$user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)->where('district_id',$cityId)->selectRaw('name,id')->orderByDesc('id')->get();
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

    public static function calculatePackageFee($zone_code,$price,$billedKg,$actualKg,$payer){
        $priceList = GeneralSettingService::getZonePriceByCode($zone_code);
        if(!$priceList) return DataResponse::NotFound('Zone price not found');
        $zPrice = $priceList->price > 0 ? $priceList->price : $priceList->base_fee;
        $selectKg = $billedKg ?? $actualKg;
        $additionalPrice = 0;
        $merchant_total = $zPrice;
        if($selectKg >= $priceList->above_kg){
            $additionalPrice = $priceList->above_kg_price;
        }else if($selectKg < $priceList->above_kg && $selectKg >= $priceList->below_kg){
            $additionalPrice = $priceList->below_kg_price;
        }

        $driverTotal = $price;
        $merchant_total += $additionalPrice;
        $total = $price + $additionalPrice;
        if($payer == 'receiver'){
            $total += $zPrice;
            $merchant_total = 0;
        }

        return (object)[
            "error" => false,
            'delivery_fee' => $zPrice,
            'driver_total' => $driverTotal,
            'merchant_total' => $merchant_total,
            'total' => $total
        ];
    }

    public static function setDeliveryStatus($id,$user): void{

        $trip = Delivery::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if($trip){
            $deliveredCount = 0;
            $completeStatus = 0;
            $pQuery = DeliveryPackage::where('delivery_id',$id)->where('company_id',$user->company_id)->where('is_deleted',0);
            $packages = $pQuery->get();
            $onDelivery = 0;
            $failCount = 0;
            $count = $pQuery->count();
            foreach($packages as $package){
                if($package->status_id == 9){
                    $deliveredCount +=1;
                }
                if($package->status_id == 6) $onDelvery = 1;
                if($package->status_id == 10) $failCount +=1;

            }
            if($count == $deliveredCount){
                $completeStatus = 15; // all completed
            }else{
                if(!$onDelivery && $failCount>0){
                    if($failCount == $count) $completeStatus = 17; // failed
                    else $completeStatus = $completeStatus = 16; // Done
                }
            }

            if($completeStatus) $trip->update([
                'updated_uid' => $user->id,
                'status_id' => $completeStatus
            ]);
        }

    }

}
