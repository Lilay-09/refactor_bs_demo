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

    public function getOptionsCity(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCity($user));
    }

    public function getOptionsDistrict(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDistrict($user));
    }

    public function getOptionsChannel(){
        return ApiResponse::JsonResult($this->gs::optionChannels());
    }

    public function getOptionsCommune(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCommune($user));
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

    public function getOptionsPickupStatus(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsTrackingStatus($user,[20],'pick'));
    }

    public function getFormSetOrderStatus(){
        $user = UserService::getAuthUser();

        $obj = [
            'statuses' => $this->gs::optionsTrackingStatus($user,[20],'pick'),
            'drivers' => $this->gs::optionsDriver($user)
        ];
        return ApiResponse::JsonResult($obj,'get form set order status');
    }

    public function getOptionsPriceListName(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsPriceListName($user));
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
            'delivery_type' => $this->gs::optionsDeliveryType(),
            'merchants' => $this->gs::optionsMerchant($user),
            'statuses' => $this->gs::optionsPickupStatus($user),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'vehicle_types' => $this->gs::optionsVehicleType($user),
            'drivers' => $this->gs::optionsDriver($user),
            'product_types' => $this->gs::optionsProductType($user)
        ];
        return ApiResponse::JsonResult($obj);
    }


    public function getFormPackageTrail(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'delivery_type' => $this->gs::optionsDeliveryType(),
            'merchants' => $this->gs::optionsMerchant($user),
            'statuses' => $this->gs::optionsPickupStatus($user),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'drivers' => $this->gs::optionsDriver($user),
            'zones' => $this->gs::optionsZone($user)
        ];
        return ApiResponse::JsonResult($obj);
    }


    public function getFormFleet(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'statuses' => $this->gs::optionsTrackingStatus($user,[15],'fleet'),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'drivers' => $this->gs::optionsDriver($user),
            'zones' => $this->gs::optionsZone($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormPromotion(){
        return ApiResponse::JsonResult($this->gs::optionChannels(1));
    }

    public function getFormRemark(){
        return ApiResponse::JsonResult($this->gs::optionChannels(0));
    }
    public function getOptionsDriver(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDriver($user));
    }

    public function getOptionsCurrencyPair(){
        return ApiResponse::JsonResult($this->gs::optionCurrencyPair());
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
