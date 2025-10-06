<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MerchantTransactionService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Http\Request;

class MerchantTransactionController extends Controller
{

    public function __construct(private MerchantTransactionService $merchantTransactionService){

    }
    protected $userClass = 'merchant';
    public function getDeliveryPackages(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getDeliveryPackages($req,$this->userClass,$user));
    }

    public function getMerchantDeliveryPackages(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getMerchantDeliveryPackages($req,$user));
    }

    public function updateDeliveryPackage(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updatePackageFromTransaction($req->id,$req->all(),$user));
    }

    public function declineRequestedPayment(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->merchantTransactionService->declineRequetedSettlement($req->paymentId,$req,$user));
    }

    public function receivePackagesPayment(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->receiveOrDisburesement($req,$user,$this->userClass);
        return ApiResponse::flex($receive);
    }

    public function receivePackagesBulkPaymentV1(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        $receive = $trxService->disburesementBulkV1($req,$user,$this->userClass);
        return ApiResponse::flex($receive);
    }

    public function approveAdnSettleBulkRequestedSettlement(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->merchantTransactionService->approveAndSettleBulkRequestedSettlement($req,$user));
    }

    public function approveAndSettleRequestedSettlement(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->merchantTransactionService->approveAndSettleRequestedSettlement($req->paymentId,$req->input('transaction_type'),$user));
    }


    public function getSettledPayments(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->merchantTransactionService->getSettledPaymentTransactions($req,$user));
    }

    public function getSettledPaymentById(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->merchantTransactionService->getSettledPaymentTransactionById($req->tranId,$user));
    }

    public function getPayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->getTransaction($req,'merchant'));
    }

    public function approvePayments(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->approvePayments($req,$user));
    }

    public function deletePayment(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->deletePayment($req,$req->payment_type,'merchant',$user));
    }

    public function getRequestedSettlement(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->merchantTransactionService->getRequestedSettlement($req,$user));
    }

    public function getMerchantBalances(Request $req){
        $user = UserService::getAuthUser();
        $merchantId = $req->merchant_id ?? null;
        $transactionType = $req->transaction_type;
        $userId = $driverId ?? $merchantId;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = User::from('users as d')
            ->where('d.account_type','merchant')
            ->with('bank_accounts:user_id,bank_name,bank_number,account_name,is_primary')
            ->where('d.is_deleted', 0)
            ->where('d.company_id', operator: $user->company_id) // Uncomment if needed
            ->join('packages as p', 'p.merchant_id', '=', 'd.id')
            ->where('p.is_deleted',0)
            ->whereIn('p.status_id',[9,19])
            ->orderByRaw('COALESCE(p.failed_datetime, p.delivered_datetime) DESC NULLS LAST')
            // ->join('payments as pmt','p.driver_payment_id','pmt.id')
            // ->where('pmt.is_settled',0)
            ->selectRaw('
                p.taxi_fee,p.extra_charge,p.additional_fee,p.payer,p.delivery_fee,p.cod,p.price,p.delivered_datetime,p.failed_datetime,d.id as driver_id,
                d.id,d.username as merchant_name,d.code,p.status_id,p.updated_at,p.driver_cod_usd,p.driver_cod_khr
            ');
            // $qP->where(function ($q) {
                $qP->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payment_packages as pp')
                        ->whereColumn('pp.package_id', 'p.id')
                        ->where('pp.payer_type', 'merchant')
                        ->where('pp.is_deleted', false);
                })->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('disbursement_packages as dp')
                        ->whereColumn('dp.package_id', 'p.id')
                        ->where('dp.payee_type', 'merchant')
                        ->where('dp.type','payment')
                        ->where('dp.is_deleted', false);
                });
            // });
            // ->groupBy(['d.id','pmt.payable_amount',DB::raw('DATE(p.delivered_datetime)'),DB::raw('DATE(p.failed_datetime)')]);
        if($userId){
            $qP->where('d.id',operator: $userId);
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            // $qP->whereRaw('
            //     (delivered_datetime >= ? AND delivered_datetime < ? OR failed_datetime >= ? AND failed_datetime < ?)', [
            //     "$startDate 00:00:00", "$endDate 23:59:59.999",
            //     "$startDate 00:00:00", "$endDate 23:59:59.999"
            // ]);
            $qP->where(function ($q) use ($startDate, $endDate) {
                // Check for status_id = 9, delivered_datetime should be within the date range
                $q->where(function ($q) use ($startDate, $endDate) {
                    $q->where('status_id', 9)
                    ->whereRaw('delivered_datetime >= ? AND delivered_datetime <= ?', ["$startDate 00:00:00", "$endDate 23:59:59.999"]);
                })
                // Check for status_id = 19, failed_datetime should be within the date range
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('status_id', 19)
                    ->whereRaw('failed_datetime >= ? AND failed_datetime <= ?', ["$startDate 00:00:00", "$endDate 23:59:59.999"]);
                });
            });
        }
        $merchants = $qP->get();
        $grandTotalUsd = 0;
        $totalPackageCount = 0;
        $totalDriverCodUsd = 0;
        $totalDriverCodKhr = 0;
        $totalFee = 0;
        $groupData = collect($merchants)->map(function ($item) {
            // Set groupDate based on status
            if ($item->status_id == 9) {
                $date = $item->delivered_datetime ? $item->delivered_datetime : $item->updated_at;
                $item->groupDate = date('d-M-Y', strtotime($date));
            } elseif ($item->status_id == 19) {
                $date = $item->failed_datetime ? $item->failed_datetime : $item->updated_at;
                $item->groupDate = date('d-M-Y', strtotime($date));
            }
            return $item;
        })->filter(function ($item) {
            return $item->groupDate !== null; // Exclude items with no groupDate
        })->groupBy(function ($item) {
            // Group by both groupDate and driver_id
            return $item->groupDate . '|' . $item->driver_id;
        })->map(function ($group, $key) use(&$grandTotalUsd,&$grandTotalKhr,&$totalPackageCount,&$totalDriverCodUsd,&$totalDriverCodKhr,&$totalFee,$transactionType) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group
            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            // $rowTotalUsd = $group->where('cod',1)->where('status_id','=',9)->sum('price');
            // $rowTotalKhr = $group->where('cod',1)->where('status_id',9)->sum('price_khr');
            // if($transactionType == 'disbursement'){
            //     if($rowTotalUsd < 0)  return;
            // }
            $taxiFee = $group->where('status_id','=',9)->sum('taxi_fee');
            $otherFee = $group->where('payer','sender')->sum('other_fee');
            $deliveryFee = $group->where('payer','sender')->sum('delivery_fee');
            $representative = $group->first();
            $driverCodUsd = $group->whereIn('status_id',[9,19])->sum('driver_cod_usd');
            $merchantCodUsd = $driverCodUsd;
            $driverCodKhr = $group->whereIn('status_id',[9,19])->sum('driver_cod_khr');
            $merchantCodKhr = $driverCodKhr;
            // $totalAmount = $rowTotalUsd - $deliveryFee - $taxiFee;
            $deductFee = $deliveryFee + $otherFee + $taxiFee;
            $totalFee += $group->sum('delivery_fee') + $otherFee;
            Helper::deductAmountBase($merchantCodUsd,$merchantCodKhr,$deductFee);
            // $representative->package_count = $packageTotal; // Add the summed total_package
            $bankInfo = $representative->bank_accounts->where('is_primary',1)->first();
            if(!$bankInfo) $bankInfo = $representative->bank_accounts->first();
            unset($representative->groupDate);
            // if ($transactionType == TransactionType::TRANSFER_IN->value && $totalAmount >= 0) {
            //     return null; // Exclude this group
            // }else if ($transactionType == TransactionType::TRNASFER_OUT->value && $totalAmount < 0) {
            //     return null; // Exclude this group
            // }
            $grandTotalUsd += $group->where('cod',1)->where('status_id','=',9)->sum('price');
            $grandTotalKhr += $group->where('cod',1)->where('status_id','=',9)->sum('price_khr');
            $totalDriverCodUsd += $driverCodUsd;
            $totalDriverCodKhr += $driverCodKhr;
            $totalPackageCount += $packageTotal;
            return [
                'finished_date' => $date,
                'driver_id' => $driver_id,
                'merchant_name' => $representative->merchant_name,
                'code' => $representative->code,
                'package_count' => $packageTotal,
                'driver_cod_usd' => Helper::getNumber($driverCodUsd,2),
                'driver_cod_khr' => Helper::getNumber($driverCodKhr,2),
                'merchant_cod_usd' => (float)Helper::getNumber($merchantCodUsd,2),
                'merchant_cod_khr' => (float)Helper::getNumber($merchantCodKhr,2),
                'fee' => Helper::getNumber($deliveryFee + $otherFee,2),
                'taxi_fee' => $taxiFee,
                'status_id' => $representative->status_id,
                // 'amount' => Helper::getNumber($totalAmount,2),
                'account_info' => $bankInfo
            ];
        })->filter()->values();
        return ApiResponse::Pagination($groupData,$req,null,[
            'total_package' => $totalPackageCount,
            'total_cod_usd' => (float)Helper::getNumber($totalDriverCodUsd,2),
            'total_cod_khr' => (float)Helper::getNumber($totalDriverCodKhr,2),
            'total_amount_usd' => (float)Helper::getNumber($grandTotalUsd,2),
            'total_amount_khr' => (float)Helper::getNumber($grandTotalKhr,2),
            'total_fee' => (float)Helper::getNumber($totalFee,2),
        ]);
    }

    public function deleteSettlePayment(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        // \Log::error(json_encode($req->all()));
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->deleteSettlePayment($id,$req->payment_type,'merchant',$user));
    }

}
