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

    public function getDriverCommissionPackage(Request $req){
        $driverId = $req->driver_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qD = User::query()->selectRaw('code,id,user_name as driver_name,phone as driver_phone')->where('account_type','driver');
        if($driverId) $qD->where('id',$driverId);
        // $driverInfo = $qD->get();
        $qP = Package::query()->selectRaw('status_id,driver_id')
        ->whereIn('status_id',[9,19])
        ->where('is_deleted',0)
        ->whereNull('driver_commission_id');
        $qO = Order::query()->where('is_deleted',0)->whereNull('driver_commission_id')->where('status_id',5);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->whereRaw("
                (
                    (status_id = 19 AND failed_datetime BETWEEN ? AND ?)
                    OR
                    (status_id = 9 AND delivered_datetime BETWEEN ? AND ?)
                )
            ", [
                "$startDate 00:00:00", "$endDate 23:59:59",
                "$startDate 00:00:00", "$endDate 23:59:59"
            ]);

            $qO->whereBetween('order_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        $qDc = DriverCommission::query()->where('is_deleted',0)->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission');
        if($driverId) {
            $qP->where('driver_id',$driverId);
            $qO->where('driver_id',$driverId);
            $qDc->where('driver_id',$driverId);
        }
        $packages = $qP->get();
        $orders = $qO->get();
        // if($driverId)
        $driverCommissions = $qDc->get();
        $clbMapper = function ($driver) use($driverCommissions,$orders,$packages) {
            $commissionInfo = $this->getDriverCommissionInfo($driverCommissions,$driver->id);
            $driver->pickup_rate = $commissionInfo->normal_pickup_commission;
            $driver->delivery_rate = $commissionInfo->normal_delivery_commission;
            $pickUpInfo = $this->getPickUpDetails($orders,$driver->id);
            $totalPickUp = $pickUpInfo->total_package;
            $driver->total_pickup = $totalPickUp;
            $deliverdInfo = $this->getDeliveredDetails($packages,$driver->id);
            $totalDelivered = $deliverdInfo->delivered_count;
            $driver->total_delivered = $totalDelivered;
            $driver->total_commission_packages = $deliverdInfo->total_commission_pkg;
            $driver->total = Helper::getNumber($driver->pickup_rate * $totalPickUp + $driver->delivery_rate * $totalDelivered,2);
            $driver->bank_account = null;
            $driver->status_code = 'Pending';
            foreach($driver->bank_accounts as $b){
                if($b->is_primary) $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                if(!$b->bank_account) $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
            }
            unset($driver->bank_accounts);
            return $driver;
        };
        return ApiResponse::PaginationV1($qD,$req,null,[],1000,$clbMapper);
        // return ApiResponse::Pagination($driverInfo,$req);
    }

    public function getDriverCommissionTrx(Request $req){
        $driverId = $req->driver_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qD = User::fromRaw('users as d')->where('d.account_type','driver')
        ->join('disbursements as dis','dis.payee_id','d.id')->where('dis.type','commission')
        ->join('users as rc','rc.id','dis.receiptionist_uid')
        ->where('dis.is_deleted',0)
        ->orderByDesc('dis.id')
        ->selectRaw('d.id as driver_id,dis.id as payment_id,d.user_name as driver_name,d.phone,d.code,dis.pickup_rate,dis.delivery_rate,dis.payment_datetime,dis.breakdown_notes,rc.user_name as receiptionist,payable_amount,package_count,delivered_package_count,pickup_package_count');
        if($driverId) $qD->where('d.id',$driverId);
        $driverInfo = $qD->get();
        foreach($driverInfo as $d){
            $d->paid_date = Helper::dateDMY($d->payment_datetime);
            $d->paid_time = Helper::formatCustomDateTime($d->payment_datetime,'h:i:s A');
            $d->status_code = 'Paid';
            unset($d->payment_datetime);
        }
        return ApiResponse::Pagination($driverInfo,$req);
    }

    public function getPickUpDetails($orders,$driverId){
        $totalPkg = 0;
        foreach($orders as $order){
            if($order->driver_id == $driverId){
                $totalPkg += $order->qty;
            }
        }
        return (object)[
            'total_package' => $totalPkg,
        ];
    }

    public function getDeliveredDetails($packages,$driverId){
        $totalPkg = 0;
        $failedWithFeeCount = 0;
        $deliveredCount = 0;
        $totalCommissionPkg = 0;
        foreach($packages as $pkg){
            if($pkg->driver_id == $driverId){
                $totalPkg += 1;
                if($pkg->status_id == 9) {$deliveredCount +=1; $totalCommissionPkg +=1;}
                if($pkg->status_id == 19) {$failedWithFeeCount +=1; $totalCommissionPkg +=1;}
            }
        }

        return (object)[
            'total_package' => $totalPkg,
            'delivered_count' => $deliveredCount,
            'failed_with_fee_count' => $deliveredCount,
            'total_commission_pkg' => $totalCommissionPkg
        ];
    }

    public static function getDriverCommissionInfo($driverCommissions,$driverId){
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        foreach($driverCommissions as $driverComm){
            if($driverComm->driver_id == $driverId){
                    if($driverComm->delivery_type == 'fast'){
                    $dc->fast_pickup_commission = $driverComm->pickup_commission;
                    $dc->fast_delivery_commission = $driverComm->delivery_commission;
                }
                if($driverComm->delivery_type == 'normal'){
                    $dc->normal_pickup_commission = $driverComm->pickup_commission;
                    $dc->normal_delivery_commission = $driverComm->delivery_commission;
                }
            }
        }

        return $dc;
    }

    public function getDriverBalance(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->getBalance($req,$user);
        return ApiResponse::flex($receive);
    }

    // DELIVERIES part
    public function receivePackagesPayment(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->receiveOrDisburesement($req,$user,'driver');
        return ApiResponse::flex($receive);
    }

    public function getPayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getPayments($req,$user,'driver'));
    }

    //** Settle Statement */
    public function getApprovedPayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getPayments($req,$user,'driver',1));
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
        return ApiResponse::flex($trxService->deletePayment($id,$req->payment_type,'driver',$user));
    }

    public function deleteSettlePayment(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        // \Log::error(json_encode($req->all()));
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->deleteSettlePayment($id,$req->payment_type,'driver',$user));
    }

    public function updateDeliveryPackage(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updateDeliveryPackage($req,'driver',$user));
    }

    public function disbursementDriverCommission(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->disbursementCommission($req,$user,'driver'));
    }

    public function deleteDriverCommission(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $id = $req->id;
        return ApiResponse::flex($trxService->deleteDisbursementCommission($id,$user,'driver'));
    }

}
