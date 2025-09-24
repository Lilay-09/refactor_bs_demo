<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\GeneralSettingService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Http\Request;
// use Log;

class DriverTransactionController extends Controller
{
    //
    public function getDeliveryPackages(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getDeliveryPackages($req,'driver',$user));
    }
    public function getDeliveryPackagesV1(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getDeliveryPackagesV1($req,'driver',$user));
    }

    public function getDriverCommissionPackage(Request $req){
        $driverId = $req->driver_id;
        // Log::info($req->all());
        $qD = User::query()->selectRaw('code,id,username as driver_name,phone as driver_phone')->where('account_type','driver');
        if($driverId) $qD->where('id',$driverId);
        $qP = Package::query()->from('packages as p')
        ->select('p.status_id','p.driver_id','p.delivery_type')
        // ->whereIn('p.status_id',[9,19])
        ->whereIn('p.status_id',[9])
        ->where('p.is_deleted',0)
        // ->whereNull('driver_commission_id');
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.type','commission')
                ->where('dp.is_deleted', false);
        });
        $qO = Order::query()
        ->select('id','driver_id') // select only needed columns
        ->where('is_deleted', 0)
        ->whereNull('driver_commission_id')
        ->where('status_id', 5)
        ->withCount([
            'packages as qty' => fn($q) => $q
                ->where('status_id', 9)
                ->where('is_deleted', 0)
        ])
        ->groupBy('id')
        ->having('qty', '>', 0);

        $qDc = DriverCommission::query()->where('is_deleted',0)->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission,delivery_commission_type,pickup_commission_start_date,delivery_commission_start_date');
        if($driverId) {
            $qP->where('driver_id',$driverId);
            $qO->where('driver_id',$driverId);
            $qDc->where('driver_id',$driverId);
        }

        // if($driverId)
        $driverCommissions = $qDc->get();
        // $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        // return $driverCommissionInfo;
        // $pickUpStartDate = $req->query('startDate',$driverCommissionInfo->normal_pickup_commission_start_date);
        $startDateFromQuery = $req->query('startDate');
        $endDate = $req->query('endDate');
        // $defaultNormalDeliveryDate = $driverCommissionInfo->normal_delivery_commission_start_date;
        // $defaultFastDeliveryDate = $driverCommissionInfo->fast_delivery_commission_start_date;
        // $defaultNormalPickUpDate = $driverCommissionInfo->normal_pickup_commission_start_date;

        // $normalDeliveryStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalDeliveryDate)
        //     : null;
        // $fastDeliveryStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultFastDeliveryDate)
        //     : null;
        // $normalPickUpStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalPickUpDate)
        //     : null;
        $normalDeliveryStartDate = Helper::dateYMD($startDateFromQuery);
        $fastDeliveryStartDate = null;
        $normalPickUpStartDate = $normalDeliveryStartDate;
        $endDate = $endDate ? Helper::dateYMD($endDate). ' 23:59:59' : null;
        // return $endDate;

        // \Log::info($normalDeliveryStartDate);
        if($normalDeliveryStartDate && $endDate){
            $qP->where(function ($q) use ($normalDeliveryStartDate,$fastDeliveryStartDate, $endDate) {
                $q->where(function ($q) use ($normalDeliveryStartDate, $endDate) {
                    $q->where('p.delivery_type', 'normal')
                    ->whereBetween('p.delivered_datetime',[$normalDeliveryStartDate,$endDate]);
                    // ->whereRaw("
                    //         (
                    //             (p.status_id = 19 AND p.failed_datetime BETWEEN ? AND ?)
                    //             OR
                    //             (p.status_id = 9 AND p.delivered_datetime BETWEEN ? AND ?)
                    //         )
                    //     ", [
                    //         $normalDeliveryStartDate, $endDate,
                    //         $normalDeliveryStartDate, $endDate
                    //     ]);
                });

                // ->orWhere(function ($q) use ($fastDeliveryStartDate, $endDate) {
                //     $q->where('p.delivery_type', 'fast')
                //     ->whereRaw("
                //             (
                //                 (p.status_id = 19 AND p.failed_datetime BETWEEN ? AND ?)
                //                 OR
                //                 (p.status_id = 9 AND p.delivered_datetime BETWEEN ? AND ?)
                //             )
                //         ", [
                //             $fastDeliveryStartDate, $endDate,
                //             $fastDeliveryStartDate, $endDate
                //         ]);
                // });
            });
        }

        if($normalPickUpStartDate && $endDate){
            $qO->whereBetween('pickup_datetime',[$normalPickUpStartDate,$endDate]);
        }



        $packages = $qP->get();
        $orders = $qO->get();
        //** Callback func */
        // Log::info(json_encode($driverCommissionInfo));
        $clbMapper = function ($driver) use ($driverCommissions,$orders, $packages) {
            // $commissionInfo = TransactionService::getDriverCommissionInfo($driverCommissionInfo, $driver->id);
            $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driver->id);
            $driver->pickup_rate = $driverCommissionInfo->normal_pickup_commission;
            $driver->delivery_rate = $driverCommissionInfo->normal_delivery_commission;
            $driver->delivery_fast_rate = $driverCommissionInfo->fast_delivery_commission;

            $pickUpInfo = $this->getPickUpDetails($orders, $driver->id);
            $deliverdInfo = $this->getDeliveredDetails($packages, $driver->id);
            // Log::info(json_encode($deliverdInfo));

            $totalPickUp = $pickUpInfo->total_package ?? 0;
            $totalNormalPkg = $deliverdInfo->normal_delivered_count ?? 0;//($deliverdInfo->normal_delivered_count ?? 0) + ($deliverdInfo->normal_failed_with_fee_count ?? 0);
            $totalFastPkg = ($deliverdInfo->fast_delivered_count ?? 0) + ($deliverdInfo->fast_failed_with_fee_count ?? 0);

            $driver->total_pickup = $totalPickUp;
            $driver->total_delivered = ($deliverdInfo->normal_delivered_count ?? 0) + ($deliverdInfo->fast_delivered_count ?? 0);
            $driver->normal_delivered_count = $deliverdInfo->normal_delivered_count;
            $driver->fast_delivered_count = $deliverdInfo->fast_delivered_count;

            $driver->total_failed_with_fee = ($deliverdInfo->normal_failed_with_fee_count ?? 0) + ($deliverdInfo->fast_failed_with_fee_count ?? 0);
            $driver->total_commission_packages = $deliverdInfo->total_commission_pkg ?? 0;
            $driver->normal_failed_with_fee_count = $deliverdInfo->normal_failed_with_fee_count;
            $driver->fast_failed_with_fee_count = $deliverdInfo->fast_failed_with_fee_count;

            $totalPickupRate = TransactionService::calculateCommission($driver->pickup_rate,$driverCommissionInfo->normal_pickup_commission_type,$totalPickUp);
            $totalDeliveryNormal = TransactionService::calculateCommission($driver->delivery_rate,$driverCommissionInfo->normal_delivery_commission_type,$totalNormalPkg);
            $driver->total = Helper::getNumber(
                $totalPickupRate + $totalDeliveryNormal,
                2
            );

            // Bank account info
            $driver->bank_account = null;
            foreach ($driver->bank_accounts as $b) {
                $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name, $b->bank_number, $b->account_name);
                if ($b->is_primary) {
                    break; // Prefer primary bank account
                }
            }

            $driver->status_code = 'Pending';
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
        ->selectRaw('d.id as driver_id,dis.id as payment_id,d.username as driver_name,d.phone,d.code,dis.pickup_rate,dis.delivery_rate,dis.payment_datetime,dis.breakdown_notes,rc.username as receiptionist,payable_amount,package_count,delivered_package_count,pickup_package_count');
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
            // Log::info("{$order->driver} - {$driverId}");
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
        $normalFailedWithFeeCount = 0;
        $fastDeliveredCount = 0;
        $fastFailedWithFeeCount = 0;
        $normalDeliveredCount = 0;
        $totalCommissionPkg = 0;
        foreach($packages as $pkg){
            if($pkg->driver_id == $driverId){
                $totalPkg += 1;
                if($pkg->status_id == 9) {
                    if($pkg->delivery_type == 'normal'){
                        $normalDeliveredCount +=1;
                    }else if($pkg->delivery_type == 'fast'){
                        $fastDeliveredCount +=1;
                    }
                    $totalCommissionPkg +=1;
                }
                if($pkg->status_id == 19) {
                    if($pkg->delivery_type == 'normal'){
                        $normalFailedWithFeeCount +=1;
                    }
                    else if($pkg->delivery_type == 'fast'){
                        $fastFailedWithFeeCount +=1;
                    }
                    $totalCommissionPkg +=1;
                }

            }
        }

        return (object)[
            'total_package' => $totalPkg,
            'normal_delivered_count' => $normalDeliveredCount,
            'normal_failed_with_fee_count' => $normalFailedWithFeeCount,
            'fast_delivered_count' => $fastDeliveredCount,
            'fast_failed_with_fee_count' => $fastFailedWithFeeCount,
            'total_commission_pkg' => $totalCommissionPkg
        ];
    }

    // public static function getDriverCommissionInfo($driverCommissions,$driverId){
    //     $dc = (object)[
    //         'normal_pickup_commission' => 0,
    //         'normal_pickup_commission_start_date' => null,
    //         'normal_delivery_commission' => 0,
    //         'normal_delivery_commission_start_date' => null,
    //         'fast_pickup_commission' => 0,
    //         'fast_pickup_commission_start_date' => null,
    //         'fast_delivery_commission' => 0,
    //         'fast_delivery_commission_start_date' => null,

    //     ];
    //     foreach($driverCommissions as $driverComm){
    //         if($driverComm->driver_id == $driverId){
    //                 if($driverComm->delivery_type == 'fast'){
    //                 $dc->fast_pickup_commission = $driverComm->pickup_commission;
    //                 $dc->fast_pickup_commission_start_date = $driverComm->pickup_commission_start_date;
    //                 $dc->fast_delivery_commission = $driverComm->delivery_commission;
    //                 $dc->fast_delivery_commission_start_date = $driverComm->delivery_commission_start_date;
    //             }
    //             if($driverComm->delivery_type == 'normal'){
    //                 $dc->normal_pickup_commission = $driverComm->pickup_commission;
    //                 $dc->normal_pickup_commission_start_date = $driverComm->pickup_commission_start_date;
    //                 $dc->normal_delivery_commission = $driverComm->delivery_commission;
    //                 $dc->normal_delivery_commission_start_date = $driverComm->delivery_commission_start_date;
    //             }
    //         }
    //     }

    //     return $dc;
    // }

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
        $receive = $trxService->receiveOrDisburesementV1($req,$user,'driver');
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
