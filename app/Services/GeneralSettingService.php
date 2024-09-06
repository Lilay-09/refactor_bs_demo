<?php

namespace App\Services;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductModel;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\ProductVariantTag;
use App\Models\Service;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockLocationType;
use App\Models\Vendor;
use App\Models\VendorType;
use Helper;
use Request;

class GeneralSettingService
{
    // Your service methods go here
    public static function getOptionsVendor($user){
        $vendors = Vendor::where('branch_id',$user->branch_id)->selectRaw('id,name,phone')->get();
        foreach($vendors as $v){
            $v->name = $v->phone.'('.($v->name ?? 'no name').')';
        }
        return $vendors;
    }


    public static function getMovementType($targetCol){
        $movementTypes = [
            'receive_qty' => 'Receive Order',
            'transfer_in_qty' => 'Transfer In',
            'transfer_out_qty' => 'Transfer Out',
            'sold_qty' => 'Sold',
            'return_qty' => 'Return',
        ];
        return $movementTypes[$targetCol] ?? null;
    }



    static function getModels($user){
        return ProductModel::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getWarhouses($user){
        return StockLocation::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getModelsByBrand($brand_id,$user){
        return ProductModel::where('company_id',$user->company_id)->where('brand_id',$brand_id)->selectRaw('id,name')->get();
    }
    static function getProductGroups($user){
        return ProductGroup::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getProducts($user){
        return Product::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

    static function getProductVariants($user){
        $variants = ProductVariant::with('product')->where('company_id',$user->company_id)->selectRaw('id,product_id,size,color,sku,weight,width,length,expires_at,condition,material,cost')->get();
        foreach($variants as $vr){
            $vr->product_name = ($vr->product->code?($vr->product->code.'|'):'').$vr->product->name . '(Condition: '.$vr->condition.($vr->size ? ',Size: '.$vr->size:'').($vr->color ? ',Color: '.$vr->color:'').')';
            unset($vr->product);
        }
        return $variants;
    }


    static function getCustomers($user){
        $rows = Customer::where('company_id',$user->company_id)->selectRaw('name,phone,id,discount_percent')->get();
        foreach($rows as $row){
            if($row->name){
                $row->name = $row->phone . '('.$row->name.')';
            }else $row->name = $row->phone;
            unset($row->phone);
        }
        return $rows;
    }

    static function getBanks($user){
        return Bank::selectRaw('id,name')->get();
    }

    static function getStockItems($user){
        $stockItems = Stock::with(['variant:id,size,color,condition,retail_price,expires_at,product_id,company_id','variant.product','variant.photos'])->selectRaw('sku,variant_id,qty,retail_price,id,company_id')->where('company_id',$user->company_id)->get();
        foreach($stockItems as $item){
            $item->product_name = $item->variant->product->name;
            $item->product_description = $item->variant->product->description;
            $item->color = $item->variant->color;
            $item->size = $item->variant->size;
            $item->expires_at = $item->variant->expires_at;
            $item->condition = $item->variant->condition;
            $item->product_code = $item->variant->product->code;
            $item->retail_price = $item->retail_price > 0 ? $item->retail_price : $item->variant->retail_price;
            foreach($item->variant->photos as $photo){
                    if($photo->is_thumbnail){
                        $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
                    }
                    if(!$item->image_url) $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
            }
            unset($item->variant);
        }
        return $stockItems;
    }

    static function getServices($user){
        $services =  Service::selectRaw('id,name,price,photo_file_name')->where('company_id',$user->company_id)->get();
        foreach($services as $service){
            $service->image_url = Helper::getImageUrl($service->photo_file_name,$user->company_id,'service');
        }
        return $services;
    }

    static function getTags($user){
        return ProductTag::where('company_id',$user->company_id)->selectRaw('id,name,name as label')->get();
    }

    static function getCustomerTypes($user){
        return CustomerType::where('branch_id',$user->branch_id)->selectRaw('id,name')->get();
    }

    static function getProductCategories($user){
        return Category::where('company_id',$user->company_id)->selectRaw('id,name')->get();
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

    static function getStockLocationTypes($user){
        return StockLocationType::selectRaw('id,name')->get();
    }
    static function getBranches($user){
        return Branch::where('company_id',$user->company_id)->selectRaw('id,name')->get();
    }

}
