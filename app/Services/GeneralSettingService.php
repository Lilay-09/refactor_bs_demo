<?php

namespace App\Services;
use App\Models\City;
use App\Models\Commune;
use App\Models\Country;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\District;
use App\Models\PriceList;
use App\Models\PriceListname;
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

    public static $channels = [
        ['value' => 'driver','label' => 'Driver'],
        ['value' => 'merchant', 'label'=>'Merchant']
    ];

    public static function optionChannels($idx=null){
        if($idx || $idx >= 0){
            return [self::$channels[$idx]];
        }
        return self::$channels;
    }

    public static function optionsZone($user){
        return Zone::where('status',1)->where('company_id',$user->company_id)->orWhere('is_deleted',0)->selectRaw('id,zone_name,zone_code')->orderByDesc('id')->get();
    }

    public static function optionsTrackingStatus($user,$exludeIds=[],$stage=null,$selectCols=null){
        if(!$selectCols) $selectCols = 'id,name';
        $q = TrackingStatus::where('hidden',0)->where('is_deleted',0)
        ->where('company_id',$user->company_id)
        ->selectRaw($selectCols);
        if($stage){
            $q->where('stage',$stage);
        }
        if(isset($exludeIds[0])){
            $q->whereNotIn('id',$exludeIds);
        }
        $statuses = $q->get();
        return $statuses;
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
        ->selectRaw('name,name as value,id')->orderByDesc('id')->get();
    }

    public static function optionsProductType($user){
        return ProductType::where('company_id',$user->company_id)->where('is_deleted',0)->orderByDesc('id')->get();
    }

    public static function optionsCityByCountry($countryId,$user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$countryId)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCountry($user){
        return Country::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCity($user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }
    public static function optionsDistrict($user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function getGeneralTopics($companyId=null,$channel=null,$userId=null){
        $topic = env('TOPIC_PREFIX');
        $obj = (object)[
            'private' => $companyId.$topic.$channel.'private'.$userId,
            'public' => $companyId.$topic.$channel.'public'
        ];
        return $obj;
    }

    public static function optionsCommune($user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }



    public static function optionsDistrictByCity($cityId,$user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->where('city_id',$cityId)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCommuneByDistrict($cityId,$user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)->where('district_id',$cityId)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsStatusPackageOnDelivery(){

    }

    public static function priceByZone($zone_id,$user){
        $row = PriceList::with(['zones'])
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

    public static function optionCurrencyPair(){
        return [
            [
                'label' => 'USD-KHR',
                'value' => 'USD-KHR'
            ]
        ];
    }

    public static function optionsPriceListName($user){
        return PriceListname::where('company_id',$user->company_id)->orderByDesc('id')->selectRaw('id,name,kg_marker')->get();
    }

    public static function getZonePriceByCode($zone_code){
        $user = UserService::getAuthUser();
        return PriceList::with('zones')->where('is_deleted',0)
        ->where('status',1)
        ->where('company_id',$user->company_id)
        ->whereHas('zones',function ($q) use ($zone_code){
            $q->where('zone_code',$zone_code);
        })
        ->first();
    }

    public static function calculatePackageFee($zone_code,$price,$billedKg,$actualKg,$payer,$cod,$taxi_fee=0){
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
        $driverTotal = 0;
        if($cod) $driverTotal += $price;
        $merchant_total += $additionalPrice;
        $total = $price + $additionalPrice;
        if($payer == 'receiver'){
            $total += $zPrice;
            $merchant_total = 0;
            $driverTotal += $zPrice;
        }

        return (object)[
            "error" => false,
            'delivery_fee' => $zPrice,
            'driver_total' => $driverTotal - $taxi_fee,
            'merchant_total' => $merchant_total,
            'total' => $total
        ];
    }

    public static function updateTripStatus($id,$user): void{
        $trip = Delivery::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if($trip){
            $queryDeliveryPackage = DeliveryPackage::where('delivery_id',$id);
            $deliveredCount = 0;
            $isCompleted = 0;
            $failCount = 0;
            $stillOnDelivery = 0;
            $packages = $queryDeliveryPackage->get();
            foreach($packages as $pck){
                if($pck->status_id == 9){
                    $deliveredCount += 1;
                }else if($pck->status_id == 10){
                    $failCount += 1;
                }
                if($pck->status_id == 6){
                    $stillOnDelivery = 6;
                }
            }
            if($trip->package_count == $failCount){
                $status_id = 17;
                $isCompleted = 1;
            // }else if($trip->package_count == $deliveredCount){
            //     $status_id = 15;
            }else if($trip->package_count >= $deliveredCount){
                $status_id = 16;
                if($stillOnDelivery) $status_id = 14;
            }
            $trip->update([
                'is_completed' => $isCompleted,
                'update_uid' => $user->id,
                'failed_count' => $failCount,
                'status_id' => $status_id,
                'delivered_count' => $deliveredCount
            ]);
        }
    }

}
