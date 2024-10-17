<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\Tax;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Http\Request;

class GeneralSettingController extends Controller
{
    //

    protected $currencyPair = [
        ['name' => 'USD-KHR']
    ];

    protected $gs;

    public function __construct(GeneralSettingService $gs){
        $this->gs = $gs;
    }

    public function getOptionsCountry(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCountry($user));
    }

    public function getOptionsCityByCountry(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCityByCountry($req->country_id,$user));
    }

    public function getOptionsDistrictByCity(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDistrictByCity($req->city_id,$user));
    }

    public function getOptionsCommuneByDistrict(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCommuneByDistrict($req->district_id,$user));
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsZone($user));
    }

    public function getFormPriceList(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'zones' => $this->gs::optionsZone($user),
            'delivery_types' => $this->gs::optionsDeliveryType()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormPackage(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'zones' => $this->gs::optionsZone($user),
            'delivery_types' => $this->gs::optionsDeliveryType(),
            'cod' => $this->gs::optionsCOD(),
            'payers' => $this->gs::optionsPayer(),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPriceByZone(Request $req){
        $user = UserService::getAuthUser();
        $price = $this->gs::priceByZone($req->zone_id,$user);
        if(!$price) return ApiResponse::NotFound('Price not found');
        return ApiResponse::JsonResult($price,__('get zone price'));
    }

    public function getFormOrder(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'merchants' => $this->gs::optionsMerchant($user),
            'statuses' => $this->gs::optionsPickupStatus($user),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'vehicle_types' => $this->gs::optionsVehicleType($user),
            'drivers' => $this->gs::optionsDriver($user),
            'product_types' => $this->gs::optionsProductType($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    // public function getFormUser(){
    //     $user = UserService::getAuthUser();
    //     $obj = (object)[
    //         'roles' => $this->gs::getRoles($user),
    //         'branches' => $this->gs::getBranches($user),
    //     ];
    //     return ApiResponse::JsonResult($obj);
    // }


}
