<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\StockZoneService;
use App\Services\UserService;
use Illuminate\Http\Request;

class StockZoneController extends Controller
{
    //
    protected StockZoneService $_stockZoneService;
    protected $_user;
    public function __construct(StockZoneService $stockZoneService){
        $this->_stockZoneService = $stockZoneService;
        $this->_user = UserService::getAuthUser();
    }

    public function createStockZone(Request $req){
        return ApiResponse::flex($this->_stockZoneService->createStockZone($req,$this->_user));
    }

    public function getStockZones(Request $req){
        return ApiResponse::flex($this->_stockZoneService->getStockZones($req,$this->_user));
    }
    public function getOneStockZone(Request $req){
        return ApiResponse::flex($this->_stockZoneService->getOneStockZone($req->id,$this->_user));
    }
    public function updateStockZone(Request $req){
        return ApiResponse::flex($this->_stockZoneService->updateStockZone($req,$req->id,$this->_user));
    }
}
