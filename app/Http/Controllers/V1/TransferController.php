<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    //
    private object $authUser;
    public function __construct(private TransferService $transferService){
        $this->authUser = auth()->user();
    }

    public function createTransfer(Request $req){
        return ApiResponse::flex($this->transferService->createTransfer($req,$this->authUser));
    }
    public function updateTransfer(Request $req){
        return ApiResponse::flex($this->transferService->updateTransfer($req->id,$req,$this->authUser));
    }
    public function getTransfers(Request $req){
        return ApiResponse::flex($this->transferService->getTransfers($req,$this->authUser));
    }

    public function getOneTransfer(Request $req){
        return ApiResponse::flex($this->transferService->getOneTransfer($req->id,$this->authUser));
    }


}
