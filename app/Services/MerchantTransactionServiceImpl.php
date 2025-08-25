<?php

namespace App\Services;

use App\DTO\MerchantRequestedSettlementDTO;
use App\Enums\PaymentStatus;
use App\Models\Disbursement;
use App\Models\Package;
use App\Models\UserBank;
use DataResponse;
use Helper;
use Illuminate\Http\Request;

class MerchantTransactionServiceImpl implements MerchantTransactionService
{
    // Your service methods go here
    public function getRequestedSettlement(Request $req,object $authUSer):object{
        $qPmt = Disbursement::query()
        ->where('payment_status_id',PaymentStatus::REQUESTED->value)
        ->with([
            'requestedUser:id,username,phone,code',
            'merchant:id,username,phone,code',
            'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name',
            'pmtPackages:id,disbursement_id,package_id',
            'pmtPackages.package:id,driver_cod_usd,driver_cod_khr,price,price_khr'
        ])
        ->where('is_deleted',false)
        ->where('payee_type','merchant');
        $select = [
            'id','requested_date','payee_id','payee_type','delivery_fee',
            'taxi_fee','amount_due_usd','amount_due_khr','requested_uid',
            'payment_status_id'
        ];

        $callback = function($q) {
            $q->merchant_name = $q->merchant->username;
            $q->requested_username = $q->requestedUser?->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->merchant_code = $q->merchant->code;
            $rqD = $q->requested_date;
            $q->requested_date = Helper::formatCustomDateTime($rqD,'d-M-Y');
            $q->requested_time = Helper::formatCustomDateTime($rqD,'h:i A');
            $q->fees = $q->delivery_fee;
            $q->driver_cod_usd = 0;
            $q->driver_cod_khr = 0;
            $q->cod_usd = 0;
            $q->cod_khr = 0;
            foreach($q->pmtPackages as $pkg){
                $q->driver_cod_usd += $pkg->package->driver_cod_usd;
                $q->driver_cod_khr += $pkg->package->driver_cod_khr;
                $q->cod_usd += $pkg->package->price;
                $q->cod_khr += $pkg->package->price_khr;
            }
            $q->bank_accounts = $q->merchant->bank_accounts ?? [];
            $q->package_count = count($q->pmtPackages);

            $q->cod_to_be_paid_usd = $q->amount_due_usd;
            $q->cod_to_be_paid_khr = $q->amount_due_khr;

            // $disbursement->package_count = $packageIds->count();
            unset($q->pmtPackages,$q->merchant);
            $q->status = PaymentStatus::tryFrom($q->payment_status_id)->label();
            // return $q;
            return MerchantRequestedSettlementDTO::fromModel($q);

        };

        return DataResponse::PaginationV1($qPmt,$req,'',[],500,$callback,$select);
    }

    public function approveAndSettleRequestedSettlement(Request $req,object $authUser):object{
        $validator = validator($req->all(),[
            'payment_ids' => 'required|array',
        ]);

        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $paymentIds = $inputs['payment_ids'];
        $disbursements = Disbursement::where('is_deleted', false)
            ->whereIn('id', $paymentIds)
            ->with(['merchant:id,username'])
            ->get();

        $merchantIds = $disbursements->pluck('merchant.id')->filter()->unique()->values();

        $disbursementById = $disbursements->keyBy('id');

        $accountList = UserBank::where('is_deleted',false);
        foreach($paymentIds as $idx => $pId){
            if(empty($disbursementById[$pId])){
                return DataResponse::ValidateFail(__('messages.info',[
                    'info' => "Payment not found on row => {($idx + 1)}"
                ]));
            }
            $disbursement = $disbursementById[$pId];
            $receivedAmtUsd = $disbursement->amount_due_usd;
            $receivedAmtKhr = $disbursement->amount_due_khr;
            $dueAmounts = [];
            if($receivedAmtUsd > 0 ){
                $dueAmounts[] = [
                    'currency' => 'USD',
                    'amount' => $receivedAmtUsd
                ];
            }
            if($receivedAmtKhr > 0 ){
                $dueAmounts[] = [
                    'currency' => 'KHR',
                    'amount' => $receivedAmtKhr
                ];
            }

            foreach ($dueAmounts as $dueAmt) {
                $dueAccount = $this->dueBankAccounts($accountList, $disbursement->payee_id, $dueAmt['currency']);

                if (is_null($dueAccount)) {
                    return DataResponse::NotFound(__('messages.info', [
                        'info'   => "Merchant {$disbursement->merchant->username} has no bank account for {$dueAmt['currency']}",
                        'khInfo' => "អ្នកជួញដូរ {$disbursement->merchant->username} មិនមានគណនីសម្រាប់រូបិយប័ណ្ណ {$dueAmt['currency']}"
                    ]));
                }
            }


        }
    }

    private function dueBankAccounts($accountList, $userId, $currency): array
    {
        // Filter accounts by user
        $userAccounts = collect($accountList)->where('user_id', $userId);

        if ($userAccounts->isEmpty()) {
            return [];
        }

        // Try to get account with requested currency
        $matched = $userAccounts->firstWhere('currency', $currency);

        if ($matched) {
            return [
                'account_number' => $matched->account_number,
                'account_name'   => $matched->account_name,
            ];
        }

        // Fallback: return first available account
        $fallback = $userAccounts->first();
        return [
            'account_number' => $fallback->account_number,
            'account_name'   => $fallback->account_name,
        ];
    }



    public function declineRequetedSettlement(Request $req,object $authUser):object{

    }
    public function approveAndSettleBulkRequestedSettlement(Request $req,object $authUser):object{
        $validator = validator($req->all(),[
            'payment_ids' => 'required|array',
        ]);

        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $paymentIds = $inputs['payment_ids'];
        $disbursements = Disbursement::where('is_deleted', false)
            ->whereIn('id', $paymentIds)
            ->with(['merchant:id,username'])
            ->get();

        $merchantIds = $disbursements->pluck('merchant.id')->filter()->unique()->values();

        $disbursementById = $disbursements->keyBy('id');

        $accountList = UserBank::where('is_deleted',false);
        foreach($paymentIds as $idx => $pId){
            if(empty($disbursementById[$pId])){
                return DataResponse::ValidateFail(__('messages.info',[
                    'info' => "Payment not found on row => {($idx + 1)}"
                ]));
            }
            $disbursement = $disbursementById[$pId];
            $receivedAmtUsd = $disbursement->amount_due_usd;
            $receivedAmtKhr = $disbursement->amount_due_khr;
            $dueAmounts = [];
            if($receivedAmtUsd > 0 ){
                $dueAmounts[] = [
                    'currency' => 'USD',
                    'amount' => $receivedAmtUsd
                ];
            }
            if($receivedAmtKhr > 0 ){
                $dueAmounts[] = [
                    'currency' => 'KHR',
                    'amount' => $receivedAmtKhr
                ];
            }

            foreach ($dueAmounts as $dueAmt) {
                $dueAccount = $this->dueBankAccounts($accountList, $disbursement->payee_id, $dueAmt['currency']);

                if (is_null($dueAccount)) {
                    return DataResponse::NotFound(__('messages.info', [
                        'info'   => "Merchant {$disbursement->merchant->username} has no bank account for {$dueAmt['currency']}",
                        'khInfo' => "អ្នកជួញដូរ {$disbursement->merchant->username} មិនមានគណនីសម្រាប់រូបិយប័ណ្ណ {$dueAmt['currency']}"
                    ]));
                }
            }

        }
    }
}
