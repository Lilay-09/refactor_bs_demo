<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
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
            ->where('d.company_id', $user->company_id) // Uncomment if needed
            ->join('packages as p', 'p.merchant_id', '=', 'd.id')
            ->where('p.is_deleted',0)
            ->whereIn('p.status_id',[9,19])
            ->orderByRaw('COALESCE(p.failed_datetime, p.delivered_datetime) DESC NULLS LAST')
            // ->join('payments as pmt','p.driver_payment_id','pmt.id')
            // ->where('pmt.is_settled',0)
            ->selectRaw('p.taxi_fee,p.extra_charge,p.additional_fee,p.payer,p.delivery_fee,p.cod,p.price,p.delivered_datetime,p.failed_datetime,d.id as driver_id,d.id,d.user_name as merchant_name,d.code,p.status_id,p.updated_at');
            // ->groupBy(['d.id','pmt.payable_amount',DB::raw('DATE(p.delivered_datetime)'),DB::raw('DATE(p.failed_datetime)')]);
        if($userId){
            $qP->where('d.id',operator: $userId);
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->whereRaw('(DATE(delivered_datetime) >= ? AND DATE(delivered_datetime) <= ? OR DATE(failed_datetime) >= ? AND DATE(failed_datetime) <= ?)',[
                $startDate, $endDate, $startDate, $endDate
            ]);

        }
        $merchants = $qP->get();
        $grandTotal = 0;
        $totalPackageCount = 0;
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
        })->map(function ($group, $key) use(&$grandTotal,&$totalPackageCount,$transactionType) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group

            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            $totalCod = $group->where('cod',1)->where('status_id','!=',19)->sum('price');
            if($transactionType == 'disbursement'){
                if($totalCod < 0)  return;
            }
            $totalTaxi = $group->where('status_id','!=',19)->sum('taxi_fee');
            $totalExtraCharge = $group->where('payer','sender')->sum('extra_charge');
            $totalDeliveryFee = $group->where('payer','sender')->sum('delivery_fee') + $totalExtraCharge;
            $representative = $group->first();
            $totalAmount = $totalCod - ($totalDeliveryFee +  + $group->sum('additional_fee'));
            $grandTotal += $totalAmount;
            $totalPackageCount += $packageTotal;
            // $representative->package_count = $packageTotal; // Add the summed total_package
            $bankInfo = $representative->bank_accounts->where('is_primary',1)->first();
            if(!$bankInfo) $bankInfo = $representative->bank_accounts->first();
            unset($representative->groupDate);
            return [
                'finished_date' => $date,
                'driver_id' => $driver_id,
                'merchant_name' => $representative->merchant_name,
                'code' => $representative->code,
                'package_count' => $packageTotal,
                'cod_amount' => number_format($totalCod,2),
                'fee' => number_format($totalDeliveryFee,2),
                'taxi_fee' => $totalTaxi,
                'status_id' => $representative->status_id,
                'amount' => $totalAmount,
                'account_info' => $bankInfo
            ];
        })->values();
        return ApiResponse::Pagination($groupData,$req,null,[
            'total_package' => $totalPackageCount,
            'total_amount' => number_format($grandTotal,2),
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
