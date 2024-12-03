<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class MerchantTransactionController extends Controller
{
    //
    protected $userClass = 'merchant';
    public function getDeliveryPackages(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getDeliveryPackages($req,$this->userClass,$user));
    }

    public function updateDeliveryPackage(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updateDeliveryPackage($req,$this->userClass,$user));
    }

    public function receivePackagesPayment(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->receivePaymentService($req,$user,$this->userClass);
        return ApiResponse::flex($receive);
    }
}
