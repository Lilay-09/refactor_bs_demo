<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TransactionService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class MerchantTransactionController extends Controller
{
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
        $receive = $trxService->receiveOrDisburesement($req,$user,$this->userClass);
        return ApiResponse::flex($receive);
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
            ->selectRaw('p.taxi_fee,p.extra_charge,p.additional_fee,p.payer,p.delivery_fee,p.cod,p.price,p.delivered_datetime,p.failed_datetime,d.id as driver_id,d.id,d.user_name as merchant_name,d.code,p.status_id,p.updated_at');
            $qP->where(function ($q) {
                $q->whereNull('p.merchant_payment_id')
                ->whereNull('p.merchant_disbursement_id');
            });
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
        $grandTotal = 0;
        $totalPackageCount = 0;
        $totalCod = 0;
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
        })->map(function ($group, $key) use(&$grandTotal,&$totalPackageCount,&$totalCod,$transactionType) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group
            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            $rowCod = $group->where('cod',1)->where('status_id','=',9)->sum('price');
            // if($transactionType == 'disbursement'){
            //     if($rowCod < 0)  return;
            // }
            $totalTaxi = $group->where('status_id','=',9)->sum('taxi_fee');
            $totalExtraCharge = $group->where('payer','sender')->sum('extra_charge');
            $totalDeliveryFee = $group->where('payer','sender')->sum('delivery_fee') + $totalExtraCharge;
            $representative = $group->first();
            $totalAmount = $rowCod - $totalDeliveryFee - $totalTaxi;

            // $representative->package_count = $packageTotal; // Add the summed total_package
            $bankInfo = $representative->bank_accounts->where('is_primary',1)->first();
            if(!$bankInfo) $bankInfo = $representative->bank_accounts->first();
            unset($representative->groupDate);
            if ($transactionType == 'disbursement' && $totalAmount >= 0) {
                return null; // Exclude this group
            }else if ($transactionType == 'receive' && $totalAmount < 0) {
                return null; // Exclude this group
            }
            $grandTotal += $totalAmount;
            $totalCod += $rowCod;
            $totalPackageCount += $packageTotal;
            return [
                'finished_date' => $date,
                'driver_id' => $driver_id,
                'merchant_name' => $representative->merchant_name,
                'code' => $representative->code,
                'package_count' => $packageTotal,
                'cod_amount' => Helper::getNumber($rowCod,2),
                'fee' => Helper::getNumber($totalDeliveryFee,2),
                'taxi_fee' => $totalTaxi,
                'status_id' => $representative->status_id,
                'amount' => Helper::getNumber($totalAmount,2),
                'account_info' => $bankInfo
            ];
        })->filter()->values();
        return ApiResponse::Pagination($groupData,$req,null,[
            'total_package' => $totalPackageCount,
            'total_cod' => (float)Helper::getNumber($totalCod,2),
            'total_amount' => (float)Helper::getNumber($grandTotal,2),
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
