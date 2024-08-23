<?php

namespace App\Services;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\CustomerType;
use App\Models\ExpenseCategory;
use App\Models\ProductGroup;
use App\Models\ProductModel;
use App\Models\Vendor;
use App\Models\VendorType;
use User;

class GeneralSettingService
{
    // Your service methods go here
    public static function getOptionsVendor($user){
        return Vendor::where('branch_id',$user->branch_id)->selectRaw('id,name,phone')->get();
    }

    static function getModels($user){
        return ProductModel::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getModelByBrand($brand_id,$user){
        return ProductModel::where('company_id',$user->company_id)->where('brand_id',$brand_id)->selectRaw('id,name')->get();
    }
    static function getProductGroups($user){
        return ProductGroup::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getCustomerTypes($user){
        return CustomerType::where('branch_id',$user->branch_id)->selectRaw('id,name')->get();
    }

    static function getProductCategories($user){
        return Category::where('company_id',$user->company_id)->get();
    }
    static function getBrands($user){
        return Brand::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getExpenseCategories($user){
        return ExpenseCategory::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getCities($user){
        return City::selectRaw('id,name')->get();
    }

    static function getCountries($user){
        return Country::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getVendorTypes($user){
        return VendorType::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }
}
