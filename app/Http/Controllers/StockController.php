<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    //
    public function getStockItems(Request $req){
        $stockItems = Stock::get();
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
