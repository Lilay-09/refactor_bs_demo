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
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductModel;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\ProductVariantTag;
use App\Models\Role;
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
        $vendors = Vendor::where('branch_id',$user->branch_id)->where('void',0)->selectRaw('id,name,phone,id as value')->get();
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
            'missing_qty' => 'Missing',
            'return_qty' => 'Return',
            'take_out_qty' => 'Take Out'
        ];
        return $movementTypes[$targetCol] ?? null;
    }



    static function getModels($user){
        return ProductModel::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getWarhouses($user){
        return StockLocation::where('company_id',$user->company_id)->whher('void',0)->selectRaw('id,name')->get();
    }

    static function getAdjustmentStatuses(){
        return [
            (object)[
                'label' => 'Pending',
                'value' => 'pending'
            ],
            // (object)[
            //     'label' => 'Partially Approved',
            //     'value' => 'partially approved'
            // ],
            (object)[
                'label' => 'Success',
                'value' => 'all approved'
            ],
        ];
    }
    static function getAdjustmentApproveStatuses(){
        return [
            (object)[
                'label' => 'Approved',
                'value' => 'approved'
            ],
            (object)[
                'label' => 'Pending',
                'value' => 'pending'
            ]
        ];
    }

    static function getModelsByBrand($brand_id,$user){
        return ProductModel::where('company_id',$user->company_id)->where('void',0)->where('brand_id',$brand_id)->selectRaw('id,name')->get();
    }
    static function getProductGroups($user){
        return ProductGroup::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getProducts($user){
        return Product::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getStockOption($user){
        $query = Stock::where('company_id',$user->company_id)->with(['variant','variant.product'])->where('void',0)->selectRaw('sku,variant_id');
        $stocks = $query->get();
        foreach($stocks as $item){
            $item->item_name = $item->variant->product->name.' |Color: '.$item->variant->color.', Size: '.$item->variant->size.', Condition: '.$item->variant->condition;
            unset($item->variant);
        }
        return $stocks;
    }

    static function getProductVariants($user){
        $variants = ProductVariant::with('product')->where('void',0)->where('company_id',$user->company_id)->selectRaw('id,product_id,size,color,sku,weight,width,length,expires_at,condition,material,cost')->get();
        foreach($variants as $vr){
            $vr->product_name = ($vr->product->code?($vr->product->code.'|'):'').$vr->product->name . '(Condition: '.$vr->condition.($vr->size ? ',Size: '.$vr->size:'').($vr->color ? ',Color: '.$vr->color:'').')';
            unset($vr->product);
        }
        return $variants;
    }


    static function getCustomers($user){
        $rows = Customer::where('company_id',$user->company_id)->where('void',0)->selectRaw('name,phone,id,discount_percent')->get();
        foreach($rows as $row){
            if($row->name){
                $row->name = $row->phone . '('.$row->name.')';
            }else $row->name = $row->phone;
            unset($row->phone);
        }
        return $rows;
    }

    static function getBanks($user){
        return Bank::selectRaw('id,name')->where('void',0)->get();
    }

    static function getStockItems($user,$req=null){
        $product_code = $req->product_code ?? null;
        $brand_id = $req->brand_id ?? null;
        $category_id = $req->category_id ?? null;
        $tags = $req->tags ?? null;
        if (is_string($tags)) {
            $tags = explode(',', strtolower($tags));
        }
        $query = Stock::with(['variant:id,size,color,condition,retail_price,expires_at,product_id,company_id','variant.product','variant.photos'])->where('void',0)->selectRaw('sku,variant_id,qty,retail_price,id,company_id')->where('company_id',$user->company_id);
        if(isset($tags[0])){
            $query->whereHas('variant.product.tags', function ($query) use ($tags){
                $query->whereIn('tag',$tags);
            });
        }
        if($product_code){
            $query->whereHas('variant.product',function ($query) use ($product_code){
                $query->where('code',$product_code);
            });
        }
        if($brand_id){
            $query->whereHas('variant.product.getModel',function ($query) use ($brand_id){
                $query->where('brand_id',$brand_id);
            });
        }

        if($category_id){
            $query->whereHas('variant.product',function ($query) use ($category_id){
                $query->where('category_id',$category_id);
            });
        }
        $stockItems = $query->get();
        foreach($stockItems as $item){
            $item->product_name = $item->variant->product->name;
            $item->product_description = $item->variant->product->description;
            $item->color = $item->variant->color;
            $item->size = $item->variant->size;
            $item->expires_at = $item->variant->expires_at;
            $item->condition = $item->variant->condition;
            $item->product_code = $item->variant->product->code;
            $item->retail_price = $item->retail_price > 0 ? $item->retail_price : $item->variant->retail_price;
            $item->product_details = 'Color: '.$item->color.', Size: '.$item->size.', Condition: '.$item->condition;
            foreach($item->variant->photos as $photo){
                    if($photo->is_thumbnail){
                        $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
                    }
                    if(!$item->image_url) $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
            }
            // unset($item->variant);
        }
        return $stockItems;
    }

    static function getServices($user,$req=null){
        $services =  Service::selectRaw('id,name,price,photo_file_name')->where('void',0)->where('company_id',$user->company_id)->get();
        foreach($services as $service){
            $service->image_url = Helper::getImageUrl($service->photo_file_name,$user->company_id,'service');
        }
        return $services;
    }

    static function getTags($user){
        return ProductTag::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name,name as label')->get();
    }

    static function getCustomerTypes($user){
        return CustomerType::where('branch_id',$user->branch_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getProductCategories($user){
        return Category::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }
    static function getBrands($user){
        return Brand::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getSellExchangeRate($user){
        return ExchangeRate::where('company_id',$user->company_id)->where('void',0)->take(1)->value('sell_rate') ?? 4000;
    }

    static function getExpenseCategories($user){
        return ExpenseCategory::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getCities($user){
        return City::selectRaw('id,name')->where('void',0)->get();
    }

    static function getCountries($user){
        return Country::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getVendorTypes($user){
        return VendorType::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getStockLocationTypes($user){
        return StockLocationType::selectRaw('id,name')->where('void',0)->get();
    }
    static function getBranches($user){
        return Branch::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name')->get();
    }

    static function getRoles($user){
        return Role::where('company_id',$user->company_id)->where('void',0)->get();
    }
}
