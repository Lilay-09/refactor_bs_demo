<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MerchantEmployeeService;
use App\Services\UserService;
use Illuminate\Http\Request;

class MerchantEmployeeController extends Controller
{
    //
    protected $merchantEmpService;
    protected $authUser;
    public function __construct(MerchantEmployeeService $merchantEmployeeService){
        $this->merchantEmpService = $merchantEmployeeService;
        $this->authUser = UserService::getAuthUser();
    }

    public function createMerchantEmployee(Request $req){
        return ApiResponse::flex($this->merchantEmpService->createMerchantEmployee($req,$this->authUser));
    }

    public function getMerchantEmployees(Request $req){
        return ApiResponse::flex($this->merchantEmpService->getMerchantEmployees($req,$this->authUser));
    }

    public function getOneMerchantEmployee(Request $req){
        return ApiResponse::flex($this->merchantEmpService->getOneMerchantEmployee($req->id,$this->authUser));
    }

    public function updateMerchantEmployee(Request $req){
        return ApiResponse::flex($this->merchantEmpService->updateMerchantEmployee($req,$req->id,$this->authUser));
    }

    public function deleteMerchantEmployee(Request $req){
        return ApiResponse::flex($this->merchantEmpService->deleteMerchantEmployee($req->id,$this->authUser));
    }
}
