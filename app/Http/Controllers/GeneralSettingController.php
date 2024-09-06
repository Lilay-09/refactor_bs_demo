<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CustomerType;
use App\Models\ProductModel;
use App\Models\StockLocation;
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
            'tags' => $this->gs::getTags($user),
            'categories' => $this->gs::getProductCategories($user)
        ];
        return ApiResponse::JsonResult($obj);
    }


    public function getWarehouses (){
        $user = UserService::getAuthUser();
        $stockLocation = StockLocation::where('company_id',$user->company_id)->selectRaw('id,name')->get();
        return ApiResponse::JsonResult($stockLocation);
    }

    public function formPOS(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'customers' => $this->gs::getCustomers($user),
            'tags' => $this->gs::getTags($user),
            'categories' => $this->gs::getProductCategories($user),
            'brands' => $this->gs::getBrands($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formPosItems(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'items' => $this->gs::getStockItems($user),
            'service' => $this->gs::getServices($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getBanks(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::getBanks($user));
    }

    public function getProducts(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::getProducts($user));
    }
    public function getStockLocationTypes(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::getStockLocationTypes($user));
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

    public function formWarehouse(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'warehouse_types' => $this->gs::getStockLocationTypes($user),
            'branches' => $this->gs::getBranches($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formReceive(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'warehouses' => $this->gs::getWarhouses($user),
            'banks'=> $this->gs::getBanks($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formTransfer(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'warehouses' => $this->gs::getWarhouses($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function formPurchase(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'vendors' => $this->gs::getOptionsVendor($user),
            'warehouses' => $this->gs::getWarhouses($user),
            'banks'=> $this->gs::getBanks($user),
            'items' => $this->gs::getProductVariants($user),
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


    public function getModelsByBrand(Request $req){
        $brand_id = $req->brand_id;
        $user = UserService::getAuthUser();
        $models = $this->gs::getModelsByBrand($brand_id,$user);
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
