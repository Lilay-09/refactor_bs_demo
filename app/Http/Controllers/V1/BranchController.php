<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\BranchService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    //
    private object $authUser;
    public function __construct(private BranchService $branchService){
        $this->authUser = auth()->user();
    }

    public function getBranches(Request $req){
        return ApiResponse::flex($this->branchService->getBranches($req,$this->authUser));
    }


    public function getOneBranch(Request $req){
        return ApiResponse::flex($this->branchService->getOneBranch($req->id,$this->authUser));
    }

    public function updateBranch(Request $req){
        return ApiResponse::flex($this->branchService->updateBranch($req->id,$req,$this->authUser));
    }

    public function createBranch(Request $req){
        return ApiResponse::flex($this->branchService->createBranch($req,$this->authUser));
    }

    public function deleteBranch(Request $req){
        return ApiResponse::flex($this->branchService->deleteBranch($req->id,$this->authUser));
    }
}
