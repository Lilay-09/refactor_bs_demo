<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\EmploymentPositionService;
use App\Services\UserService;
use Illuminate\Http\Request;

class EmploymentPositionController extends Controller
{
    //

    protected $empPosService;
    protected $authUser;
    public function __construct(EmploymentPositionService $employmentPositionService){
        $this->empPosService = $employmentPositionService;
        $this->authUser = UserService::getAuthUser();
    }

    public function createEmploymentPosition(Request $req){
        return ApiResponse::flex($this->empPosService->createEmploymentPosition($req,$this->authUser));
    }

    public function getEmploymentPositions(Request $req){
        return ApiResponse::flex($this->empPosService->getEmploymentPositions($req,$this->authUser));
    }

    public function getOneEmploymentPosition(Request $req){
        return ApiResponse::flex($this->empPosService->getOneEmploymentPosition($req->id,$this->authUser));
    }

    public function updateEmploymentPosition(Request $req){
        return ApiResponse::flex($this->empPosService->updateEmploymentPosition($req,$req->id,$this->authUser));
    }

    public function deleteEmploymentPosition(Request $req){
        return ApiResponse::flex($this->empPosService->deleteEmploymentPosition($req->id,$this->authUser));
    }
}
