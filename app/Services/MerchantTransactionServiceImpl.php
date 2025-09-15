<?php

namespace App\Services;

use App\DTO\MerchantRequestedSettlementDTO;
use App\DTO\MerchantSettledTransactionByIdDTO;
use App\DTO\MerchantSettledTransactionDTO;
use App\Enums\PaymentStatus;
use App\Enums\TrackingStatus;
use App\Enums\TransactionType;
use App\Models\Disbursement;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\UserBank;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;

class MerchantTransactionServiceImpl implements MerchantTransactionService
{
    // Your service methods go here
    // public function getRequestedSettlement(Request $req,object $authUSer):object{
    //     $qPmt = Disbursement::query()
    //     ->whereIn('payment_status_id',[
    //         PaymentStatus::REQUESTED->value,
    //         PaymentStatus::APPROVE_AND_SETTLE->value
    //     ])
    //     ->with([
    //         'requestedUser:id,username,phone,code',
    //         'merchant:id,username,phone,code',
    //         'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name,currency',
    //         'pmtPackages:id,disbursement_id,package_id',
    //         'pmtPackages.package:id,driver_cod_usd,driver_cod_khr,price,price_khr'
    //     ])
    //     ->where('is_deleted',false)
    //     ->where('payee_type','merchant');
    //     $select = [
    //         'id','requested_date','payee_id','payee_type','delivery_fee',
    //         'taxi_fee','amount_due_usd','amount_due_khr','requested_uid',
    //         'payment_status_id'
    //     ];

    //     $callback = function($q) {
    //         $q->merchant_name = $q->merchant->username;
    //         $q->requested_username = $q->requestedUser?->username;
    //         $q->merchant_phone = $q->merchant->phone;
    //         $q->merchant_code = $q->merchant->code;
    //         $rqD = $q->requested_date;
    //         $q->transactoin_type = TransactionType::TRNASFER_OUT->value;
    //         $q->requested_date = Helper::formatCustomDateTime($rqD,'d-M-Y');
    //         $q->requested_time = Helper::formatCustomDateTime($rqD,'h:i A');
    //         $q->fees = $q->delivery_fee;
    //         $q->driver_cod_usd = 0;
    //         $q->driver_cod_khr = 0;
    //         $q->cod_usd = 0;
    //         $q->cod_khr = 0;
    //         foreach($q->pmtPackages as $pkg){
    //             $q->driver_cod_usd += $pkg->package->driver_cod_usd;
    //             $q->driver_cod_khr += $pkg->package->driver_cod_khr;
    //             $q->cod_usd += $pkg->package->price;
    //             $q->cod_khr += $pkg->package->price_khr;
    //         }
    //         $q->bank_accounts = $q->merchant->bank_accounts ?? [];
    //         $q->package_count = count($q->pmtPackages);

    //         $q->cod_to_be_paid_usd = $q->amount_due_usd;
    //         $q->cod_to_be_paid_khr = $q->amount_due_khr;

    //         // $disbursement->package_count = $packageIds->count();
    //         unset($q->pmtPackages,$q->merchant);
    //         $q->status = PaymentStatus::tryFrom($q->payment_status_id)->label();
    //         // return $q;
    //         return MerchantRequestedSettlementDTO::fromModel($q);
    //     };

    //     return DataResponse::PaginationV1($qPmt,$req,'',[],500,$callback,$select);
    // }

