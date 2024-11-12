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

    public function getFormZone(){
        $user = UserService::getAuthUser();

        $obj = [
            'zone_types' => $this->gs::optionsZoneType(),
            'countries' => $this->gs::optionsCountry($user)
        ];
        return ApiResponse::JsonResult($obj);
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
            'statuses' => $this->gs::optionsTrackingStatus($user,[20],[],'pick'),
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
            'statuses' => $this->gs::optionsTrackingStatus($user,[15],[],'fleet'),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'drivers' => $this->gs::optionsDriver($user),
            'zones' => $this->gs::optionsZone($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormMerchant(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'merchant_types' => $this->gs::optionsBusinessType($user),
            'business_types' => $this->gs::optionsBusinessType($user),
            'cods' => $this->gs::optionsCOD(),
            'genders' => $this->gs::optionsGender(),
            'price_list' => $this->gs::optionsPriceList($user),
            'referrers' => $this->gs::optionsMerchant($user),
            'banks' => $this->gs::optionsBank($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormDriver(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'employee_types' => $this->gs::optionsEmployeeType(),
            'shitf_types' => $this->gs::optionsShiftType(),
            'genders' => $this->gs::optionsGender(),
            'vehicle_types' => $this->gs::optionsVehicleType($user),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'banks' => $this->gs::optionsBank($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsVehicleType(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsVehicleType($user));
    }
    // public function getOptionsDriverByVehicleType(Request $req){
    //     $user = UserService::getAuthUser();
    //     return ApiResponse::JsonResult($this->gs::optionsDriverByVehicleType($req->vehicle_type,$user));
    // }

    public function getFormPromotion(){
        return ApiResponse::JsonResult($this->gs::optionChannels(1));
    }

    public function getFormRemark(){
        $obj = (object)[
            'channels' => $this->gs::optionChannels(0),
            'categories' => $this->gs::optionsRemarkCategory()
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getOptionsDriver(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDriver($user,$req->vehicle_type));
    }

    public function getOptionsCurrencyPair(){
        return ApiResponse::JsonResult($this->gs::optionCurrencyPair());
    }




    public function getFormFinished(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'warehouses' => $this->gs::optionsWarehouse($user),
            'merchants' => $this->gs::optionsMerchant($user),
            'drivers' => $this->gs::optionsDriver($user),
            'delivery_types' => $this->gs::optionsDeliveryType(),
            'payment_statuses' => $this->gs::paymentStatus(),
            'statuses' => $this->gs::optionsTrackingStatus($user,[],[9,11,19],'delivery')
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
