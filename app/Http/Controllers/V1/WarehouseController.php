<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\UserService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    //

    private object $authUser;
    public function __construct(private WarehouseService $warehouseService){
        $this->authUser = auth()->user();
    }

    public function createWarehouse(Request $req){
        return ApiResponse::flex($this->warehouseService->createWarehouse($req,$this->authUser));
    }

    public function getWarehouses(Request $req){
        return ApiResponse::flex($this->warehouseService->getWarehouses($req,$this->authUser));
    }

    public function updateWarehouse(Request $req){
        return ApiResponse::flex($this->warehouseService->updateWarehouse($req->id,$req,$this->authUser));
    }

    public function getOneWarehouse(Request $req){
        return ApiResponse::flex($this->warehouseService->getOneWarehouse($req->id,$this->authUser));
    }

    public function deleteWarehouse(Request $req){
        return ApiResponse::flex($this->warehouseService->deleteWarehouse($req->id,$this->authUser));
    }
}
