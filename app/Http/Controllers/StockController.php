<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use Helper;
use Illuminate\Http\Request;
use DB;
class StockController extends Controller
{
    //
    public function getStockItems(Request $req){
        $stockItems = Stock::with(['variant.product','stockLocation.type','variant.photos'])->get();
        foreach($stockItems as $item){
            $item->product_name = $item->variant->product->name;
            $item->size = $item->variant->size;
            $item->product_name = $item->variant->product->name;
            $item->product_description = $item->variant->product->description;
            $item->color = $item->variant->color;
            $item->expires_at = $item->variant->expires_at;
            $item->condition = $item->variant->condition;
            $item->product_code = $item->variant->product->code;
            $item->product_details = 'Color: '.$item->color.', Size: '.$item->size.', Condition: '.$item->condition;
            $item->retail_price = $item->retail_price > 0 ? $item->retail_price : $item->variant->retail_price;
            $item->warehouse = $item->stockLocation->name.($item->stockLocation->main ? ' (Main Warehouse)':' (Branch Shop)');
            foreach($item->variant->photos as $photo){
                if($photo->is_thumbnail){
                    $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
                }
                if($item->image_url) $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
            }
            unset($item->stockLocation,$item->varaint);
        }
        return ApiResponse::Pagination($stockItems,$req);
    }

    public function getStockItem(Request $req,$ref=null){
        $ref = $ref ? $ref : $req->ref;
        $stockItem = Stock::with('variant.product')->where('sku',$ref)->orderByRaw('DATE(created_at) desc')->first();
        if(!$stockItem) if(is_numeric($ref)) $stockItem = Stock::with('variant.product')->orderByRaw('DATE(created_at) desc')->find($ref);
        if($stockItem){
            $stockItem->product_name = $stockItem->variant->product->name;
            $stockItem->size = $stockItem->variant->size;
            $stockItem->product_name = $stockItem->variant->product->name;
            $stockItem->product_description = $stockItem->variant->product->description;
            $stockItem->color = $stockItem->variant->color;
            $stockItem->expires_at = $stockItem->variant->expires_at;
            $stockItem->condition = $stockItem->variant->condition;
            $stockItem->product_code = $stockItem->variant->product->code;
            $stockItem->retail_price = $stockItem->retail_price > 0 ? $stockItem->retail_price : $stockItem->variant->retail_price;
            unset($stockItem->variant);
        }
        return ApiResponse::JsonResult($stockItem);
    }

    public function setStockItemPrices(Request $req){
        $id = $req->id;
        $validate = validator($req->all(),[
            'cost' => 'required|numeric',
            'retail_price' => 'required|numeric'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $stock = Stock::where('id',$id)->first();
        if(!$stock) return ApiResponse::NotFound('Item not found');
        $stock->update($inputs);
        return ApiResponse::JsonResult(null,false,'Price has been set');
    }

    public function getSortStockItems(Request $req){
        $stockItems = Stock::get();
        $items = $this->getStockItems($stockItems);
        return ApiResponse::JsonResult($stockItems);
    }

    private function getRecursiveSortStockItems($items){
        return $items;
    }
}
