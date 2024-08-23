<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CustomerType;
use App\Models\ProductModel;
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



    // public function getFormProductOption(Request $req){
    //     $user = UserService::getAuthUser();
    //     return ApiResponse::JsonResult($this->gs::getFormProductOptionByType($req->type,$user));
    // }

    public function getFormProduct(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'groups' => $this->gs::getProductGroups($user),
            'brands' => $this->gs::getBrands($user),
            'categories' => $this->gs::getProductCategories($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formSupplier(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'vendor_types' => $this->gs::getVendorTypes($user),
            // 'cities' => $this->gs::getCities($user),
            'countries' => $this->gs::getCountries($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getCustomerTypes(){
        $user = UserService::getAuthUser();
        $customerTypes = $this->gs::getCustomerTypes($user);
        return ApiResponse::JsonResult($customerTypes);
    }

    public function getModels(){
        $user = UserService::getAuthUser();
        $models = $this->gs::getModels($user);
        return ApiResponse::JsonResult($models);
    }

    public function getModelByBrand(Request $req){
        $brand_id = $req->brand_id;
        $user = UserService::getAuthUser();
        $models = $this->gs::getModelByBrand($brand_id,$user);
        return ApiResponse::JsonResult($models);
    }

    public function getBrands(){
        $user = UserService::getAuthUser();
        $brands = $this->gs::getBrands($user);
        return ApiResponse::JsonResult($brands);
    }

    public function getExpenseCategories(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::getExpenseCategories($user));
    }
}
