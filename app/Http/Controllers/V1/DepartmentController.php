<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DepartmentService;
use App\Services\UserService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    //
    protected $departmentService;
    protected $authUser;
    public function __construct(DepartmentService $departmentService){
        $this->departmentService = $departmentService;
        $this->authUser = UserService::getAuthUser();
    }

    public function createDepartment(Request $req){
        return ApiResponse::flex($this->departmentService->createDepartment($req,$this->authUser));
    }

    public function updateDepartment(Request $req){
        return ApiResponse::flex($this->departmentService->updateDepartment($req,$req->id,$this->authUser));
    }

    public function getDepartments(Request $req){
        return ApiResponse::flex($this->departmentService->getDepartments($req,$this->authUser));
    }

    public function getOneDepartment(Request $req){
        return ApiResponse::flex($this->departmentService->getOneDepartment($req->id,$this->authUser));
    }

    public function deleteDepartment(Request $req){
        return ApiResponse::flex($this->departmentService->deleteDepartment($req->id,$this->authUser));
    }
}
