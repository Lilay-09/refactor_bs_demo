<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;
use DB;
class StockController extends Controller
{
    //
    public function getStockItems(Request $req){
        $stockItems = Stock::with('variant.product')->get();
        foreach($stockItems as $item){
            $item->product_name = $item->variant->product->name;
            $item->size = $item->variant->size;
            $item->product_name = $item->variant->product->name;
            $item->product_description = $item->variant->product->description;
            $item->color = $item->variant->color;
            $item->expires_at = $item->variant->expires_at;
            $item->condition = $item->variant->condition;
            $item->product_code = $item->variant->product->code;
            $item->retail_price = $item->retail_price > 0 ? $item->retail_price : $item->variant->retail_price;
            unset($item->variant);
        }
        return ApiResponse::Pagination($stockItems,$req);
    }

    public function setStockItemPrices(Request $req){
        $sku = $req->sku;
        $stock = Stock::where('sku',$sku)->first();
        if(!$stock) return ApiResponse::NotFound('Item not found');
        return ApiResponse::JsonResult($stock);
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