    public function getRequestedSettlement(Request $req, object $authUser): object
    {
        $perPage = $req->input('per_page', 10);
        $page = $req->input('page', 1);

        // Common callback for transforming each record
        $callback = function($q) {
            $q->merchant_name = $q->merchant->username;
            $q->requested_username = $q->requestedUser?->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->merchant_code = $q->merchant->code;
            $rqD = $q->requested_date;
            // $q->transaction_type = TransactionType::TRNASFER_OUT->value;
            if ($q instanceof Disbursement) {
                $q->transaction_type = TransactionType::TRNASFER_OUT->value; // Out
            } elseif ($q instanceof Payment) {
                $q->transaction_type = TransactionType::TRANSFER_IN->value; // In
            }
            $q->requested_date = Helper::formatCustomDateTime($rqD, 'd-M-Y');
            $q->requested_time = Helper::formatCustomDateTime($rqD, 'h:i A');

            $q->fees = $q->delivery_fee;
            $q->driver_cod_usd = 0;
            $q->driver_cod_khr = 0;
            $q->cod_usd = 0;
            $q->cod_khr = 0;

            foreach ($q->pmtPackages as $pkg) {
                $q->driver_cod_usd += $pkg->package->driver_cod_usd;
                $q->driver_cod_khr += $pkg->package->driver_cod_khr;
                $q->cod_usd += $pkg->package->price;
                $q->cod_khr += $pkg->package->price_khr;
            }

            $q->bank_accounts = $q->merchant->bank_accounts ?? [];
            $q->package_count = count($q->pmtPackages);
            $q->cod_to_be_paid_usd = $q->amount_due_usd;
            $q->cod_to_be_paid_khr = $q->amount_due_khr;

            unset($q->pmtPackages, $q->merchant);
            $q->status = PaymentStatus::tryFrom($q->payment_status_id)->label();

            return MerchantRequestedSettlementDTO::fromModel($q);
        };

        // Build Eloquent queries
        $disQuery = Disbursement::query()
            ->whereIn('payment_status_id', [
                PaymentStatus::REQUESTED->value,
                PaymentStatus::APPROVE_AND_SETTLE->value
            ])
            ->where('is_deleted', false)
            ->where('payee_type', 'merchant')
            ->with([
                'requestedUser:id,username,phone,code',
                'merchant:id,username,phone,code',
                'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name,currency',
                'pmtPackages:id,disbursement_id,package_id',
                'pmtPackages.package:id,driver_cod_usd,driver_cod_khr,price,price_khr'
            ]);

        $payQuery = Payment::query()
            ->whereIn('payment_status_id', [
                PaymentStatus::REQUESTED->value,
                PaymentStatus::APPROVE_AND_SETTLE->value
            ])
            ->where('is_deleted', false)
            ->where('payer_type', 'merchant')
            ->with([
                'requestedUser:id,username,phone,code',
                'merchant:id,username,phone,code',
                'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name,currency',
                'pmtPackages:id,payment_id,package_id',
                'pmtPackages.package:id,driver_cod_usd,driver_cod_khr,price,price_khr'
            ]);

        // Fetch slices only for the current page
        $disData = $disQuery->orderBy('requested_date', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $payData = $payQuery->orderBy('requested_date', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Merge, sort, and slice to fit page
        $merged = $disData->merge($payData)
            ->sortByDesc('requested_date')
            ->values();

        // Calculate total count
        $total = $disQuery->count() + $payQuery->count();
        $totalPages = (int) ceil($total / $perPage);

        // Apply transformation callback
        $transformed = $merged->map($callback);

        // Build final pagination object
        $paginated = (object)[
            'status_code' => 200,
            'status' => 'OK',
            'error' => false,
            'message' => '',
            'data' => $transformed,
            'per_page' => $perPage,
            'total' => $total,
            'total_page' => $totalPages,
            'page_no' => $page,
            'errors' => [],
        ];

        return $paginated;
    }



    public function approveAndSettleRequestedSettlement(int $paymentId,string $transactionType,object $authUser):object{
        if(empty($transactionType)){
            return DataResponse::BadRequest('Transaction type must be provided');
        }
        $disbursement = Disbursement::where('is_deleted', false)
            ->with(['merchant:id,username'])
            ->find($paymentId);

        if(!$disbursement){
            return DataResponse::NotFound();
        }

        if ($disbursement->payment_status_id === PaymentStatus::APPROVE_AND_SETTLE->value) {
            return DataResponse::BadRequest(__('messages.info', [
                'info'   => 'This payment has already been approved and settled',
                'khInfo' => 'ការទូទាត់នេះត្រូវបានអនុម័ត និងទូរទាត់រួចរាល់ហើយ'
            ]));
        }

        if($disbursement->payment_status_id !== PaymentStatus::REQUESTED->value){
            return DataResponse::BadRequest(__('messages.info',[
                'info' => 'Please ensure payment is requested, before settle',
                'khInfo' => 'សូមប្រាកដថាបានស្នើការទូទាត់ជាមុនសិន មុនពេលធ្វើការបង់ប្រាក់'
            ]));
        }
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
        $accountList = UserBank::where('is_deleted',false)
        ->select(['id','bank_name','account_name','bank_number as account_number','currency','user_id'])
        ->where('user_id',$disbursement->payee_id)->get();
        $toBeSettleList = [];
        foreach ($dueAmounts as $dueAmt) {
            $dueAccount = $this->dueBankAccounts($accountList, $disbursement->payee_id, $dueAmt['currency']);
            if (empty($dueAccount)) {
                return DataResponse::NotFound(__('messages.info', [
                    'info'   => "Merchant {$disbursement->merchant->username} has no bank account for {KHR or USD}",
                    'khInfo' => "អ្នកលក់ {$disbursement->merchant->username} មិនមានគណនីសម្រាប់រូបិយប័ណ្ណ {KHR ឬ USD}"
                ]));
            }

            $toBeSettleList[] = [
                'currency' => $dueAmt['currency'],
                'amount' => $dueAmt['amount'],
                'payment_date' => now(),
                'payment_id' => $paymentId,
                'tran_via' => 'internal',
                'transaction_type' => TransactionType::TRNASFER_OUT->value,
                'from_account' => 'Ng Company',
                'to_account' => $dueAccount['concat'],
                'approved_uid' => $authUser->id,
                'create_uid' => $authUser->id,
                'update_uid' => $authUser->id,
                'branch_id' => $authUser->branch_id,
                'company_id' => $authUser->company_id
            ];
        }

        try{
            DB::beginTransaction();
            PaymentTransaction::insert($toBeSettleList);
            $disbursement->update([
                'payment_status_id' => PaymentStatus::APPROVE_AND_SETTLE->value,
                'settled_datetime' => now(),
                'is_settled' => true,
                'settled_uid' => $authUser->id
            ]);
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            Log::info($e->getMessage());
            DB::rollBack();
            return DataResponse::Error('Failed to approve');
        }
    }

    private function dueBankAccounts($accountList, $userId, $currency): array
    {
        // Filter accounts by user
        $userAccounts = $accountList->where('user_id', $userId);
        // Log::info($userAccounts);

        if ($userAccounts->isEmpty()) {
            return [];
        }

        // Try to get account with requested currency
        $matched = $userAccounts->firstWhere('currency', $currency);

        if ($matched) {
            return [
                'account_number' => $matched->account_number,
                'account_name'   => $matched->account_name,
                'concat' => "{$matched->currency}|{$matched->account_name}|{$matched->account_number}"
            ];
        }

        // matched: return first available account
        $fallback = $userAccounts->first();
        return [
            'account_number' => $fallback->account_number,
            'account_name'   => $fallback->account_name,
            'concat' => "{$fallback->currency}|{$fallback->account_name}|{$fallback->account_number}"
        ];
    }

    public function declineRequetedSettlement(int $paymentId,Request $req,object $authUser):object{

        $transactionType = $req->transaction_type;

        if($transactionType === TransactionType::TRNASFER_OUT->value){
            $disbursement = Disbursement::where('is_deleted',false)
            ->where('payment_status_id',PaymentStatus::REQUESTED->value)
            ->find($paymentId);
        }
    }
    public function approveAndSettleBulkRequestedSettlement(Request $req,object $authUser):object{
        $validator = validator($req->all(),[
            'payment_ids' => 'required|array',
            'payment_ids.*.id' => 'int',
            'payment_ids.*.transaction_type' => 'string'
        ]);
        // Log::info(json_encode($req->all()));

        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputsPayment = $inputs['payment_ids'];
        $paymentIds = array_column($inputsPayment,'id');
        // Log::info($paymentIds);
        $disbursements = Disbursement::where('is_deleted', false)
            ->whereIn('id', $paymentIds)
            ->with(['merchant:id,username'])
            ->get();

        $merchantIds = $disbursements->pluck('merchant.id')->filter()->unique()->values()->toArray();
        $disbursementById = $disbursements->keyBy('id');
        $payments = Payment::where('is_deleted', false)
            ->whereIn('id', $paymentIds)
            ->with(['merchant:id,username'])
            ->get();

        $merchantIds = array_unique(array_merge(
            $merchantIds,
            $payments->pluck('merchant.id')->filter()->values()->toArray()
        ));
        $paymentById = $payments->keyBy('id');
        $toBeSettleList = [];
        $toUpdatePayout = [];
        $toUpdatePayin = [];
        $accountList = UserBank::where('is_deleted',false)
        ->select(['id','bank_name','account_name','bank_number as account_number','currency','user_id'])
        ->whereIn('user_id',$merchantIds)->get();
        // return DataResponse::JsonResult($accountList);
        foreach($inputsPayment as $idx => $p){
            $pId = $p['id'];
            $tranType = $p['transaction_type'];
            if($tranType === TransactionType::TRNASFER_OUT->value){
                if(empty($disbursementById[$pId])){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => "Payment not found on row => {($idx + 1)}"
                    ]));
                }
                $disbursement = $disbursementById[$pId];
                if ($disbursement->payment_status_id === PaymentStatus::APPROVE_AND_SETTLE->value) {
                return DataResponse::BadRequest(__('messages.info', [
                        'info'   => 'This payment has already been approved and settled',
                        'khInfo' => 'ការទូទាត់នេះត្រូវបានអនុម័ត និងទូរទាត់រួចរាល់ហើយ'
                    ]));
                }

                if($disbursement->payment_status_id !== PaymentStatus::REQUESTED->value){
                    return DataResponse::BadRequest(__('messages.info',[
                        'info' => 'Please ensure payment is requested, before settle',
                        'khInfo' => 'សូមប្រាកដថាបានស្នើការទូទាត់ជាមុនសិន មុនពេលធ្វើការបង់ប្រាក់'
                    ]));
                }
                $toUpdatePayout[] = $pId;
                $receivedAmtUsd = $disbursement->amount_due_usd;
                $receivedAmtKhr = $disbursement->amount_due_khr;
            }else if($tranType === TransactionType::TRANSFER_IN->value){
                if(empty($paymentById[$pId])){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => "Payment not found on row => {($idx + 1)}"
                    ]));
                }
                $payment = $paymentById[$pId];
                if ($payment->payment_status_id === PaymentStatus::APPROVE_AND_SETTLE->value) {
                return DataResponse::BadRequest(__('messages.info', [
                        'info'   => 'This payment has already been approved and settled',
                        'khInfo' => 'ការទូទាត់នេះត្រូវបានអនុម័ត និងទូរទាត់រួចរាល់ហើយ'
                    ]));
                }

                if($payment->payment_status_id !== PaymentStatus::REQUESTED->value){
                    return DataResponse::BadRequest(__('messages.info',[
                        'info' => 'Please ensure payment is requested, before settle',
                        'khInfo' => 'សូមប្រាកដថាបានស្នើការទូទាត់ជាមុនសិន មុនពេលធ្វើការបង់ប្រាក់'
                    ]));
                }
                $toUpdatePayin[] = $pId;
                $receivedAmtUsd = $payment->amount_due_usd;
                $receivedAmtKhr = $payment->amount_due_khr;
            }


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
            $targetUid = $tranType === TransactionType::TRANSFER_IN->value ?
                $payment->payer_id : $disbursement->payee_id;

            $targetUsername = $tranType === TransactionType::TRANSFER_IN->value ?
                $payment->merchant->username : $disbursement->merchant->username;

            foreach ($dueAmounts as $dueAmt) {
                $dueAccount = $this->dueBankAccounts($accountList, $targetUid, $dueAmt['currency']);

                if (empty($dueAccount)) {
                    return DataResponse::NotFound(__('messages.info', [
                        'info'   => "Merchant {$targetUsername} has no bank account for {$dueAmt['currency']}",
                        'khInfo' => "អ្នកលក់ {$targetUsername} មិនមានគណនីសម្រាប់រូបិយប័ណ្ណ {$dueAmt['currency']}"
                    ]));
                }
                $toBeSettleList[] = [
                    'currency' => $dueAmt['currency'],
                    'amount' => $dueAmt['amount'],
                    'payment_date' => now(),
                    'tran_via' => 'internal',
                    'payment_id' => $pId,
                    'transaction_type' => TransactionType::TRNASFER_OUT->value,
                    'from_account' => 'Ng Company',
                    'to_account' => $dueAccount['concat'],
                    'approved_uid' => $authUser->id,
                    'create_uid' => $authUser->id,
                    'update_uid' => $authUser->id,
                    'branch_id' => $authUser->branch_id,
                    'company_id' => $authUser->company_id
                ];
            }
        }

        try{
            DB::beginTransaction();
            PaymentTransaction::insert($toBeSettleList);
            if(!empty($toUpdatePayout)){
                Disbursement::whereIn('id',$toUpdatePayout)->update([
                    'payment_status_id' => PaymentStatus::APPROVE_AND_SETTLE->value,
                    'settled_datetime' => now(),
                    'is_settled' => true,
                    'settled_uid' => $authUser->id
                ]);
            }
            if(!empty($toUpdatePayin)){
                Payment::whereIn('id',$toUpdatePayin)->update([
                    'payment_status_id' => PaymentStatus::APPROVE_AND_SETTLE->value,
                    'settled_datetime' => now(),
                    'is_settled' => true,
                    'settled_uid' => $authUser->id
                ]);
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            Log::error($e->getMessage());
            DB::rollBack();
            return DataResponse::Error('Failed to approve');
        }
    }

    public function getSettledPaymentTransactions(Request $req,$authUser):object{
        $qTrx = PaymentTransaction::query()
        ->with([
            'disbursement:id,payee_id',
            'disbursement.merchant:id,username,phone,code',
            'performer:id,username',
            'payment:id,payer_id'
        ])
        ->where('is_deleted',false);
        $select = ['id','tran_via','approved_uid','payment_id','payment_date','to_account','from_account','payment_ref','amount','currency'];
        $callback = function($q): MerchantSettledTransactionDTO{
            $q->merchant_name = $q->disbursement->merchant->username ?? '';
            $q->merchant_code = $q->disbursement->merchant->code ?? '';
            $pmtDate = $q->payment_date;
            $q->payment_date = Helper::formatCustomDateTime($pmtDate,'d-M-Y');
            $q->payment_time = Helper::formatCustomDateTime($pmtDate,'h:i A');
            // return $q;
            $q->performed_by = $q->performer->username;
            return MerchantSettledTransactionDTO::fromModel($q);
        };
        return DataResponse::PaginationV1($qTrx,$req,'',[],500,$callback,$select);
    }

    public function getSettledPaymentTransactionById(int $tranId,object $authUser):object{
        $qTrx = PaymentTransaction::where('is_deleted',false)
        ->with([
            'disbursement:id,payee_id',
            'disbursement.merchant:id,username,phone,code',
            'performer:id,username'
        ])
        ->select(['id','tran_via','approved_uid','payment_id','payment_date','to_account','from_account','payment_ref','amount','currency'])
        ->find($tranId);
        if(!$qTrx){
            return DataResponse::NotFound();
        }
        $qTrx->load([
            'disbursement:id,payee_id,amount_due_khr,amount_due_usd,package_count,received_amount_usd,received_amount_khr',
            'disbursement.pmtPackages:id,disbursement_id,package_id',
            'disbursement.pmtPackages.package:id,qr_code,status_id,zone_name,receiver_address,zone_code'
        ]);

        $qTrx->merchant_name = $qTrx->disbursement->merchant->username;
        $qTrx->merchant_code = $qTrx->disbursement->merchant->code;
        $pmtDate = $qTrx->payment_date;
        $qTrx->payment_date = Helper::formatCustomDateTime($pmtDate,'d-M-Y');
        $qTrx->payment_time = Helper::formatCustomDateTime($pmtDate,'h:i A');
        // return $qTrx;
        $items = [];
        foreach ($qTrx->disbursement->pmtPackages as $dp) {
            $d = [];
            foreach ($dp->package->getAttributes() as $key => $p) {
                if($key === 'status_id'){
                    $d['status'] = TrackingStatus::tryFrom($p)->label();
                }
                $d[$key] = $p;
            }
            $items[] = $d;
        }
        $qTrx->disbursement->items = $items;
        $qTrx->performed_by = $qTrx->performer->username;
        unset($qTrx->disbursement->merchant,$qTrx->disbursement->pmtPackages);

        return DataResponse::JsonResult(MerchantSettledTransactionByIdDTO::fromModel($qTrx));
    }
}
