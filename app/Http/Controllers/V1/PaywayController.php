<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\PaywayService;
use App\Services\PaywayServiceImpl;
use App\Services\UserService;
use Illuminate\Http\Request;

class PaywayController extends Controller
{
    //
    public function __construct(private PaywayService $paywayService){

    }

    public function getABAKHQRPayload(Request $req){
        $authUser = UserService::getAuthUser();
        return ApiResponse::flex($this->paywayService->bankABAKHQRGeneratePayload($authUser,$req));
    }

    public function receiverCallbackPayment(Request $req){
        return ApiResponse::flex($this->paywayService->receiverPayCallbackUrl($req));
    }
    public function driverCallbackPayment(Request $req){
        return ApiResponse::flex($this->paywayService->DriverPayCallbackUrl($req));
    }

    public function checkTransaction(Request $req){
        return ApiResponse::flex(PaywayServiceImpl::verifyTransaction($req->tranId));
    }

    public function deeplinkAfterKHQRScan(Request $req){
        return $this->paywayService->deeplinkAfterKHQRScan($req->userId,null);
    }

    public function streamRedirect(Request $req){
        // \Log::info($req->ip());
        return $this->paywayService->streamRedirect($req);
        // return ApiResponse::flex($this->paywayService->streamRedirect($req));
    }

    public function getPaylogs(Request $req){
        // $pwl = PaywayLog::orderByDesc('id')->get();
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->paywayService->getPaywayLogs($req,$user));
    }

    public function generateCheckTransaction(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->paywayService->generateCheckTransactionPayload($user,$req));
    }


    public function gernerateSettleABAQrPayload(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->paywayService->bankABAKHQRSettleGeneratePayload($req,$user));
    }

    // public function payout(){
    //     $user = UserService::getAuthUser();
    //     return ApiResponse::flex($this->paywayService->payout('USD',33.40,[
    //         [
    //             "account" => "500000001",
    //             "amount" => 33.40
    //         ]
    //     ]));
    // }
}
