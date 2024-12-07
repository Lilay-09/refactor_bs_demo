<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\DriverService;
use App\Services\GeneralSettingService;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class DriverTransactionController extends Controller
{
    //
    public function getDeliveryPackages(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getDeliveryPackages($req,'driver',$user));
    }

    public function getDriverBalance(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->getDriverBalance($req,$user);
        return ApiResponse::flex($receive);
    }

    // DELIVERIES part
    public function receivePackagesPayment(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->receivePaymentService($req,$user,'driver');
        return ApiResponse::flex($receive);
    }

    public function getPayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getPayments($req,$user));
    }

    public function getApprovedPayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getApprovedPayments($req,$user));
    }

    public function settleApprovedPayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->settlePayments($req,$user));
    }

    public function approvePayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->approvePayments($req,$user));
    }
    public function deletePayment(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->deletePayment($id,'driver',$user));
    }

    public function updateDeliveryPackage(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updateDeliveryPackage($req,'driver',$user));
    }

    // public function getDriverDeliveredPackages(Request $req){
        // $user = UserService::getAuthUser();
        // $qD = User::where('account_type','driver')->with(['bank_accounts'])->where('company_id',$user->company_id)->where('is_deleted',0)
        // ->selectRaw('id,code,user_name');
    //     $order = Order::where('is_deleted',0)->where('company_id',$user->company_id)
    //     ->where('status_id',5) //** packages at warehouse */
    //     ->get();
    //     $drivers = $qD->get();
    //     $driverCommissions = DriverCommission::where('is_deleted',0)->orderByDesc('id')->get();

    //     foreach ($drivers as $driver){
    //         $pickupCount = $this->getDriverPickUpInfo($order,$driver->id);
    //         $rate = $this->getDriverRate($driverCommissions,$driver->id);
    //         $driver->rate = $rate;
    //         $driver->pickup = $pickupCount;
    //         $total = 0;
    //         $total += ($pickupCount * $rate->normal_pickup_commission);
    //         $driver->total = $total;
    //         foreach($driver->bank_accounts as $acc){
    //             if($acc->is_primary){
    //                 $driver->bank_info = (object)[
    //                     'bank_name' => $acc->bank_name
    //                 ];
    //             }else if(!$driver->bank_info){
    //                 $driver->bank_info = (object)[
    //                     'bank_name' => $acc->bank_name,
    //                     'bank_number' => $acc->bank_number,
    //                     'account_name' => $acc->account_name
    //                 ];
    //             }
    //         }
    //         unset($driver->bank_accounts);
    //     }
    //     return $drivers;
    // }

    // public function getDriverPickUpInfo($orders,$driver_id){
    //     $packages = 0;
    //     foreach($orders as $order){
    //         if($order->driver_id == $driver_id){
    //             $packages += $order->qty;
    //         }
    //     }
    //     return $packages;
    // }

    // public function getDriverRate($driverCommissions,$driverId){
    //     $dc = (object)[
    //         'normal_pickup_commission' => 0,
    //         'normal_delivery_commission' => 0,
    //         'fast_pickup_commission' => 0,
    //         'fast_delivery_commission' => 0
    //     ];
    //     foreach($driverCommissions as $driverComm){
    //         if($driverComm->driver_id == $driverId){
    //             if($driverComm->delivery_type == 'fast'){
    //                 $dc->fast_pickup_commission = $driverComm->pickup_commission;
    //                 $dc->fast_delivery_commission = $driverComm->delivery_commission;
    //             }
    //             if($driverComm->delivery_type == 'normal'){
    //                 $dc->normal_pickup_commission = $driverComm->pickup_commission;
    //                 $dc->normal_delivery_commission = $driverComm->delivery_commission;
    //             }
    //         }
    //     }
    //     return $dc;
    // }
}
