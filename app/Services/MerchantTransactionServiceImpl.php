<?php

namespace App\Services;

use App\DTO\MerchantRequestedSettlementDTO;
use App\DTO\MerchantSettledTransactionByIdDTO;
use App\DTO\MerchantSettledTransactionDTO;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaywayProvider;
use App\Enums\PaywayStatus;
use App\Enums\PaywayType;
use App\Enums\TrackingStatus;
use App\Enums\TransactionType;
use App\Exceptions\ForbiddenExcept;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DisbursementPackage;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentPackage;
use App\Models\PaymentTransaction;
use App\Models\UserBank;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MerchantTransactionServiceImpl implements MerchantTransactionService
{
    public function __construct(
        protected PaywayService $paywayService
    ){}

    public function getMerchantDeliveryPackages(array $data, object $authUser):object
    {

        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $paymentType = $data['payment_type'] ?? null;
        $search = $data['search'] ?? null;

        // $defaultExchangeRate = GeneralSettingService::getLatestXRate()->buy_rate;
        $effectiveExchangeRate = "4000";//"COALESCE(NULLIF(packages.exchange_rate, 0), {$defaultExchangeRate})";

        $fees = "
            SUM(
                CASE WHEN packages.payer = 'sender' THEN packages.delivery_fee + packages.extra_charge ELSE 0 END
            )
        ";


        $displaySumUsd = "
            SUM(
                CASE
                    WHEN packages.payer = 'receiver'
                        AND packages.status_id = 9
                        AND (packages.driver_cod_usd > packages.price)
                        THEN (packages.driver_cod_usd - (packages.delivery_fee + packages.extra_charge))

                    WHEN packages.payer != 'receiver'
                        OR packages.status_id != 19
                        THEN packages.driver_cod_usd
                    ELSE 0
                END
            )
        ";

        $displaySumKhr = "
            SUM(
                CASE
                    WHEN packages.payer = 'receiver'
                        AND packages.status_id = 9
                        AND packages.driver_cod_khr > 0 
                        THEN (
                            packages.driver_cod_khr
                            - ((packages.delivery_fee + packages.extra_charge) * {$effectiveExchangeRate})
                        )

                    WHEN packages.payer != 'receiver'
                        OR packages.status_id != 19
                        THEN packages.driver_cod_khr
                    ELSE 0
                END
            )
        ";

        $oweUsd = "
            SUM(
                CASE
                    WHEN packages.payer != 'receiver'
                        AND packages.status_id = 9
                        AND packages.driver_cod_usd = 0
                        AND packages.driver_cod_khr > 0
                        AND packages.driver_cod_khr < ((packages.delivery_fee + packages.extra_charge) * {$effectiveExchangeRate})
                        THEN (
                            ((packages.delivery_fee + packages.extra_charge) * {$effectiveExchangeRate}) - packages.driver_cod_khr
                        ) / {$effectiveExchangeRate}
                    ELSE 0
                END
            )
        ";

        $amountUsdExpr = "
            ROUND(
                ({$displaySumUsd} - {$fees}
                + LEAST(SUM(packages.driver_cod_khr)/MAX({$effectiveExchangeRate}), GREATEST({$fees} - SUM(packages.driver_cod_usd), 0)))::numeric,
                2
            )
        ";

        $amountKhrExpr = "
            GREATEST(
                CASE
                    -- USD covers all fees → only deduct row-level excess KHR (driver_cod_khr - price_khr)
                    WHEN ({$displaySumUsd}) >= {$fees}
                    THEN SUM(
                        CASE
                            WHEN packages.payer = 'receiver' AND packages.status_id = 19 THEN 0
                            -- Contribution based on driver COD KHR and price KHR
                            WHEN packages.driver_cod_khr > packages.price_khr THEN packages.driver_cod_khr - ((packages.delivery_fee + packages.extra_charge) * {$effectiveExchangeRate})
                            ELSE packages.driver_cod_khr
                        END
                    )
                    -- If no USD, calculate remaining KHR after deducting fees
                    WHEN ({$displaySumUsd}) = 0
                    THEN GREATEST(
                        SUM(
                            CASE
                                WHEN packages.payer = 'receiver' AND packages.status_id = 19 THEN 0
                                -- Contribution based on driver COD KHR and price KHR
                                WHEN packages.driver_cod_khr > packages.price_khr THEN packages.driver_cod_khr
                                ELSE packages.driver_cod_khr
                            END
                        ) - ({$fees} * {$effectiveExchangeRate}),
                        0
                    )
                    -- USD not enough → deduct remaining fees from KHR
                    ELSE
                        SUM(
                            CASE
                                WHEN packages.payer = 'receiver' AND packages.status_id = 19 THEN 0
                                ELSE packages.driver_cod_khr
                            END
                        ) - (({$fees} - {$displaySumUsd}) * {$effectiveExchangeRate})
                END,
                0
            )
        ";

        /** SELECT */
        $select = [
            'packages.merchant_id',
            DB::raw('COUNT(packages.id) as package_count'),
            DB::raw('SUM(packages.price) as total_price'),
            DB::raw('SUM(packages.price_khr) as total_price_khr'),
            DB::raw($fees.' as fees'),
            DB::raw("
                SUM(
                    CASE WHEN packages.payer = 'sender' THEN packages.taxi_fee ELSE 0 END
                ) as taxi_fee
            "),
            // DB::raw('SUM(packages.driver_cod_usd) as total_driver_cod_usd'),
            // DB::raw('SUM(packages.driver_cod_khr) as total_driver_cod_khr'),
            DB::raw($displaySumUsd.' as total_driver_cod_usd'),
            DB::raw($displaySumKhr.' as total_driver_cod_khr'),
            DB::raw($oweUsd.' as owe_usd'),
            DB::raw("array_agg(packages.id) as package_ids"),
            DB::raw("MAX(
                CASE
                    WHEN packages.status_id = 9 THEN DATE(packages.delivered_datetime)
                    WHEN packages.status_id = 19 THEN DATE(packages.failed_datetime)
                END
            ) as finish_date"),

            DB::raw('SUM(d.received_amount_usd) as dis_received_usd'),
            DB::raw('SUM(d.received_amount_khr) as dis_received_khr'),

            // payment received
            DB::raw('SUM(p.received_amount_usd) as pay_received_usd'),
            DB::raw('SUM(p.received_amount_khr) as pay_received_khr'),


            DB::raw("MAX(p.id) as payment_id"),
            DB::raw("MAX(d.id) as disbursement_id"),
    
            DB::raw("MAX(d.payment_status_id) as disbursement_status_id"),
            DB::raw("MAX(p.payment_status_id) as payment_status_id"),
            DB::raw("($amountUsdExpr) as amount_to_be_paid_usd"),
            DB::raw("($amountKhrExpr) as amount_to_be_paid_khr"),
        ];

        // Subqueries to get latest related rows per package (prevent duplication)
        $latestPP = DB::raw("
            (
                SELECT DISTINCT ON (package_id)
                    package_id,
                    payment_id,
                    id AS pp_id
                FROM payment_packages
                WHERE is_deleted = false
                ORDER BY package_id, id DESC
            ) latest_pp
        ");

        $latestDP = DB::raw("
            (
                SELECT DISTINCT ON (package_id)
                    package_id,
                    disbursement_id,
                    id AS dp_id
                FROM disbursement_packages
                WHERE is_deleted = false AND type = 'payment'
                ORDER BY package_id, id DESC
            ) latest_dp
        ");

        $defaultStatuses = [
            PaymentStatus::PARTIAL->value, 
            PaymentStatus::DECLINED->value,
            // PaymentStatus::SETTLED_KHR_REMAINING_USD->value,
            // PaymentStatus::SETTLED_USD_REMAINING_KHR->value
        ];

        // Base query
        $qP = Package::query()
            ->where('packages.is_deleted', false)
            ->whereIn('packages.status_id', [9, 19])
            ->leftJoin($latestDP, 'packages.id', '=', 'latest_dp.package_id')
            ->leftJoin('disbursements as d', function ($join) {
                $join->on('latest_dp.disbursement_id', '=', 'd.id')
                    ->where('d.payee_type', 'merchant')
                    ->where('d.is_deleted', false);
            })
            ->leftJoin($latestPP, 'packages.id', '=', 'latest_pp.package_id')
            ->leftJoin('payments as p', function ($join) use($startDate,$endDate) {
                $join->on('latest_pp.payment_id', '=', 'p.id')
                    ->where('p.payer_type', 'merchant');
                // if($startDate && $endDate){
                //     $startDateTime = Helper::dateYMD($startDate).' 00:00:00';
                //     $endDateTime = Helper::dateYMD($endDate).' 23:59:59';
                //     $join->where(function($q) use($startDateTime,$endDateTime){
                //         $q->where('p.payment_status_id',PaymentStatus::DECLINED->value)
                //         ->where('p.declined_date',[$startDateTime,$endDateTime]);
                //     });
                // }
            })
            ->where(function ($query) use($defaultStatuses){
                $query->where(function ($q) use($defaultStatuses){
                    $q->whereNull('latest_dp.dp_id')
                        ->orWhereIn('d.payment_status_id', $defaultStatuses)
                        ->orWhereNull('d.id');
                });
                $query->where(function ($q) use($defaultStatuses){
                    $q->whereNull('latest_pp.pp_id')
                        ->orWhereIn('p.payment_status_id', $defaultStatuses)
                        ->orWhereNull('p.id');
                });
            })
            ->when($paymentType === TransactionType::TRANSFER_IN->value, fn($q) =>
                $q->havingRaw("($amountUsdExpr) <= 0 AND ($amountKhrExpr) <= 0")
            )
            ->when($paymentType === TransactionType::TRANSFER_OUT->value, fn($q) =>
                $q->havingRaw("($amountUsdExpr) > 0 OR ($amountKhrExpr) > 0")
            )
            ->with(['merchant:id,username,code,phone', 'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name,currency'])
            ->select($select)
            ->groupByRaw("merchant_id, COALESCE(p.id, d.id, 0)")
            ->orderBy('finish_date', 'desc');

        // Date filter
        if (!$search) {
            if ($startDate && $endDate) {
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qP->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->where('packages.status_id', 9)
                            ->whereDate('packages.delivered_datetime', '>=', $startDate)
                            ->whereDate('packages.delivered_datetime', '<=', $endDate);
                    })->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('packages.status_id', 19)
                            ->whereDate('packages.failed_datetime', '>=', $startDate)
                            ->whereDate('packages.failed_datetime', '<=', $endDate);
                    });
                });
            }
        } else {
            $qP->whereHas('merchant', function ($q) use ($search) {
                $q->where('username', 'ilike', "%$search%")
                    ->orWhere('code', 'ilike', "%$search%")
                    ->orWhere('phone', 'ilike', "%$search%");
            });
        }

        // Post-format callback
        $callback = function ($q) {
            $q->code = $q->merchant->code;
            $q->merchant_name = $q->merchant->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->finish_date = Helper::dateDMY($q->finish_date, 'd-m-Y');
            $q->transaction_type = TransactionType::TRANSFER_IN->value;
            // $q->amount_to_be_paid_usd = $q->amount_to_be_paid_usd - $q->owe_usd;
            if ($q->amount_to_be_paid_khr > 0 || $q->amount_to_be_paid_usd > 0) {
                $q->transaction_type = TransactionType::TRANSFER_OUT->value;
            }

            $q->bank_accounts = $q->merchant->bank_accounts ?? [];

            $pmtStatusId = isset($q->disbursement_id) ? $q->disbursement_status_id 
                : (isset($q->payment_status_id) ? $q->payment_status_id : null);
            $q->status = isset($q->disbursement_id)
                ? PaymentStatus::tryFrom($pmtStatusId)->label()
                : (isset($q->payment_id)
                    ? PaymentStatus::tryFrom($pmtStatusId)->label()
                    : 'Unpaid');
            if($pmtStatusId){
                if(in_array($pmtStatusId,[
                    PaymentStatus::PARTIAL->value,
                    PaymentStatus::SETTLED_USD_REMAINING_KHR->value,
                    PaymentStatus::SETTLED_KHR_REMAINING_USD->value
                ])){
                    $q->is_paid_usd = (($q->dis_received_usd > 0) || ($q->pay_received_usd > 0)) ? true : false;
                    $q->is_paid_khr = (($q->dis_received_khr > 0) || ($q->pay_received_khr > 0))? true : false;
                }
            }else {
                $q->payment_status_id = PaymentStatus::UNPAID->value;
                $q->is_paid_usd = false;
                $q->is_paid_khr = false;
            }
            $q->amount_to_be_paid_usd = Helper::getNumber($q->amount_to_be_paid_usd, 2, true);
            $q->amount_to_be_paid_khr = Helper::getNumber($q->amount_to_be_paid_khr, 0, true);
            $q->total_driver_cod_usd = Helper::getNumber($q->total_driver_cod_usd, 2, true);
            $q->total_driver_cod_khr = Helper::getNumber($q->total_driver_cod_khr, 0, true);
            $q->total_price = Helper::getNumber($q->total_price, 2, true);
            $q->total_price_khr = Helper::getNumber($q->total_price_khr, 0, true);
            unset($q->merchant);
            return $q;
        };
        return DataResponse::PaginationV1($qP, $data, '', [], 1000, $callback, $select);
    }

    public function getRequestedSettlement(array $data, object $authUser): object
    {
        $perPage = $data['per_page'] ?? 10;
        $page = $data['page_no'] ?? 1;

        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $paymentType = $data['paymentType'] ?? null;
        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;
        $paymentMode = $data['payment_mode'] ?? null;
        // Common callback for transforming each record
        $callback = function($q) {
            $q->merchant_id = $q->merchant->id;
            $q->merchant_name = $q->merchant->username;
            $q->requested_username = $q->requestedUser?->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->merchant_code = $q->merchant->code;
            $rqD = $q->requested_date;
            // $q->transaction_type = TransactionType::TRNASFER_OUT->value;
            if ($q instanceof Disbursement) {
                $q->transaction_type = TransactionType::TRANSFER_OUT->value; // Out
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
                if(($pkg->package->status_id == 9 || $pkg->package->status_id == 19) && $pkg->package->payer == 'receiver'){
                    $checkFeeAndCodUsd = ($pkg->package->driver_cod_usd > 0) ? ($pkg->package->driver_cod_usd - ($pkg->package->delivery_fee + $pkg->package->extra_charge)):0;
                    $q->driver_cod_usd += $checkFeeAndCodUsd > 0 ? $checkFeeAndCodUsd:0;
                    $checkFeeAndCodKhr = ($pkg->package->driver_cod_khr > 0) ? ($pkg->package->driver_cod_khr - (($pkg->package->delivery_fee + $pkg->package->extra_charge) * $pkg->package->exchange_rate)):0;
                    $q->driver_cod_khr += $checkFeeAndCodKhr > 0 ? $checkFeeAndCodKhr : 0;
                }else{
                    $q->driver_cod_usd += $pkg->package->driver_cod_usd;
                    $q->driver_cod_khr += $pkg->package->driver_cod_khr;
                }

                $q->cod_usd += $pkg->package->price;
                $q->cod_khr += $pkg->package->price_khr;
                
            }

            $paymentStatusId = $q->payment_status_id;
            if(in_array($paymentStatusId,[
                PaymentStatus::APPROVE_AND_SETTLE->value,
                PaymentStatus::REQUESTED->value
            ])){
                $q->is_paid_usd = true;
                $q->is_paid_khr = true;
            }
            elseif($paymentStatusId === PaymentStatus::SETTLED_USD_REMAINING_KHR->value){
                $q->is_paid_usd = true;
                $q->is_paid_khr = false;
                // $q->amount_due_khr = 0;
            }
            elseif($paymentStatusId === PaymentStatus::SETTLED_KHR_REMAINING_USD->value){
                $q->is_paid_usd = false;
                $q->is_paid_khr = true;
                // $q->amount_due_usd = 0;
            }
            elseif($paymentStatusId === PaymentStatus::PARTIAL->value){
                $q->is_paid_usd = ($q->received_amount_usd > 0) ? true : false;
                $q->is_paid_khr = ($q->received_amount_khr > 0) ? true : false;
                if(!$q->is_paid_usd){
                    $q->amount_due_usd = 0;
                }
                if(!$q->is_paid_khr){
                    $q->amount_due_khr = 0;
                }
            }
            

            $q->bank_accounts = $q->merchant->bank_accounts ?? [];
            $q->package_count = count($q->pmtPackages);
            $q->cod_to_be_paid_usd = Helper::getNumber($q->amount_due_usd, 2, true);
            $q->cod_to_be_paid_khr = Helper::getNumber($q->amount_due_khr, 0, true);
            $q->driver_cod_usd = Helper::getNumber($q->driver_cod_usd,2,true);
            $q->driver_cod_khr = Helper::getNumber($q->driver_cod_khr,0,true);
            $q->cod_usd = Helper::getNumber($q->cod_usd,2,true);
            $q->cod_khr = Helper::getNumber($q->cod_khr,0,true);

            unset($q->pmtPackages, $q->merchant);
            $q->status = ($q->payment_status_id == PaymentStatus::PARTIAL->value) ? 
            "Requested"
            : PaymentStatus::tryFrom($q->payment_status_id)->label();
            return MerchantRequestedSettlementDTO::fromModel($q);
        };

        // Build Eloquent queries
        $disQuery = Disbursement::query()
            ->whereIn('payment_status_id', [
                PaymentStatus::PARTIAL->value,
                PaymentStatus::REQUESTED->value,
                PaymentStatus::SETTLED_KHR_REMAINING_USD->value,
                PaymentStatus::SETTLED_USD_REMAINING_KHR->value,
                PaymentStatus::APPROVE_AND_SETTLE->value
            ])
            ->where('is_deleted', false)
            ->where('payee_type', 'merchant')
            /** apply payment mode */
            ->when($paymentMode,function($q) use($paymentMode){
                $q->whereHas('merchant', function($q) use($paymentMode){
                    $q->where('payment_mode', $paymentMode);
                });
            })
            ->with([
                'requestedUser:id,username,phone,code',
                'merchant:id,username,phone,code,payment_mode',
                'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name,currency',
                'pmtPackages:id,disbursement_id,package_id',
                'pmtPackages.package:id,payer,status_id,delivery_fee,extra_charge,driver_cod_usd,driver_cod_khr,price,price_khr,exchange_rate'
            ])
            ->orderByRaw("
                CASE payment_status_id
                    WHEN 6 THEN 1
                    WHEN 8 THEN 2
                    WHEN 3 THEN 3
                END ASC
            ");

        $payQuery = Payment::query()
            ->whereIn('payment_status_id', [
                PaymentStatus::PARTIAL->value,
                PaymentStatus::REQUESTED->value,
                PaymentStatus::SETTLED_KHR_REMAINING_USD->value,
                PaymentStatus::SETTLED_USD_REMAINING_KHR->value,
                PaymentStatus::APPROVE_AND_SETTLE->value
            ])
            ->where('is_deleted', false)
            ->where('payer_type', 'merchant')
            /** apply payment mode */
            ->when($paymentMode,function($q) use($paymentMode){
                $q->whereHas('merchant', function($q) use($paymentMode){
                    $q->where('payment_mode', $paymentMode);
                });
            })
            ->with([
                'requestedUser:id,username,phone,code',
                'merchant:id,username,phone,code,payment_mode',
                'merchant.bank_accounts:id,user_id,bank_name,bank_number,account_name,currency',
                'pmtPackages:id,payment_id,package_id',
                'pmtPackages.package:id,payer,status_id,delivery_fee,extra_charge,driver_cod_usd,driver_cod_khr,price,price_khr,exchange_rate'
            ])
            ->orderByRaw("
                CASE payment_status_id
                    WHEN 6 THEN 1
                    WHEN 8 THEN 2
                    WHEN 3 THEN 3
                END ASC
            ");

        if($search){
            $payQuery->where(function($q) use($search){
                $q->whereHas('merchant',function($q) use($search){
                    $q->where('username','ILIKE',"%$search%");
                });
            });
            $disQuery->where(function($q) use($search){
                $q->whereHas('merchant',function($q) use($search){
                    $q->where('username','ILIKE',"%$search%");
                });
            });
        }


        if($status){
            if($status == PaymentStatus::REQUESTED->value){
                $disQuery->whereIn('payment_status_id',[
                    PaymentStatus::REQUESTED->value,
                    PaymentStatus::PARTIAL->value
                ]);
                $payQuery->whereIn('payment_status_id',[
                    PaymentStatus::REQUESTED->value,
                    PaymentStatus::PARTIAL->value
                ]);
            }
            else{
                $disQuery->where('payment_status_id',$status);
                $payQuery->where('payment_status_id',$status);
            }
        }

        
        if($startDate && $endDate){
            $startDateTime = Helper::dateYMD($startDate).' 00:00:00';
            $endDateTime = Helper::dateYMD($endDate).' 23:59:59';
            $disQuery->whereBetween('requested_date',[$startDateTime,$endDateTime]);
            $payQuery->whereBetween('requested_date',[$startDateTime,$endDateTime]);
        }

        $disData = collect();
        $payData = collect();
        // Fetch slices only for the current page
        if ($paymentType === 'out' || !$paymentType) {
            $disData = $disQuery
                // ->orderBy('payment_status_id','asc')
                ->orderBy('requested_date', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
        }


        if ($paymentType === 'in' || !$paymentType) {
            $payData = $payQuery
            ->orderBy('requested_date', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        }
        // Merge, sort, and slice to fit page
        $merged = $disData->merge($payData)
            // ->sortByDesc('requested_date')
            ->values();

        // Calculate total count
        $total = $disQuery->count() + $payQuery->count();
        $totalPages = (int)ceil($total / $perPage);

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


    public function declineRequetedSettlement(int $paymentId,array $data,object $authUser):object{
        $transactionType = $data['transaction_type'] ?? null;
        // $reason = $data['reason'] ?? null;
        // if(empty($reason)){
        //     return DataResponse::ValidateFail('Please fill reason');
        // }
        if($transactionType === TransactionType::TRANSFER_OUT->value){
            $disbursement = Disbursement::where('is_deleted',false)
            ->whereIn('payment_status_id',[PaymentStatus::REQUESTED->value,PaymentStatus::PARTIAL->value])
            ->find($paymentId);
            if(!$disbursement)return DataResponse::NotFound('Not found');
            $disbursement->update([
                'payment_status_id' => PaymentStatus::DECLINED->value,
                // 'reason' => $reason,
                'declined_date' => now(),
                'amount_due_usd' => 0,
                'amount_due_khr' => 0,
                'received_amount_khr' => 0,
                'received_amount_usd' => 0
            ]);
        }else if($transactionType === TransactionType::TRANSFER_IN->value){
            $payment = Payment::where('is_deleted',false)
            ->whereIn('payment_status_id',[PaymentStatus::REQUESTED->value,PaymentStatus::PARTIAL->value])
            ->find($paymentId);
            if(!$payment)return DataResponse::NotFound('Not found');
            $payment->update([
                'payment_status_id' => PaymentStatus::DECLINED->value,
                // 'reason' => $reason,
                'amount_due_usd' => 0,
                'declined_date' => now(),
                'amount_due_khr' => 0,
                'received_amount_khr' => 0,
                'received_amount_usd' => 0
            ]);
        }
        return DataResponse::JsonResult(null,false,'Declined');
    }

    public function approveAndSettleBulkRequestedSettlement(array $data,object $authUser):object{
        $validator = validator($data,[
            'currency' => 'required|in:KHR,USD',
            'payment_type' => 'required|in:in,out',
            'payment_ids' => 'required|array',
            'payment_ids.*.id' => 'int',
            'payment_ids.*.transaction_type' => 'string',
            'is_manual' => 'required|in:0,1'
        ]);

        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $isManual = $inputs['is_manual'] == '0' ? true:false;

        $inputsPayment = $inputs['payment_ids'];
        $paymentIds = array_column($inputsPayment,'id');

        $disbursements = Disbursement::where('is_deleted', false)
            ->whereIn('id', $paymentIds)
            ->with([
                'merchant:id,username',
                'pmtPackages:id,disbursement_id,package_id'
            ])
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
        $toInsertTrans = [];
        $toUpdatePayoutIds = [];
        $toPayout = [];
        $toUpdatePayin = [];
        $totalPkg = 0;
        $payoutPackageIds = [];
        $accountList = UserBank::where('is_deleted',false)
        ->select(['id','bank_name','account_name','bank_number as account_number','currency','user_id'])
        ->whereIn('user_id',$merchantIds)->get();
        $currency = $inputs['currency'];
        $approvedAmounts = [];
        // return DataResponse::JsonResult($accountList);
        foreach($inputsPayment as $idx => $p){
            $pId = $p['id'];
            $tranType = $p['transaction_type'];
            $paymentRef = null;
            if($tranType === TransactionType::TRANSFER_OUT->value){
                if(empty($disbursementById[$pId])){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => "Payment not found on row => {($idx + 1)}"
                    ]));
                }
                $disbursement = $disbursementById[$pId];
                if ($disbursement->payment_status_id === PaymentStatus::APPROVE_AND_SETTLE->value) {
                    return DataResponse::ValidateFail(__('messages.info', [
                        'info'   => 'This payment has already been approved and settled',
                        'khInfo' => 'ការទូទាត់នេះត្រូវបានអនុម័ត និងទូរទាត់រួចរាល់ហើយ'
                    ]));
                }
                // $payoutPackageIds[] = $disbursement->pmtPackages()->pluck('package_id')->toArray();
                $payoutPackageIds = array_merge(
                    $payoutPackageIds,
                    $disbursement->pmtPackages()->pluck('package_id')->toArray()
                );
                $approvedAmounts[$pId] = $currency === 'USD'
                    ? $disbursement->amount_due_usd
                    : $disbursement->amount_due_khr;

                $paymentRef = $disbursement->trx_code;

                // Partially settled USD, KHR remaining
                if ($currency === 'USD' && $disbursement->payment_status_id === PaymentStatus::SETTLED_USD_REMAINING_KHR->value) {
                    return DataResponse::Duplicated(__('messages.info', [
                        'info'   => 'This payment has already been settled in USD, remaining KHR',
                        'khInfo' => 'ការទូទាត់នេះបានទូរទាត់ជាសុទ្ធ USD ហើយ មាន KHR ប្រាក់នៅសល់'
                    ]));
                }

                // Partially settled KHR, USD remaining
                if ($currency === 'KHR' && $disbursement->payment_status_id === PaymentStatus::SETTLED_KHR_REMAINING_USD->value) {
                    return DataResponse::Duplicated(__('messages.info', [
                        'info'   => 'This payment has already been settled in KHR, remaining USD',
                        'khInfo' => 'ការទូទាត់នេះបានទូរទាត់ជាសុទ្ធ KHR ហើយ មាន USD ប្រាក់នៅសល់'
                    ]));
                }

                if(!in_array($disbursement->payment_status_id,[
                    // PaymentStatus::PARTIAL->value,
                    PaymentStatus::REQUESTED->value,
                    PaymentStatus::SETTLED_KHR_REMAINING_USD->value,
                    PaymentStatus::SETTLED_USD_REMAINING_KHR->value
                ])){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'Please ensure payment is requested, before settle',
                        'khInfo' => 'សូមប្រាកដថាបានស្នើការទូទាត់ជាមុនសិន មុនពេលធ្វើការបង់ប្រាក់'
                    ]));
                }
                $toUpdatePayoutIds[] = $pId;
                $receivedAmtUsd = $disbursement->amount_due_usd;
                $receivedAmtKhr = $disbursement->amount_due_khr;
                $paidAmtUsd = $disbursement->paid_amount_usd;
                $paidAmtKhr = $disbursement->paid_amount_khr;
                $totalPkg += $disbursement->package_count;
            }else if($tranType === TransactionType::TRANSFER_IN->value){
                if(empty($paymentById[$pId])){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => "Payment not found on row => {($idx + 1)}"
                    ]));
                }
                $payment = $paymentById[$pId];
                if ($payment->payment_status_id === PaymentStatus::APPROVE_AND_SETTLE->value) {
                return DataResponse::ValidateFail(__('messages.info', [
                        'info'   => 'This payment has already been approved and settled',
                        'khInfo' => 'ការទូទាត់នេះត្រូវបានអនុម័ត និងទូរទាត់រួចរាល់ហើយ'
                    ]));
                }

                if($payment->payment_status_id !== PaymentStatus::REQUESTED->value){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'Please ensure payment is requested, before settle',
                        'khInfo' => 'សូមប្រាកដថាបានស្នើការទូទាត់ជាមុនសិន មុនពេលធ្វើការបង់ប្រាក់'
                    ]));
                }
                $paymentRef = $payment->trx_code;
                $toUpdatePayin[] = $pId;
                $receivedAmtUsd = $payment->amount_due_usd;
                $receivedAmtKhr = $payment->amount_due_khr;
            }

            $dueAmounts = [];
            if ($isManual) {
                $currencies = $currency === 'KHR'
                ? ['KHR' => [$receivedAmtKhr ?? 0, $paidAmtKhr ?? 0], 'USD' => [$receivedAmtUsd ?? 0, $paidAmtUsd ?? 0]]
                : ['USD' => [$receivedAmtUsd ?? 0, $paidAmtUsd ?? 0], 'KHR' => [$receivedAmtKhr ?? 0, $paidAmtKhr ?? 0]];
                $processedCurrency = $currency; // Track if we've already processed a non-zero currency.
                foreach ($currencies as $curKey => [$received, $paid]) {
                    if ($received < 0) {
                        continue; // Skip invalid amounts
                    }

                    // If payment exists, include only if mismatch
                    if ($pId && $received == $paid) {
                        if ($processedCurrency !== null && $received == 0) {
                            continue; // Skip adding zero if we've already processed a non-zero currency
                        }
                    }

                    // Push both currencies if another has received == 0
                    if ($processedCurrency == $curKey && $received > 0) {
                        $dueAmounts[] = [
                            'currency' => $curKey,
                            'amount'   => $received,
                        ];
                    }
                }
            }
            else {
                // Keep the existing single-currency behavior
                if ($currency === 'USD' && $receivedAmtUsd > 0) {
                    $dueAmounts[] = [
                        'currency' => 'USD',
                        'amount' => $receivedAmtUsd
                    ];
                }
                if ($currency === 'KHR' && $receivedAmtKhr > 0) {
                    $dueAmounts[] = [
                        'currency' => 'KHR',
                        'amount' => $receivedAmtKhr
                    ];
                }
            }

            $targetUid = $tranType === TransactionType::TRANSFER_IN->value ?
                $payment->payer_id : $disbursement->payee_id;

            $targetUsername = $tranType === TransactionType::TRANSFER_IN->value ?
                $payment->merchant->username : $disbursement->merchant->username;
            

            if(empty($dueAmounts)){
                return DataResponse::ValidateFail(__('messages.seem_no_currency_to_settle',[
                    'currency' => $currency,
                    'info' => __('messages.please_check_and_ensure_all_payment_has_received_amount_in',[
                        'currency' => $currency,
                        'username' => $targetUsername,
                    ])
                ]));
            }
            foreach ($dueAmounts as $dueAmt) {
                if(!$isManual){
                    $dueAccount = $this->dueBankAccounts($accountList, $targetUid, $dueAmt['currency']);
                    if (empty($dueAccount)) {
                        return DataResponse::NotFound(__('messages.info', [
                            'info'   => "Merchant {$targetUsername} has no bank account for {$dueAmt['currency']}",
                            'khInfo' => "អ្នកលក់ {$targetUsername} មិនមានគណនីសម្រាប់រូបិយប័ណ្ណ {$dueAmt['currency']}"
                        ]));
                    }
                }
                $toInsertTrans[] = [
                    'currency' => $dueAmt['currency'],
                    'source' => $tranType == TransactionType::TRANSFER_OUT->value ? 'disbursement':'payment',
                    'amount' => $dueAmt['amount'],
                    'payment_date' => now(),
                    'payment_method' => 'bank',
                    'payment_ref' => $paymentRef,
                    'tran_via' => TransactionType::INTERNAL->value,
                    'payment_id' => $pId,
                    'transaction_type' => $tranType,
                    'from_account' => config('app.company_bank_account'),
                    'to_account' => $dueAccount['concat'] ?? '',
                    'approved_uid' => $authUser->id,
                    'create_uid' => $authUser->id,
                    'update_uid' => $authUser->id,
                    'branch_id' => $authUser->branch_id,
                    'company_id' => $authUser->company_id,
                    'details' => null
                ];
                $toPayout[] = [
                    'account' => $dueAccount['account_number'] ?? '',
                    'currency' => $dueAmt['currency'],
                    'amount' => $dueAmt['amount'],
                ];
            }
        }

        try{
            DB::beginTransaction();
            if(!empty($toUpdatePayoutIds)){
                if(!$isManual){
                    // $totalAmt = array_sum(array_column($toPayout, 'amount'));
                    $totalAmt = number_format(array_sum(array_column($toPayout, 'amount')), 2, '.', '');
                    if($totalAmt <= 0){
                        return DataResponse::ValidateFail(__('messages.seem_no_currency_to_settle',[
                            'currency' => $currency,
                            'info' => __('messages.please_check_and_ensure_all_payment_has_received_amount_in',[
                                'currency' => $currency,
                            ])
                        ]));
                    }
                    $payoutResult = $this->paywayService->payout($currency, $totalAmt, $toPayout,$totalPkg);

                    if ($payoutResult->error) {
                        DB::rollBack();
                        return $payoutResult;
                    }

                    $payoutData = is_string($payoutResult->data)
                    ? json_decode($payoutResult->data, true)
                    : $payoutResult->data;
                    $transactionId = $payoutData['transaction_id'] ?? null;
                    // Include payment_ref in each transaction before inserting
                    foreach ($toInsertTrans as &$trans) {
                        if(
                            $trans['source'] == 'disbursement' 
                            && in_array($trans['payment_id'],$toUpdatePayoutIds)
                        ){
                            $trans['transaction_type'] = TransactionType::TRANSFER_OUT->value;
                            $trans['payment_ref'] = $transactionId;
                            $trans['payment_method'] = PaywayType::ABA_PAYOUT->value;
                            $trans['tran_via'] = PaywayType::ABA_PAYOUT->value;
                            $trans['details'] = json_encode($payoutData, JSON_THROW_ON_ERROR);
                        }
                    }
                    unset($trans);
                    $this->paywayService->paywaylog($payoutResult->data['transaction_id'],$authUser,PaywayType::ABA_PAYOUT->value,PaywayProvider::ABA->value,json_encode([
                        'items' => $payoutPackageIds,
                        'response' => $payoutResult->data
                    ]),$payoutResult->data['apv'],json_encode($inputs),PaywayStatus::DONE->value);
                }

                foreach ($toUpdatePayoutIds as $pId) {
                    $approvedAmt = $approvedAmounts[$pId];
                    if ($isManual) {
                        $curDisbursement = $disbursementById[$pId];
                        $nextStatusId = PaymentStatus::APPROVE_AND_SETTLE->value;
                        $con1 = (
                                $curDisbursement->payment_status_id === PaymentStatus::SETTLED_USD_REMAINING_KHR->value && $currency === 'KHR'
                            );
                        $con2 = (
                                $curDisbursement->payment_status_id === PaymentStatus::SETTLED_KHR_REMAINING_USD->value && $currency === 'USD'
                            );
                        $con3 = (
                                $curDisbursement->payment_status_id === PaymentStatus::REQUESTED->value
                            );
                        if (
                            $con1 || $con2 || $con3
                        ) {
                            // Check if the other currency has not yet been fully paid
                            $compareAmtUsd = $con3 ? $curDisbursement->amount_due_usd : $curDisbursement->paid_amount_usd;
                            $compareAmtKhr = $con3 ? $curDisbursement->amount_due_khr : $curDisbursement->paid_amount_khr;
                            $usdNotPaid = isset($disbursementById[$pId]->paid_amount_usd) && $disbursementById[$pId]->paid_amount_usd < $disbursementById[$pId]->usd_due;
                            $khrNotPaid = isset($disbursementById[$pId]->paid_amount_khr) && $disbursementById[$pId]->paid_amount_khr < $disbursementById[$pId]->khr_due;

                            if($con3){
                                if($currency === 'KHR' && $compareAmtUsd > 0){
                                    $nextStatusId = PaymentStatus::SETTLED_KHR_REMAINING_USD->value;
                                } elseif($currency === 'USD' && $compareAmtKhr > 0){
                                    $nextStatusId = PaymentStatus::SETTLED_USD_REMAINING_KHR->value;
                                }
                            }else{
                                if ($currency === 'KHR' && $usdNotPaid) {
                                    $nextStatusId = PaymentStatus::SETTLED_USD_REMAINING_KHR->value;
                                } elseif ($currency === 'USD' && $khrNotPaid) {
                                    $nextStatusId = PaymentStatus::SETTLED_KHR_REMAINING_USD->value;
                                }
                            }
                            // Dynamically set the next status based on the currency and other currency's payment status
                            
                        }
                        // In manual mode, settle everything at once
                        $updateDisArr = [
                            'payment_status_id'=> $nextStatusId,
                            'settled_datetime' => now(),
                            'payment_datetime' => now(),
                            'is_settled'       => true,
                            'settled_uid'      => $authUser->id,
                        ];
                        if($currency == 'USD'){
                            $updateDisArr['paid_amount_usd'] = DB::raw("paid_amount_usd + $approvedAmt");
                        }
                        if($currency == 'KHR'){
                            $updateDisArr['paid_amount_khr'] = DB::raw("paid_amount_khr + $approvedAmt");
                        }
                        Disbursement::where('id', $pId)->update($updateDisArr);
                        DisbursementDetails::create([
                            'disbursement_id' => $pId,
                            'method' => 'internal',
                            'amount' => $dueAmt['amount'],
                            'original_amount' => $dueAmt['amount'],
                            'currency_code' => $currency
                        ]);
                    } 
                    else {
                        // Keep existing partial logic
                        $usdIncrement = $currency === 'USD' ? $approvedAmt : 0;
                        $khrIncrement = $currency === 'KHR' ? $approvedAmt : 0;

                        $updateDis = [
                            'payment_status_id' => DB::raw("
                                CASE
                                    -- Only upgrade from SETTLED_KHR_REMAINING_USD → APPROVE_AND_SETTLE
                                    WHEN 
                                        payment_status_id = " . PaymentStatus::SETTLED_KHR_REMAINING_USD->value . "
                                    THEN " . PaymentStatus::APPROVE_AND_SETTLE->value . "

                                    WHEN 
                                        payment_status_id = " . PaymentStatus::SETTLED_USD_REMAINING_KHR->value . "
                                    THEN " . PaymentStatus::APPROVE_AND_SETTLE->value . "

                                    -- Your existing rules
                                    WHEN amount_due_usd = paid_amount_usd + {$usdIncrement}
                                        AND amount_due_khr = paid_amount_khr + {$khrIncrement}
                                        THEN " . PaymentStatus::APPROVE_AND_SETTLE->value . "

                                    WHEN '{$currency}' = 'USD' 
                                        AND amount_due_usd = paid_amount_usd + {$usdIncrement}
                                        THEN " . PaymentStatus::SETTLED_USD_REMAINING_KHR->value . "

                                    WHEN '{$currency}' = 'KHR' 
                                        AND amount_due_khr = paid_amount_khr + {$khrIncrement}
                                        THEN " . PaymentStatus::SETTLED_KHR_REMAINING_USD->value . "
                                    ELSE payment_status_id
                                END
                            "),
                            'approved' => true,
                            'approved_uid' => $authUser->id,
                            'approved_datetime' => now(),
                            'settled_datetime' => now(),
                            'payment_datetime' => now(),
                            'is_settled'       => true,
                            'settled_uid'      => $authUser->id,
                        ];
                        if($currency == 'USD'){
                            $updateDis['paid_amount_usd'] = DB::raw("$usdIncrement");
                        }
                        if($currency == 'KHR'){
                            $updateDis['paid_amount_khr'] = DB::raw("$khrIncrement");
                        }
                        Disbursement::where('id', $pId)->update($updateDis);

                        DisbursementDetails::create([
                            'disbursement_id' => $pId,
                            'method' => 'internal',
                            'amount' => $dueAmt['amount'],
                            'original_amount' => $dueAmt['amount'],
                            'currency_code' => $currency
                        ]);
                    }

                }
                $totalAmt = array_sum(array_column($toPayout, 'amount'));
            }

            if (!empty($toUpdatePayin)) {
                $updateData = [
                    'approved' => true,
                    'approved_uid' => $authUser->id,
                    'approved_datetime' => now(),
                    'payment_datetime' => now(),
                    'payment_status_id' => PaymentStatus::APPROVE_AND_SETTLE->value,
                    'settled_datetime'  => now(),
                    'is_settled'        => true,
                    'settled_uid'       => $authUser->id
                ];
                
                Payment::whereIn('id', $toUpdatePayin)->update($updateData);
            }

            if(!empty($payoutPackageIds)){
                Package::whereIn('id',$payoutPackageIds)->update([
                    'method' => PaymentMethod::ABA_APP->value,
                ]);
            }
            // if(!empty($toUpdatePayin)){
            //     Payment::whereIn('id',$toUpdatePayin)->update([
            //         'payment_status_id' => PaymentStatus::APPROVE_AND_SETTLE->value,
            //         'settled_datetime' => now(),
            //         'is_settled' => true,
            //         'settled_uid' => $authUser->id
            //     ]);
            // }
            PaymentTransaction::insert($toInsertTrans);
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            if($e instanceof ForbiddenExcept){
                return DataResponse::Forbidden($e->getMessage());
            }
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error('Failed to approve');
        }
    }


    public static function dueBankAccounts($accountList, $userId, $currency): array
    {
        // Filter accounts by user
        $userAccounts = $accountList->where('user_id', $userId);
        

        if ($userAccounts->isEmpty()) {
            return [];
        }

        // Try to get account with requested currency
        $matched = $userAccounts->firstWhere('currency', $currency);

        if ($matched) {
            return [
                'account_number' => $matched->account_number,
                'account_name'   => $matched->account_name,
                'concat' => "{$matched->currency}|{$matched->account_name}|{$matched->account_number}|{$matched->bank_name}"
            ];
        }

        // matched: return first available account
        $fallback = $userAccounts->first();
        return [
            'account_number' => $fallback->account_number,
            'account_name'   => $fallback->account_name,
            'concat' => "{$fallback->currency}|{$fallback->account_name}|{$fallback->account_number}|{$fallback->bank_name}"
        ];
    }

    public function getSettledPaymentTransactions(array $data, $authUser): object
    {
        $transactionType = $data['transaction_type'] ?? null;
        $merchantId = $data['merchant_id'] ?? null;
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $currency = $data['currency'] ?? null;
        $qTrx = PaymentTransaction::query()
        ->with([
            'disbursement:id,payee_id,requested_uid,received_amount_usd,received_amount_khr',
            'disbursement.requestedUser:id,username,code',
            'disbursement.merchant:id,username,phone,code',
            // 'disbursement.merchant.bank_accounts:id,user_id,currency,bank_name,bank_number,account_name',
            'approver:id,username',
            'payment:id,payer_id,requested_uid,received_amount_usd,received_amount_khr',
            'payment.merchant:id,username,phone,code',
            'payment.requestedUser:id,username,code',
            // 'payment.merchant.bank_accounts:id,user_id,currency,bank_name,bank_number,account_name'
        ])
        ->where('payment_transactions.is_deleted',false)
        ->orderByDesc('payment_transactions.id');
        
        if($transactionType){
            $qTrx->where('payment_transactions.transaction_type',$transactionType);
        }

        if($currency){
            $qTrx->where('payment_transactions.currency',$currency);
        }

        if($startDate && $endDate){
            $qTrx->whereBetween('payment_transactions.payment_date',[
                Helper::dateYMD($startDate).' 00:00:00',
                Helper::dateYMD($endDate).' 23:59:59'
            ]);
        }

        if($merchantId){
            $qTrx->where(function($q) use($merchantId){
                $q->whereHas('disbursement',function($q)use($merchantId){
                    $q->where('payee_type','merchant')
                    ->where('payee_id',$merchantId);
                })
                ->orWhereHas('payment',function($q)use($merchantId){
                    $q->where('payer_type','merchant')
                    ->where('payer_id',$merchantId);
                });
            });
        }
        $summaryQuery = (clone $qTrx)
            ->selectRaw("
                id,
                payment_id,
                currency,
                transaction_type,
                SUM(amount) as total_balance
            ")
            ->groupBy('currency','transaction_type','payment_id','id');

        $summaryData = DB::table(DB::raw("({$summaryQuery->toSql()}) as t"))
            ->mergeBindings($summaryQuery->getQuery())
            ->select('currency','transaction_type', DB::raw('SUM(total_balance) as total_balance'))
            ->groupBy('currency','transaction_type')
            ->get();
        
        // ✅ Count unique merchants
        $inMerchantIds = (clone $qTrx)
            ->where('transaction_type', 'in')
            ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
            ->pluck('payments.payer_id');

        $outMerchantIds = (clone $qTrx)
            ->where('transaction_type', 'out')
            ->join('disbursements', 'disbursements.id', '=', 'payment_transactions.payment_id')
            ->where('disbursements.payee_type', 'merchant')
            ->pluck('disbursements.payee_id');

        // ✅ Merge & count unique
        $merchantCount = $inMerchantIds
            ->merge($outMerchantIds)
            ->filter()
            ->unique()
            ->count();

           /** Arrizon Accounts */ 
        $ArzAccounts = [
            "USD" => config('app.company_name') . " USD (013 807 284)",
            "KHR" => config('app.company_name') . " KHR (013 807 310)"
        ];
        
        $select = ['id','tran_via','approved_uid','transaction_type','payment_id','payment_date','to_account','from_account','payment_ref','amount','currency','details'];
        $callback = function($q) use($ArzAccounts): MerchantSettledTransactionDTO{
            $merchant = $q->transferable?->merchant ?? $q->disbursement?->merchant ?? $q->payment?->merchant;
            $q->merchant_name = $merchant?->username ?? '';//($q->transaction_type == TransactionType::TRANSFER_OUT )? $q->disbursement?->merchant->username ?? $q->payment?->merchant->username ?? '';
            $q->merchant_code = $merchant?->code ?? ''; //$q->disbursement?->merchant->code ?? $q->payment?->merchant->username ?? '';
            $pmtDate = $q->payment_date;
            $q->payment_date = Helper::formatCustomDateTime($pmtDate,'d-M-Y');
            $q->payment_time = Helper::formatCustomDateTime($pmtDate,'h:i A');
            // $q->bank_name = 
            if($q->currency == Currency::KHR->value){
                $q->fmt_amount = Helper::amountStdFmt($q->amount,'KHR');
            }else{
                $q->fmt_amount = Helper::amountStdFmt($q->amount);
            }
            $q->transaction_type = TransactionType::tryFrom($q->transaction_type)->label();
            if($q->to_account){
                $parts = explode('|', $q->to_account);
                $q->bank_account = [
                    'currency' => $parts[0] ?? null,
                    'account_name' => $parts[1] ?? null,
                    'account_number' => $parts[2] ?? null,
                    'bank_name' => $parts[3] ?? null,
                ];
            }
            $q->verified_by = $q->disbursement?->requestedUser?->username ?? $q->payment?->requestedUser?->username ?? '';
            // $q->bank_accounts = $q->disbursement?->merchant?->bank_accounts ?? $q->payment?->merchant?->bank_accounts ?? [];
            $q->approved_by = $q->approver->username;
            $details = $q->details;
            $beneficiaries = $details['beneficiaries'] ?? [];
            $beneficiary = null;
            foreach($beneficiaries as $b){
                if($b['mid_account'] == $parts[2]){
                    $beneficiary = $b;
                    break;
                }
            }
            
            $trxDate = $details['transaction_date'] ?? '';
            $q->info = [
                'tran_id' => $q->payment_ref,
                'payer_account' => $ArzAccounts[$q->currency],
                'payee_account' => $q->bank_account,
                'currency' => $q->currency,
                'amount' => Helper::amountStdFmt($q->amount),
                'transaction_date' => $trxDate ? Helper::dateDMY($trxDate,'M d, Y'):'',
                'transaction_time' => $trxDate ? Helper::dateDMY($trxDate,'h:i A'):'',
                'external_reference' => $details['external_reference'] ?? '',
                'beneficiary' => $beneficiary
            ];
            
            return MerchantSettledTransactionDTO::fromModel($q);
            // return $q;
        };
        $additionalData = [
            'merchant_count' => $merchantCount,
            'total_summary' => $this->buildSettledTransactionTotals($summaryData)
        ];
        return DataResponse::PaginationV1($qTrx,$data,'',$additionalData,500,$callback,$select);
    }

    private function buildSettledTransactionTotals($summaryData) {
        return [
            'total_in_usd'  => Helper::amountStdFmt(
                $summaryData->where('currency', 'USD')->where('transaction_type', 'in')->sum('total_balance'),
                'USD'
            ),

            'total_out_usd' => Helper::amountStdFmt(
                $summaryData->where('currency', 'USD')->where('transaction_type', 'out')->sum('total_balance'),
                'USD'
            ),

            'total_in_khr'  => Helper::amountStdFmt(
                $summaryData->where('currency', 'KHR')->where('transaction_type', 'in')->sum('total_balance'),
                'KHR'
            ),

            'total_out_khr' => Helper::amountStdFmt(
                $summaryData->where('currency', 'KHR')->where('transaction_type', 'out')->sum('total_balance'),
                'KHR'
            ),
        ];
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
            return DataResponse::NotFound('Not found');
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

    public function approveAndSettleRequestedSettlement(int $paymentId,string $transactionType,object $authUser):object{
        if(empty($transactionType)){
            return DataResponse::ValidateFail('Transaction type must be provided');
        }
        $disbursement = Disbursement::where('is_deleted', false)
            ->with(['merchant:id,username'])
            ->find($paymentId);

        if(!$disbursement){
            return DataResponse::NotFound('Not found');
        }

        if ($disbursement->payment_status_id === PaymentStatus::APPROVE_AND_SETTLE->value) {
            return DataResponse::ValidateFail(__('messages.info', [
                'info'   => 'This payment has already been approved and settled',
                'khInfo' => 'ការទូទាត់នេះត្រូវបានអនុម័ត និងទូរទាត់រួចរាល់ហើយ'
            ]));
        }

        if($disbursement->payment_status_id !== PaymentStatus::REQUESTED->value){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Please ensure payment is requested, before settle',
                'khInfo' => 'សូមប្រាកដថាបានស្នើការទូទាត់ជាមុនសិន មុនពេលធ្វើការបង់ប្រាក់'
            ]));
        }
        $receivedAmtUsd = $disbursement->amount_due_usd;
        $receivedAmtKhr = $disbursement->amount_due_khr;
        $dueAmounts = [];
        if($receivedAmtUsd >= 0 ){
            $dueAmounts[] = [
                'currency' => 'USD',
                'amount' => $receivedAmtUsd
            ];
        }
        if($receivedAmtKhr >= 0 ){
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
            $dueAccount = self::dueBankAccounts($accountList, $disbursement->payee_id, $dueAmt['currency']);
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
                'tran_via' => TransactionType::ABA_PAYOUT->value,
                'transaction_type' => TransactionType::TRANSFER_OUT->value,
                'from_account' => config('app.code_prefix').' Company',
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
            // DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            Log::error($e->getMessage());
            DB::rollBack();
            return DataResponse::Error('Failed to approve');
        }
    }


    public function receiveOrDisbursementBulk(array $data,object $user, string $type):object
    {
        $validator = $this->disburesementBulkV1Validator($data,$type);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $payingCurrency = $inputs['currency'];
        $userIds = collect($inputs["{$type}s"])->pluck('id')->toArray();
        $packageIds = collect($inputs["{$type}s"])->pluck('packages')->flatten()->toArray();
        $packages = $this->getBulkPaymentPackages($packageIds,$type,$userIds);
        $clPkg = clone $packages;
        $packageKeyById = $clPkg->keyBy('id');
        $userInfo = $inputs["{$type}s"];

        $insertPayout = [];
        $insertPayIn = [];
        $upSertPayout = [];
        $upSertPayIn = [];
        $fullyPaidInfo = [];
        $currencyConflictInfo = [];
        $invalidAmountInfo = [];
        $payOutPackages = $this->getPayOutPackagesKeyByMerchantId($packageIds,$type);
        $payInPackages = $this->getPayInPackagesKeyByMerchantId($packageIds,$type);

        // return DataResponse::JsonResult($payOutPackages);
        foreach ($userInfo as $rowIdx => $m) {
            $rowIdx = $rowIdx + 1;
            $mId = $m['id'];
            $transactionType = $m['transaction_type'] ?? null;
            if(!$transactionType){
                return DataResponse::ValidateFail('Transaction Type must be provided');
            }
            $packages = $m['packages'];
            // Validate packages
            $validPkg = $this->validBulkPackagesV1($packageKeyById, $packages, $mId, $type);
            if ($validPkg->error) {
                return $validPkg;
            }

            // $totalDueUSD = $validPkg->data['total_due_amount_usd'] ?? 0;
            // $totalDueKHR = $validPkg->data['total_due_amount_khr'] ?? 0;
            // if (($totalDueUSD + $totalDueKHR) < 0) {
            //     $merchantName = $validPkg->data['merchant_name'];
            //     $merchantCode = $validPkg->data['merchant_code'];
            //     return DataResponse::Duplicated(__('messages.info', [
            //         'info'   => "No payment is required for merchant {$merchantName} (ID: {$merchantCode}) because all packages have no due amount.",
            //         'khInfo' => "មិនចាំបាច់បង់សម្រាប់អ្នកលក់ {$merchantName} (ID: {$merchantCode}) ពីព្រោះគ្រប់កញ្ចប់គ្មានប្រាក់ដែលត្រូវបង់ទេ។"
            //     ]));
            // }

            $paidPackageIds   = [];
            $receivedUSD      = 0;
            $receivedKHR      = 0;
            $hasPmtId = false;
            $targetPmt = null;
            // $disbursementId = null;

            $allUSDReceived = true;
            $allKHRReceived = true;

            if($transactionType === TransactionType::TRANSFER_OUT->value){
                $res = $this->preparePayout($m['packages'],'merchant', $payingCurrency, $validPkg, $mId, $payOutPackages,$rowIdx);
                $fullyPaidInfo        = array_merge($fullyPaidInfo, $res['fullyPaidInfo']);
                $currencyConflictInfo = array_merge($currencyConflictInfo, $res['currencyConflictInfo']);
                $invalidAmountInfo    = array_merge($invalidAmountInfo, $res['invalidAmountInfo']);
                $allUSDReceived       = $allUSDReceived && $res['allUSDReceived'];
                $allKHRReceived       = $allKHRReceived && $res['allKHRReceived'];
                $hasPmtId = $res['targetId'];
                $targetPmt = $res['target'];
                $receivedKHR = $res['receivedKHR'];
                $receivedUSD = $res['receivedUSD'];
            }else if($transactionType === TransactionType::TRANSFER_IN->value){
                $res = $this->preparePayIn($m['packages'],'merchant', $payingCurrency, $validPkg, $mId, $payInPackages);
                $fullyPaidInfo        = array_merge($fullyPaidInfo, $res['fullyPaidInfo']);
                $currencyConflictInfo = array_merge($currencyConflictInfo, $res['currencyConflictInfo']);
                $invalidAmountInfo    = array_merge($invalidAmountInfo, $res['invalidAmountInfo']);
                $allUSDReceived       = $allUSDReceived && $res['allUSDReceived'];
                $allKHRReceived       = $allKHRReceived && $res['allKHRReceived'];
                $hasPmtId = $res['targetId'];
                $targetPmt = $res['target'];
                $receivedKHR = $res['receivedKHR'];
                $receivedUSD = $res['receivedUSD'];
            }
            // return DataResponse::JsonResult($res);

            // foreach ($packages as $pkgId) {
                // if($transactionType === TransactionType::TRNASFER_OUT->value){
                //     $res = $this->preparePayout($pkgId, 'merchant', $payingCurrency, $validPkg, $mId, $payOutPackages);
                //     $fullyPaidInfo        = array_merge($fullyPaidInfo, $res['fullyPaidInfo']);
                //     $currencyConflictInfo = array_merge($currencyConflictInfo, $res['currencyConflictInfo']);
                //     $invalidAmountInfo    = array_merge($invalidAmountInfo, $res['invalidAmountInfo']);
                //     $allUSDReceived       = $allUSDReceived && $res['allUSDReceived'];
                //     $allKHRReceived       = $allKHRReceived && $res['allKHRReceived'];
                //     $hasPmtId = $res['targetId'];
                //     $targetPmt = $res['target'];
                //     $receivedKHR = $res['receivedKHR'];
                //     $receivedUSD = $res['receivedUSD'];
                // }else if($transactionType === TransactionType::TRANSFER_IN->value){
                //     $res = $this->preparePayIn($pkgId, 'merchant', $payingCurrency, $validPkg, $mId, $payInPackages);
                //     $fullyPaidInfo        = array_merge($fullyPaidInfo, $res['fullyPaidInfo']);
                //     $currencyConflictInfo = array_merge($currencyConflictInfo, $res['currencyConflictInfo']);
                //     $invalidAmountInfo    = array_merge($invalidAmountInfo, $res['invalidAmountInfo']);
                //     $allUSDReceived       = $allUSDReceived && $res['allUSDReceived'];
                //     $allKHRReceived       = $allKHRReceived && $res['allKHRReceived'];
                //     $hasPmtId = $res['targetId'];
                //     $targetPmt = $res['target'];
                //     $receivedKHR = $res['receivedKHR'];
                //     $receivedUSD = $res['receivedUSD'];
                // }
            //     $paidPackageIds[] = $pkgId;
            // }
            $paymentStatusId = ($allUSDReceived && $allKHRReceived)
                ? PaymentStatus::REQUESTED->value
                : (($hasPmtId && $targetPmt->payment_status_id == PaymentStatus::PARTIAL->value) 
                ? PaymentStatus::REQUESTED->value : PaymentStatus::PARTIAL->value);
            if ($hasPmtId && $targetPmt) {
                // Update existing disbursement instead of inserting
                $updatePmt = [
                    'id' => $hasPmtId,
                    'payment_status_id' => $paymentStatusId,
                    'remarks'           => $inputs['remarks'] ?? $targetPmt->remarks,
                    'create_uid'        => $targetPmt->create_uid,
                    'branch_id'         => $targetPmt->branch_id,
                    'company_id'        => $targetPmt->company_id,
                    'update_uid'        => $user->id,
                    'requested_uid'     => $user->id,
                    'requested_date'    => now()
                ];

                if ($payingCurrency === 'USD') {
                    $updatePmt['received_amount_usd'] = $validPkg->data['total_due_amount_usd'] ?? 0;
                } else if ($payingCurrency === 'KHR') {
                    $updatePmt['received_amount_khr'] = $validPkg->data['total_due_amount_khr'] ?? 0;
                }
                $allowedColumns = [
                    'amount_due_usd', 'delivery_fee', 'amount_due_khr', 'received_amount_khr', 'received_amount_usd',
                    'amount', 'payable_amount', 'taxi_fee', 'cod_amount', 'package_count', 'delivered_package_count',
                    'approved', 'is_settled', 'breakdown_notes', 'settled_uid', 'exchange_rate','requested_uid',
                    'approved_datetime', 'settled_datetime', 'payment_datetime', 'type',
                    'failed_with_fee_count', 'trx_code', 'paid_amount','requested_date'
                ];

                // Merge existing values from targetPmt for allowed columns
                foreach ($allowedColumns as $col) {
                    if (!array_key_exists($col, $updatePmt)) {
                        $updatePmt[$col] = $targetPmt->$col ?? null;
                    }
                }

                if($transactionType === TransactionType::TRANSFER_OUT->value){
                    $updatePmt['amount_due_khr']    = $validPkg->data['total_due_amount_khr'];
                    $updatePmt['amount_due_usd']    = $validPkg->data['total_due_amount_usd'];
                    $updatePmt['payee_id']          = $targetPmt->payee_id;
                    $updatePmt['payee_type']        = $targetPmt->payee_type;
                    $updatePmt['receiptionist_uid']        = $targetPmt->receiptionist_uid;
                    $updatePmt['delivery_rate']        = $targetPmt->delivery_rate;
                    $updatePmt['pickup_rate']        = $targetPmt->pickup_rate;
                    $updatePmt['pickup_package_count']  = $targetPmt->pickup_package_count;
                    $upSertPayout[] = $updatePmt;
                }

                if($transactionType === TransactionType::TRANSFER_IN->value){
                    $updatePmt['amount_due_khr']    = abs($validPkg->data['total_due_amount_khr']);
                    $updatePmt['amount_due_usd']    = abs($validPkg->data['total_due_amount_usd']);
                    $updatePmt['received_amount_usd']    = abs($validPkg->data['total_due_amount_usd']);
                    $updatePmt['payer_id']          = $targetPmt->payer_id;
                    $updatePmt['payer_type']        = $targetPmt->payer_type;
                    $updatePmt['receiver_uid']        = $targetPmt->receiver_uid;
                    $upSertPayIn[] = $updatePmt;
                }

            }else{
                $baseData = [
                    'taxi_fee'               => $validPkg->data['total_taxi_fee'] ?? 0,
                    'delivery_fee'           => $validPkg->data['total_fees'] ?? 0,
                    'amount_due_khr' => $transactionType === TransactionType::TRANSFER_IN->value
                        ? abs($validPkg->data['total_due_amount_khr'])
                        : ($validPkg->data['total_due_amount_khr'] > 0 ? $validPkg->data['total_due_amount_khr'] : 0),

                    'amount_due_usd' => $transactionType === TransactionType::TRANSFER_IN->value
                        ? abs($validPkg->data['total_due_amount_usd'])
                        : ($validPkg->data['total_due_amount_usd'] > 0 ? $validPkg->data['total_due_amount_usd'] : 0),
                    'received_amount_usd'    => $receivedUSD,
                    'received_amount_khr'    => $receivedKHR,
                    'create_uid'             => $user->id,
                    'requested_uid'          => $user->id,
                    'receiver_uid'           => $user->id,
                    'receiptionist_uid'      => $user->id,
                    'failed_with_fee_count'  => $validPkg->data['failed_with_fee_count'] ?? 0,
                    'cod_amount'             => $validPkg->data['total_cod'] ?? 0,
                    'remarks'                => $inputs['remarks'] ?? null,
                    'package_count'          => $validPkg->data['total_package'] ?? count($paidPackageIds),
                    'delivered_package_count'=> $validPkg->data['delivered_package_count'] ?? count($paidPackageIds),
                    'update_uid'             => $user->id,
                    'requested_date'         => now(),
                    // 'payment_datetime'       => now(),
                    'breakdown_notes'        => $breakDownNotes ?? null,
                    'company_id'             => $user->company_id,
                    'branch_id'              => $user->branch_id,
                    'payment_status_id'      => $paymentStatusId,
                    'type'                   => 'payment',
                    'package_ids'            => json_encode($packages),
                ];
                
                if($transactionType === TransactionType::TRANSFER_OUT->value){
                    $insertPayout[] = array_merge($baseData,[
                        'transaction_type'       => $m['transaction_type'],
                        'payee_id'               => $mId,
                        'payee_type'             => $type,
                    ]);
                }else if($transactionType === TransactionType::TRANSFER_IN->value){
                    $insertPayIn[] = array_merge($baseData,[
                        'transaction_type'       => $m['transaction_type'],
                        'payer_id'               => $mId,
                        'payer_type'             => $type,
                    ]);
                }
            }
        }

        if (!empty($fullyPaidInfo)) {
            $users = implode(', ', array_unique(array_column($fullyPaidInfo, "{$type}_name")));
            // Get first unique message (if any)
            $messages = array_values(array_unique(array_filter(array_column($fullyPaidInfo, "message"))));
            $firstMessage = $messages[0] ?? "All fully paid packages detected for {$type}(s): {$type}s.";
            return DataResponse::Duplicated($firstMessage);
        }

        // if (!empty($invalidAmountInfo)) {
        //     $users = implode(', ', array_unique(array_column($invalidAmountInfo, "{$type}_name")));
        //     return DataResponse::Bad(__('messages.info', [
        //         'info'   => "Some packages have invalid received amounts.",
        //         'khInfo' => "មានកញ្ចប់មួយចំនួនមានចំនួនទឹកប្រាក់អវិជ្ជមាន។",
        //         // 'details' => json_encode($invalidAmountInfo)
        //     ]));
        // }

        if (!empty($invalidAmountInfo)) {
            $seen = [];
            $uniqueMessages = [];

            foreach ($invalidAmountInfo as $item) {
                $key = $item["{$type}_name"]; // use user/package name as uniqueness key
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $uniqueMessages[] = $item['message'];
                }
            }

            // Combine messages into a single string
            $allMessages = implode(' | ', $uniqueMessages); // or "\n" for line breaks

            // Prepare localized message
            $message = __('messages.info', [
                'info'   => "Some packages have invalid amounts: {$allMessages}",
                'khInfo' => "មានកញ្ចប់មួយចំនួនមានចំនួនទឹកប្រាក់មិនត្រឹមត្រូវ៖ {$allMessages}",
            ]);

            return DataResponse::ValidateFail($message);
        }

        // Report currency conflicts
        if (!empty($currencyConflictInfo)) {
            $first = $currencyConflictInfo[0];

            $packageId = $first['package_id'] ?? '';
            $user = $first["{$type}_name"] ?? '';
            $currency = $first['currency'] ?? '';
            $row = $first['row'] ?? '';

            return DataResponse::Duplicated("Package {$packageId} already partially paid with {$currency} for {$type}: {$user}.".($row ? " => Row: $row":''));
        }

        // return DataResponse::JsonResult($upSertPayout);
        try {
            DB::beginTransaction();
            if(!empty($insertPayout) || !empty($upSertPayout)){
                $this->batchPayoutUpSert($insertPayout,$upSertPayout,$payingCurrency,$user);
            }
            if(!empty($insertPayIn) || !empty($upSertPayIn)){
                $this->batchPayInUpSert($insertPayIn,$upSertPayIn,$payingCurrency,$user);
            }
            DB::commit();
            return DataResponse::JsonResult($upSertPayout,false,__('messages.saved'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return DataResponse::Error(__('messages.error',[
                'info' => 'Failed to settle',
                'khInfo' => 'Failed to settle'
            ]));
        }
    }

    /**
     * This function use to insert or update batch of payout
     *
     * @param array $insertPayout
     * @param array $upSertPayout
     * @param string $payingCurrency
     * @param object $user
     */
    private function batchPayoutUpSert(array $insertPayout,array $upSertPayout,string $payingCurrency,object $user){
        if(!empty($upSertPayout)){
            DB::table('disbursements')->upsert(
                $upSertPayout,
                ['id'], // unique key to match on
                [
                    'amount_due_usd',
                    'amount_due_khr',
                    'payment_status_id',
                    'payment_datetime',
                    'requested_date',
                    'payee_id',
                    'payee_type',
                    'remarks',
                    'requested_uid',
                    'received_amount_usd',
                    'received_amount_khr',
                    'updated_at',
                    'create_uid',
                    'update_uid',
                    'company_id',
                    'branch_id'
                ]
            );
        }
        if (!empty($insertPayout)) {
            foreach ($insertPayout as $disbData) {
                // Insert into disbursements table (main payment record)
                $disbursement = Disbursement::create($this->filterBulkPaymentColumns($disbData,$user,$disbData['transaction_type']));
                self::transactionCodeGenerator('transaction_sequences','payment','disbursements','trx_code',$user->branch_id,$user->company_id,$disbursement->id);
                // Attach each package to this disbursement
                $packageIds = json_decode($disbData['package_ids'], true);
                $insertDisbursementPkgs = [];
                foreach ($packageIds as $pkgId) {
                    $insertDisbursementPkgs[] = [
                        'disbursement_id' => $disbursement->id,
                        'package_id' => $pkgId,
                        'type' => 'payment',
                        'payee_type' => $disbData['payee_type'],
                        'is_deleted' => false,
                    ];
                }
                if(!empty($insertDisbursementPkgs)){
                    DisbursementPackage::insert($insertDisbursementPkgs);
                }

                $insertDisbursementDetails = [];
                if (!empty($disbData['payments'])) {
                    foreach ($disbData['payments'] as $payment) {
                        $insertDisbursementDetails[] = [
                            'disbursement_id' => $disbursement->id,
                            'method' => $payment['method'] ?? 'cash',
                            'amount' => $payment['amount'],
                            'original_amount' => $payment['original_amount'] ?? $payment['amount'],
                            'currency_code' => $payment['currency_code'] ?? $payingCurrency,
                        ];
                    }
                }
                if(!empty($insertDisbursementDetails)){
                    DisbursementDetails::insert($insertDisbursementDetails);
                }
            }
        }
    }


    static function transactionCodeGenerator($tbl_code_control,$type,$target_tbl,$target_col,$branch_id,$company_id,$newID,$prefix='TRX', $len = 5){
        if (!$len) $len = 5;
        if (!$newID) return DataResponse::ValidateFail('Identity should be input');
        $year = date('Y');
        $qRow = DB::table($tbl_code_control . " as c")->where('c.branch_id', $branch_id)
        ->where('prefix',$prefix)
        ->where('c.company_id',$company_id)
        ->where('payment_type',$type)
        ->selectRaw("c.last_idx,c.prefix,c.issue_year");
        $qRow->where('c.issue_year',$year);
        $row = $qRow->first();

        $next_num = 0;
        if ($row){
            $next_num = $row->last_idx;
            if($row->issue_year == $year) $year = $row->issue_year;
        }
        $next_num++;
        //example ref number => 20240212-B001-00003
        $new_code = date('Ymdhis') .'-B'. Helper::formatNumber($branch_id,3).'-'. Helper::formatNumber($next_num, $len);//.'-'.substr(Str::uuid()->toString(), 0, 5);
        if($prefix) $new_code = $prefix.'-'.$new_code;
        $x = DB::table($target_tbl)->where('id', $newID)->update([$target_col => $new_code]);
        if ($x || $x === 1) {
            $Qupdated = DB::table($tbl_code_control)->where('branch_id', $branch_id)
            ->where('prefix',$prefix)
            ->where('issue_year', $year)
            ->where('company_id',$company_id);
            $updated = $Qupdated->update(['last_idx' => $next_num]);
            $insert_arr = [
                'branch_id' => $branch_id,
                'issue_year' => $year,
                'last_idx' => $next_num,
                'company_id' => $company_id,
                'prefix'=>$prefix,
                'payment_type'=>$type,
            ];
            if (!$updated) DB::table($tbl_code_control)->insert($insert_arr);
            // if ($onSuccess) $onSuccess();
            return (object)['status_code' => 200, 'status' => 'OK', 'code' => $new_code];
        }
    }

    /**
     * Keep only columns allowed for Disbursement::create().
     * (Matches the keys you already pass in your current create() call.)
     *
     * @param array $data
     * @return array
     */
    private function filterBulkPaymentColumns(array $data,object $user,$tranType='out'): array
    {
        $allowed = [
            'amount_due_usd', 'delivery_fee', 'amount_due_khr', 'received_amount_khr', 'received_amount_usd',
            'amount', 'payable_amount', 'taxi_fee', 'cod_amount', 'package_count', 'delivered_package_count',
            'pickup_package_count', 'approved', 'is_settled', 'breakdown_notes', 'settled_uid', 'exchange_rate',
            'requested_uid','approved_datetime', 'settled_datetime', 'payment_datetime', 'pickup_rate', 'delivery_rate',
            'type','receiptionist_uid', 'failed_with_fee_count', 'trx_code', 'paid_amount',
            // 'fast_delivery_rate',
            'payment_status_id','fast_pickup_rate','requested_date',
        ];
        if($tranType === TransactionType::TRANSFER_OUT->value){
            $allowed[] = 'payee_id';
            $allowed[] = 'payee_type';
        }

        if($tranType === TransactionType::TRANSFER_IN->value){
            $allowed[] = 'payer_id';
            $allowed[] = 'payer_type';
            $allowed[] = 'receiver_uid';
        }

        $out = [
            'create_uid' => $user->id,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }
        return $out;
    }

    /**
     * This function use to insert or update batch of payin
     *
     * @param array $insertPayin
     * @param array $upSertPayin
     * @param string $payingCurrency
     * @param object $user
     */
    private function batchPayInUpSert(array $insertPayin,array $upSertPayin,string $payingCurrency,object $user){
        if(!empty($upSertPayin)){
            $upSertPayin = array_map(function($item) {
                // Ensure the item is an array
                $item = is_object($item) ? (array) $item : $item;

                // Remove the 'type' key if it exists
                unset($item['type']);

                return $item;
            }, $upSertPayin);

            DB::table('payments')->upsert(
                $upSertPayin,
                ['id'], // unique key to match on
                [
                    'payment_status_id',
                    'payment_datetime',
                    'payer_id',
                    'payer_type',
                    'remarks',
                    'requested_uid',
                    'amount_due_usd',
                    'amount_due_khr',
                    'requested_date',
                    'received_amount_usd',
                    'received_amount_khr',
                    'updated_at',
                    'create_uid',
                    'update_uid',
                    'company_id',
                    'branch_id'
                ]
            );
        }
        if (!empty($insertPayin)) {
            foreach ($insertPayin as $disbData) {
                unset($insertPayin['type']);
                // Insert into disbursements table (main payment record)
                $insertData=$this->filterBulkPaymentColumns($disbData,$user,$disbData['transaction_type']);
                $payment = Payment::create($insertData);
                self::transactionCodeGenerator('transaction_sequences','payment','payments','trx_code',$user->branch_id,$user->company_id,$payment->id);
                // Attach each package to this payment
                $packageIds = json_decode($disbData['package_ids'], true);
                $insertPayInPkgs = [];
                foreach ($packageIds as $pkgId) {
                    $insertPayInPkgs[] = [
                        'payment_id' => $payment->id,
                        'package_id' => $pkgId,
                        'type' => 'payment',
                        'payer_type' => $disbData['payer_type'],
                        'is_deleted' => false,
                    ];
                }
                if(!empty($insertPayInPkgs)){
                    PaymentPackage::insert($insertPayInPkgs);
                }

                $insertPayinDetails = [];
                if (!empty($disbData['payments'])) {
                    foreach ($disbData['payments'] as $payment) {
                        $insertPayinDetails[] = [
                            'disbursement_id' => $payment->id,
                            'method' => $payment['method'] ?? 'cash',
                            'amount' => $payment['amount'],
                            'original_amount' => $payment['original_amount'] ?? $payment['amount'],
                            'currency_code' => $payment['currency_code'] ?? $payingCurrency,
                        ];
                    }
                }
                if(!empty($insertPayinDetails)){
                    PaymentDetail::insert($insertPayinDetails);
                }
            }
        }
    }

    private function preparePayIn(array $packageIds,string $type, string $payingCurrency, object $validPkg, int $mId, object $payInPkgs,?int $rowIdx=null)
    {
        $result = [
            'fullyPaidInfo'       => [],
            'currencyConflictInfo'=> [],
            'invalidAmountInfo'   => [],
            'allUSDReceived'      => true,
            'allKHRReceived'      => true,
            'target' => null,
            'targetId' => null,
            'receivedUSD' => 0,
            'receivedKHR' => 0
        ];

        $payInPkg = $payInPkgs[$mId] ?? null;
        $validUsdAmt = abs($validPkg->data['total_due_amount_usd']); // abs for not sign when insert
        $validKhrAmt = abs($validPkg->data['total_due_amount_khr']); // abs for not sign when insert

        if ($payInPkg && $payInPkg->payment && in_array($payInPkg->package_id,$packageIds)) {
            $payment = $payInPkg->payment;
            // $usdDue = $payment->amount_due_usd - $payment->received_amount_usd;
            // $khrDue = $payment->amount_due_khr - $payment->received_amount_khr;
            $amountUsd = $payment->amount_due_usd ?? 0;
            $amountKhr = $payment->amount_due_khr ?? 0;
            $usdDue = $payment->payment_status_id == PaymentStatus::DECLINED->value ? $validUsdAmt : ($amountUsd - $payment->received_amount_usd);
            $khrDue = $payment->payment_status_id == PaymentStatus::DECLINED->value ? $validKhrAmt : ($amountKhr - $payment->received_amount_khr);

            $result['target'] = $payment;
            $result['targetId'] = $payment->id;

            // Already fully paid
            if (Helper::floatEquals($usdDue, 0) && Helper::floatEquals($khrDue, 0) && $payment->payment_status_id == PaymentStatus::REQUESTED->value) {
                $result['fullyPaidInfo'][] = [
                    'package_id'    => '',
                    "{$type}_id"    => $mId,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] ?? $mId,
                ];
                if ($payingCurrency === 'USD') $result['allUSDReceived'] = false;
                if ($payingCurrency === 'KHR') $result['allKHRReceived'] = false;
                return $result; // stop here
            }

            // Handle USD
            if ($payingCurrency === 'USD') {
                $result['receivedUSD'] = $validUsdAmt;
                if (!Helper::floatEquals($usdDue, $validUsdAmt)) {
                    $result['allUSDReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'USD',
                        'message'       => Helper::floatEquals($usdDue, 0)
                            ? 'This package is already fully settled in USD.'
                            : 'This package has partial USD payment already, cannot pay again in USD.',
                    ];
                } else {
                    if (Helper::floatEquals($khrDue, $validKhrAmt) || Helper::floatEquals($validKhrAmt, 0)) {
                        $result['allKHRReceived'] = true;
                    }
                }
            }

            // Handle KHR
            if ($payingCurrency === 'KHR') {
                $result['receivedKHR'] = $validKhrAmt;
                if (!Helper::floatEquals($khrDue, $validKhrAmt)) {
                    $result['allKHRReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'KHR',
                        'message'       => Helper::floatEquals($khrDue, 0)
                            ? 'This package is already fully settled in KHR.'
                            : 'This package has partial KHR payment already, cannot pay again in KHR.',
                    ];
                    return $result;
                } else {
                    if (Helper::floatEquals($usdDue, $validUsdAmt) || Helper::floatEquals($validUsdAmt, 0)) {
                        $result['allUSDReceived'] = true;
                    }
                }
            }

        } else {
            $validAmt = match($payingCurrency) {
                'USD' => $validUsdAmt,
                'KHR' => $validKhrAmt,
                default => null
            };

            // Skip if both are 0 or less
            $bothZeroOrLess = ($validUsdAmt <= 0 && $validKhrAmt <= 0);

            // Only show error if selected currency is invalid AND not both zero
            if ($validAmt !== null && $validAmt <= 0 && !$bothZeroOrLess) {
                $merchantName = $validPkg->data['merchant_name'] ?? $validPkg->data['merchant']->name ?? 'Unknown Merchant';

                $result['invalidAmountInfo'][] = [
                    'package_id'    => '',
                    'row_number'    => $rowIdx,
                    'merchant_name' => $merchantName,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                    'currency'      => $payingCurrency,
                    'message'       => "Row {$rowIdx}: {$merchantName} - package has no outstanding amount to settle."
                ];
            }
            // No payment yet → validate amounts
            // if (Helper::floatEquals($validKhrAmt, 0) && Helper::floatEquals($validUsdAmt, 0)) {
            //     $result['invalidAmountInfo'][] = [
            //         'package_id'    => '',
            //         "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
            //         'currency'      => $payingCurrency,
            //         'message'       => "Invalid amount: there are no valid amounts to be settled."
            //     ];
            // }

            if ($payingCurrency === 'USD') {
                $result['receivedUSD'] = $validUsdAmt;
                if ($validUsdAmt < 0) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                        'currency'      => 'USD',
                        'message'       => "Invalid amount: received USD ({$validUsdAmt}) cannot be positive."
                    ];
                    return $result;
                }
                if ($validKhrAmt > 0) $result['allKHRReceived'] = false;
            }

            if ($payingCurrency === 'KHR') {
                $result['receivedKHR'] = $validKhrAmt;
                if ($validKhrAmt < 0) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                        'currency'      => 'KHR',
                        'message' => "Invalid amount: received KHR ({$validKhrAmt}) cannot be positive. Please provide the amount in USD instead."
                    ];
                    return $result;
                }
                if ($validUsdAmt > 0) $result['allUSDReceived'] = false;
            }
        }

        return $result;
    }

    private function preparePayout(array $packageIds,string $type, string $payingCurrency, object $validPkg, int $mId, object $disbursementPkg,?int $rowIdx=0): array
    {
        $result = [
            'fullyPaidInfo'       => [],
            'currencyConflictInfo'=> [],
            'invalidAmountInfo'   => [],
            'allUSDReceived'      => true,
            'allKHRReceived'      => true,
            'target' => null,
            'targetId' => null,
            'receivedUSD' => 0,
            'receivedKHR' => 0
        ];

        $disbPkg = $disbursementPkg[$mId] ?? null;
        $validUsdAmt = $validPkg->data['total_due_amount_usd'] ?? 0;
        $validKhrAmt = $validPkg->data['total_due_amount_khr'] ?? 0;

        if ($disbPkg && $disbPkg->disbursement && in_array($disbPkg->package_id,$packageIds)) {
            $disbursement = $disbPkg->disbursement;
            if(!in_array($disbursement->payment_status_id,[PaymentStatus::PARTIAL->value,PaymentStatus::DECLINED->value])){
                $result['fullyPaidInfo'][] = [
                    'package_id'    => '',
                    "{$type}_id"    => $mId,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] ?? $mId,
                    'message' => 'Merchant:'.$validPkg->data["{$type}_name"].' Packages have already been '.PaymentStatus::tryFrom($disbursement->payment_status_id)->label()
                ];
            }
            $amountUsd = $disbursement->amount_due_usd ?? 0;
            $amountKhr = $disbursement->amount_due_khr ?? 0;
            $usdDue = $disbursement->payment_status_id == PaymentStatus::DECLINED->value ? $validUsdAmt : ($amountUsd - $disbursement->received_amount_usd);
            $khrDue = $disbursement->payment_status_id == PaymentStatus::DECLINED->value ? $validKhrAmt : ($amountKhr - $disbursement->received_amount_khr);
            // Currency conflict checks
            if((($payingCurrency == 'USD') && ($usdDue <=0)) || (($payingCurrency == 'KHR') && ($khrDue <=0))){
                $result['invalidAmountInfo'][] = [
                    'package_id'    => '',
                    "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                    'currency'      => 'USD',
                    'message'       => "Invalid amount: there are no valid amounts to be settled."
                ];
            }

            if ($payingCurrency === 'USD' && $amountUsd > 0 && Helper::floatEquals($usdDue, 0)) {
                $result['currencyConflictInfo'][] = [
                    'package_id'    => '',
                    "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                    'currency'      => 'USD',
                    'row'           => $rowIdx,
                    'message'       => 'This package is already fully settled in USD.'
                ];
            }

            if ($payingCurrency === 'KHR' && $amountKhr > 0 && Helper::floatEquals($khrDue, 0)) {
                $result['currencyConflictInfo'][] = [
                    'package_id'    => '',
                    "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                    'currency'      => 'KHR',
                    'row'           => $rowIdx,
                    'message'       => 'This package is already fully settled in KHR.'
                ];
            }

            
            $result['target'] = $disbursement;
            $result['targetId'] = $disbursement->id;

            // Fully paid check
            if (Helper::floatEquals($usdDue, 0) && Helper::floatEquals($khrDue, 0) && $disbursement->payment_status_id == PaymentStatus::REQUESTED->value) {
                $result['fullyPaidInfo'][] = [
                    'package_id'    => '',
                    "{$type}_id"    => $mId,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] ?? $mId,
                ];

                if ($payingCurrency === 'USD') $result['allUSDReceived'] = false;
                if ($payingCurrency === 'KHR') $result['allKHRReceived'] = false;
            }

            // Handle USD
            if ($payingCurrency === 'USD') {
                $result['receivedUSD'] = $validUsdAmt;
                if (!Helper::floatEquals($usdDue, $validUsdAmt) && ($disbursement->payment_status_id != PaymentStatus::DECLINED->value)) {
                    $result['allUSDReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                        'currency'      => 'USD',
                        'row'           => $rowIdx,
                        'message'       => 'This package has partial USD payment already, cannot pay again in USD.',
                    ];
                } else {
                    if (Helper::floatEquals($khrDue, $validKhrAmt)) {
                        $result['allKHRReceived'] = true;
                    }
                }
            } else {
                if ($validKhrAmt > 0 && $validUsdAmt > 0) $result['allKHRReceived'] = false;
            }

            // Handle KHR
            if ($payingCurrency === 'KHR') {
                $result['receivedKHR'] = $validKhrAmt;
                if (!Helper::floatEquals($khrDue, $validKhrAmt) && ($disbursement->payment_status_id != PaymentStatus::DECLINED->value)) {
                    $result['allKHRReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                        'currency'      => 'KHR',
                        'row'           => $rowIdx,
                        'message'       => 'This package has partial KHR payment already, cannot pay again in KHR.',
                    ];
                } else {
                    if (Helper::floatEquals($usdDue, $validUsdAmt)) {
                        $result['allUSDReceived'] = true;
                    }
                }
            } else {
                if ($validUsdAmt > 0 && !Helper::floatEquals($usdDue, $validUsdAmt)) $result['allUSDReceived'] = false;
            }

        } else {
            $validAmt = match($payingCurrency) {
                'USD' => $validUsdAmt,
                'KHR' => $validKhrAmt,
                default => null
            };

            if ($validAmt !== null && $validAmt <= 0) {
                $merchantName = $validPkg->data['merchant_name'] ?? $validPkg->data['merchant']->name ?? 'Unknown Merchant';

                $result['invalidAmountInfo'][] = [
                    'package_id'    => '',
                    'row_number'    => $rowIdx,
                    'merchant_name' => $merchantName,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                    'currency'      => $payingCurrency,
                    'message'       => "Row {$rowIdx}: {$merchantName} - package has no outstanding amount to settle."
                ];
            }
            // No disbursement yet → validate amounts

            if (Helper::floatEquals($validKhrAmt, 0) && Helper::floatEquals($validUsdAmt, 0)) {
                $result['invalidAmountInfo'][] = [
                    'package_id'    => '',
                    "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                    'currency'      => 'USD',
                    'message'       => "Invalid amount: there are no valid amounts to be settled."
                ];
            }

            if ($payingCurrency === 'USD') {
                $result['receivedUSD'] = $validUsdAmt;
                if (Helper::floatEquals($validUsdAmt, 0) && Helper::floatEquals($validKhrAmt, 0)) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                        'currency'      => 'USD',
                        'message'       => "Invalid amount: received USD ({$validUsdAmt}) cannot be negative."
                    ];
                }
                if ($validKhrAmt > 0 && $validUsdAmt > 0) $result['allKHRReceived'] = false;
            }

            if ($payingCurrency === 'KHR') {
                $result['receivedKHR'] = $validKhrAmt;
                if (Helper::floatEquals($validKhrAmt, 0) && Helper::floatEquals($validUsdAmt, 0)) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => '',
                        "{$type}_name" => $validPkg->data["{$type}_name"] . '(' . ($validPkg->data["{$type}_code"] ?? 'No Code') . ')',
                        'currency'      => 'KHR',
                        'message'       => "Invalid amount: received KHR ({$validKhrAmt}) cannot be negative."
                    ];
                }
                if ($validUsdAmt > 0) $result['allUSDReceived'] = false;
            }
        }

        return $result;
    }

    public function validBulkPackagesV1($packages,$packageIds,$driverOrMerchantId,$type,$isReduceFee=true){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $obj = (object)[
            'total_packages' => 0,
            'total_cod' => 0 ,
            'total_delivery_fee' => 0,
            'delivered_package_count' => 0,
            'failed_with_fee_count' => 0,
            'total_taxi_fee' => 0,
            'total_fees' => 0,
            'driver_total' => 0,
            'merchant_total' => 0,
            'total_due_amount_khr' => 0,
            'total_due_amount_usd' => 0,
            'total_package_price'=>0,
            'total_amount' => 0,
            'merchant_name' => '',
            'merchant_code' => '',
        ];

        // $paidPackageIds = DB::table('payment_packages')
        //     ->whereIn('package_id', $packageIds)
        //     ->where('payer_type', $type)  // 'driver' or 'merchant'
        //     ->where('is_deleted', false)
        //     ->pluck('package_id')
        //     ->toArray();

        // $disbursedPackageIds = DB::table('disbursement_packages')
        //     ->whereIn('package_id', $packageIds)
        //     // ->whereHas('disburement')
        //     ->where('payee_type', $type)  // 'driver' or 'merchant'
        //     ->where('is_deleted', false)
        //     ->where('type', 'payment')
        //     ->pluck('package_id')
        //     ->toArray();

        // Combine to one array of package IDs that are paid or disbursed
        // $paidOrDisbursedPackageIds = array_unique(array_merge($paidPackageIds, $disbursedPackageIds));

        $totalDriverCodUsd = 0;
        $totalDriverCodKhr = 0;
        $defaultExchangeRate = GeneralSettingService::getLatestXRate()->sell_rate;
        foreach($packageIds as $index=>$id){
            $package = $packages->get($id);
            if (!$package) {
                return DataResponse::ValidateFail(__('messages.info', [
                    'info' => 'Invalid package on row (' . ($index + 1) . ')',
                    'khInfo' => 'កញ្ចប់មិនត្រឹមត្រូវនៅជួរលេខ (' . ($index + 1) . ')',
                ]));
            }
            // if (in_array($id, $paidOrDisbursedPackageIds)) {
            //     return DataResponse::ValidateFail(__('messages.error', [
            //         'info' => 'Check list might include package that has been paid',
            //         'khInfo' => 'សូមពិនិត្យមើលថាតើបញ្ជីនេះមានកញ្ចប់ដែលបានបង់ប្រាក់រួចហើយ',
            //     ]));
            // }
            if($package->status_id == 9) $obj->delivered_package_count += 1;
            if($package->status_id == 19) $obj->failed_with_fee_count +=1;
            // $obj->total_delivery_fee += ($package->cod ? $package->delivery_fee : 0) + $package->extra_charge + $package->additional_fee;
            if($type == 'merchant' && $package->payer == 'sender') $obj->total_delivery_fee += $package->extra_charge + $package->delivery_fee;
            else if($type == 'driver' && $package->payer == 'receiver') $obj->total_delivery_fee += $package->extra_charge + $package->delivery_fee;
            // $calPackage = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
            // $totalPackages += 1;
            $obj->total_packages +=1;
            $obj->total_taxi_fee += $package->taxi_fee;
            $obj->driver_total += $package->driver_total;
            $obj->merchant_total += $package->merchant_total;
            $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->extra_charge,$package->payer);
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->extra_charge,$package->payer);
                    // $rowTotal = $package->payer == 'sender' ? $package->delivery_fee + $package->extra_charge : 0;
                }
                // else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            // $obj->total_due_amount_khr += $rowTotal['total_khr'];
            // $obj->total_due_amount_usd += $rowTotal['total_usd'];
            // Log::info($rowTotal['fees']);
            $obj->total_fees += $rowTotal['fees'];
            if($package->cod) $obj->total_cod += $package->price;
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
            $obj->merchant_name = $package->merchant?->username;
            $obj->merchant_code = $package->merchant?->code;
            // $senderFees = $package->payer == 'sender' ? $package->delivery_fee + $package->extra_charge : 0;
            $fees = $package->delivery_fee + $package->extra_charge;
            $exchangeRate = $package->exchange_rate > 0 ? $package->exchange_rate : $defaultExchangeRate;
            if($type == 'merchant'){
                if($package->status_id !== 19){
                    if($package->payer == 'receiver' && (($package->driver_cod_usd > $package->price) || ($package->driver_cod_khr > $package->price_khr))){
                        // $obj->total_fees = $package->extra_charge + $package->delivery_fee;
                    }
                    if (
                        $package->status_id === 9) {
                        // $package->driver_cod_usd = 0;
                        // $package->driver_cod_khr = 0;
                        if($package->payer === 'receiver'){
                            if($package->driver_cod_usd > $package->price){
                                $package->driver_cod_usd -= ($package->driver_cod_usd - $package->price);
                            }
                            if($package->driver_cod_khr > $package->price_khr){
                                if($package->price_khr > 0){
                                    $package->driver_cod_khr -= ($package->driver_cod_khr - $package->price_khr);
                                }
                                else{
                                    $package->driver_cod_khr -= ($fees * $exchangeRate);
                                }
                            }
                        }
                        // else{
                        //     $rowFees = $rowTotal['fees'];
                        //     $rowFeeKhr = $rowTotal['fees_khr'];
                        //     if($package->driver_cod_usd > 0 && ($package->driver_cod_usd < $rowFees)){
                        //         $package->driver_cod_usd -= $rowFees;
                        //     }
                        //     else if($package->driver_cod_khr > 0 && ($package->driver_cod_khr < $rowFeeKhr)){
                        //         $package->driver_cod_usd -= ($rowFeeKhr - $package->driver_cod_khr) / GeneralSettingService::$feeRate;
                        //     }
                        // }
                    }

                    $totalDriverCodUsd += $package->driver_cod_usd;
                    $totalDriverCodKhr += $package->driver_cod_khr;
                }
                if($package->status_id == 19 && $package->payer == 'receiver'){
                    // $totalDriverCodUsd += $package->driver_cod_usd;
                    // $totalDriverCodKhr += $package->driver_cod_khr;
                    if($totalDriverCodUsd > 0 || $totalDriverCodKhr > 0){
                        // $obj->total_fees = 0;
                    }
                }
                
            }else{
                $totalDriverCodUsd += $package->driver_cod_usd;
                $totalDriverCodKhr += $package->driver_cod_khr;
            }
        }
        // if($paymentType == 'disbursement') $obj->total_due_amount = abs($obj->total_due_amount);
        // Log::error(json_encode($obj));
        if($isReduceFee) Helper::deductAmountBase($totalDriverCodUsd,$totalDriverCodKhr,$obj->total_fees + $obj->total_taxi_fee);
        // if($obj->total_delivery_fee);
        return DataResponse::JsonResult([
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => round($obj->total_delivery_fee,2),
            'total_package' => $obj->total_packages,
            'driver_total' => $obj->driver_total,
            'total_taxi_fee' => $obj->total_taxi_fee,
            'merchant_total' => $obj->merchant_total,
            'total_package_price' => $obj->total_package_price,
            'failed_with_fee_count' => $obj->failed_with_fee_count,
            'total_cod' => $obj->total_cod,
            'total_due_amount_khr' => $totalDriverCodKhr,
            'total_due_amount_usd' => $totalDriverCodUsd,
            'total_amount' => $obj->total_amount,
            'delivered_package_count' => $obj->delivered_package_count,
            'merchant_name' => $obj->merchant_name,
            'merchant_code' => $obj->merchant_code,
            'total_fees' => $obj->total_fees
        ]);
    }

    public static function getPackageTotalV1(
        $type,
        $driverCODUsd,
        $driverCODKhr,
        $baseFee,
        $taxiFee,
        $otherFee,
        $payer,
        $statusId = null,
        $exchangeRateBase = 4000,
        
    ) {
        $fees = $baseFee + $otherFee + $taxiFee;

        $totalUsd = max(0, $driverCODUsd);
        $totalKhr = max(0, $driverCODKhr);
        // helper closure to deduct in USD first, then KHR

        if ($type === 'driver') {
            // 1. Always deduct taxi fee
            // 2. Deduct fees if payer is receiver
            if ($payer === 'receiver' && ($fees > 0 || $taxiFee > 0)) {
                if($statusId == 19){
                    $fees = 0;
                    $taxiFee = 0;
                }
                if($driverCODUsd == 0){
                    if($driverCODKhr > 0){
                        $fees = $fees * $exchangeRateBase;
                        $exchangeRateBase = 0;
                    }
                }
                Helper::deductAmountBase($totalUsd,$totalKhr,$fees,$exchangeRateBase);
            }else{
                $fees = 0;
                Helper::deductAmountBase($totalUsd,$totalKhr,$fees,$exchangeRateBase);
            }
        }
        if($type === 'merchant'){
            if ($payer === 'sender' && ($fees > 0 || $taxiFee > 0)) {
                Helper::deductAmountBase($totalUsd,$totalKhr,$fees + $taxiFee,$exchangeRateBase);
            }else{
             $fees = 0;
            }
        }
        return [
            'total_usd'  => Helper::getNumber($totalUsd, 2),
            'total_khr'  => Helper::getNumber($totalKhr, 2),
            'fees'       => Helper::getNumber($fees, 2),
            'fees_khr'    => Helper::getNumber($fees * $exchangeRateBase, 2),
            'taxi_fee'   => Helper::getNumber($taxiFee, 2),
            'extra_charge'  => Helper::getNumber($otherFee, 2),
        ];
    }

    private function validType($type){
        $validType = ['driver','merchant'];
        if(!in_array($type,$validType)) return DataResponse::ValidateFail('Invalid type');
        return DataResponse::JsonResult(null);
    }

    private function getPayOutPackagesKeyByMerchantId(array $packageIds,string $type):object{
        $packages = DisbursementPackage::where('is_deleted', false)
            ->whereIn('package_id', $packageIds)
            ->where('payee_type', $type)
            // ->whereHas('disbursement', function ($q) use($type) {
            //     $q->where('is_deleted', false)
            // })
            ->with([
                'disbursement' => function ($q) use($type) {
                    $q->select(
                        'id',
                        'amount_due_usd',
                        'payee_type',
                        'payee_id',
                        'delivery_fee',
                        'requested_date',
                        'amount_due_khr',
                        'received_amount_khr',
                        'received_amount_usd',
                        'payment_status_id',
                        'amount',
                        'payable_amount',
                        'taxi_fee',
                        'cod_amount',
                        'package_count',
                        'delivered_package_count',
                        'pickup_package_count',
                        'approved',
                        'is_settled',
                        'remarks',
                        'breakdown_notes',
                        'settled_uid',
                        'exchange_rate',
                        'approved_datetime',
                        'settled_datetime',
                        'payment_datetime',
                        'pickup_rate',
                        'requested_date',
                        'delivery_rate',
                        'type',
                        'receiptionist_uid',
                        'failed_with_fee_count',
                        'trx_code',
                        'paid_amount',
                        'create_uid',
                        'branch_id',
                        'company_id'
                    )->where('is_deleted', false)
                    ->where('payee_type',$type);
                    // ->whereIn('payment_status_id',[
                    //     PaymentStatus::DECLINED->value,
                    //     PaymentStatus::PARTIAL->value,
                    // ]);
                }
            ])
            ->get()
            // ->keyBy('package_id');
             ->groupBy(fn($p) => $p->disbursement->payee_id ?? null)
            ->map(fn($items) => $items->first()) // in case multiple packages under same merchant
            ->filter() // remove null keys
            ->keyBy(fn($item) => $item->disbursement?->payee_id);

        return (object) $packages;
    }

    private function getPayInPackagesKeyByMerchantId(array $packageIds,string $type):object{
        $packages = PaymentPackage::where('is_deleted', false)
            ->whereIn('package_id', $packageIds)
            ->where('payer_type', $type)
            // ->whereHas('payment', function ($q) use($type){
            //     $q->where('is_deleted', false)
            //     ->where('payer_type',$type);
            // })
            ->with([
                'payment' => function ($q) use($type) {
                    $q->select(
                        'id',
                        'amount_due_usd',
                        'payer_type',
                        'payer_id',
                        'delivery_fee',
                        'amount_due_khr',
                        'received_amount_khr',
                        'received_amount_usd',
                        'payment_status_id',
                        'amount',
                        'payable_amount',
                        'taxi_fee',
                        'cod_amount',
                        'package_count',
                        'delivered_package_count',
                        'approved',
                        'is_settled',
                        'remarks',
                        'breakdown_notes',
                        'settled_uid',
                        'exchange_rate',
                        'approved_datetime',
                        'settled_datetime',
                        'payment_datetime',
                        // 'pickup_rate',
                        'requested_date',
                        // 'delivery_rate',
                        // 'type',
                        'receiver_uid',
                        'failed_with_fee_count',
                        'trx_code',
                        'paid_amount',
                        // 'fast_delivery_rate',
                        // 'fast_pickup_rate',
                        'create_uid',
                        'branch_id',
                        'company_id'
                    )->where('is_deleted', false)
                    ->where('payer_type',$type)
                    ->whereIn('payment_status_id',[
                        PaymentStatus::DECLINED->value,
                        PaymentStatus::PARTIAL->value
                    ]);
                }
            ])
            ->get()
            // ->keyBy('package_id');

            ->groupBy(fn($p) => $p->payment->payer_id ?? null)
            ->map(fn($items) => $items->first()) // in case multiple packages under same merchant
            ->filter() // remove null keys
            ->keyBy(fn($item) => $item?->payment?->payer_id);
        return (object) $packages;
    }

    private function getBulkPaymentPackages(array $packageIds,string $type,array $userIds){
        return Package::where('is_deleted',false)
        ->whereIn('status_id',[19,9])
        // ->with(['merchant:id,username,code'])
        ->whereIn("{$type}_id",$userIds)
        ->whereIn('id',$packageIds)
        ->get();
    }

    private function disburesementBulkV1Validator(array $data,$type){
        return validator($data, [
            'currency' => 'required|in:KHR,USD',
            $type.'s' => ['required', 'array'],
            $type.'s.*.id' => ['required', 'integer', 'exists:users,id'],
            $type.'s.*.transaction_type' => 'required|in:in,out',
            $type.'s.*.packages' => ['required', 'array', 'min:1'],
            $type.'s.*.packages.*' => ['integer', 'exists:packages,id']
        ]);
    }

    public function getDeliveryPackages(array $filters,object $user):object{
        $merchantId = $filters['merchant_id'] ?? null;
        $pmtStatusId = $filters['payment_status_id'] ?? null;
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;
        $search = $filters['search'] ?? null;
        $qP = Package::query()
            ->from('packages as p')
            ->where('p.is_deleted',0)
            ->join('users as d','d.id','p.driver_id')
            ->join('tracking_statuses as ts','ts.id','p.status_id')
            ->join('users as m','m.id','p.merchant_id')
            ->whereIn('p.status_id',[9,19])
            ->select([
                'p.extra_charge','p.additional_fee','p.remarks','p.cod','p.price','p.price_khr','d.phone as driver_phone','p.taxi_fee','p.payer','p.delivery_fee','p.assign_driver_datetime',
                'p.merchant_total','m.username as merchant_name','m.phone as merchant_phone','d.username as driver_name','p.status_id','p.id as package_id','d.id as driver_id',
                'p.qr_code','ts.name as status_code','p.delivered_datetime','p.failed_datetime','p.zone_code','p.receiver_phone','p.delivery_type','p.zone_name',
                'p.receiver_address','p.driver_cod_usd','p.driver_cod_khr'
            ]);
            
        $qP->with(['payment' => function ($query) {
            // Apply the dynamic payer_type condition
            $query->where('payer_type', 'merchant')
            ->where('is_deleted',0);
        }])
        ->with(['disbursement' => function ($query) {
            // Apply the dynamic payer_type condition
            $query->where('payee_type', 'merchant')
            ->where('is_deleted',0);
        }]);


        if($search){
            $qP->where('p.qr_code',$search);
        }else{
            if($merchantId){
                $qP->where('p.merchant_id',$merchantId);
            }
            
            if($startDate && $endDate){
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qP->where(function ($q) use ($startDate, $endDate) {
                    $startDateTime = $startDate . ' 00:00:00';
                    $endDateTime = $endDate . ' 23:59:59';

                    // Check for status_id = 9, delivered_datetime should be within the date range
                    $q->where(function ($q) use ($startDateTime, $endDateTime) {
                        $q->where('p.status_id', 9)
                        ->whereRaw('p.delivered_datetime >= ? AND p.delivered_datetime <= ?', [$startDateTime, $endDateTime]);
                    })
                    // Check for status_id = 19, failed_datetime should be within the date range
                    ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                        $q->where('p.status_id', 19)
                        ->whereRaw('p.failed_datetime >= ? AND p.failed_datetime <= ?', [$startDateTime, $endDateTime]);
                    });
                });
            }
        }

        if ($pmtStatusId == 1) { // Unpaid
            $qP->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('payment_packages as pp')
                    ->join('payments as pmt', 'pmt.id', '=', 'pp.payment_id')
                    ->whereColumn('pp.package_id', 'p.id')
                    ->where('pp.payer_type', 'merchant')
                    ->where('pp.is_deleted', false)
                    ->where('pmt.payment_status_id', '=', 8);
            })->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('disbursement_packages as dp')
                    ->join('disbursements as d', 'd.id', '=', 'dp.disbursement_id')
                    ->whereColumn('dp.package_id', 'p.id')
                    ->where('dp.payee_type', 'merchant')
                    ->where('dp.type', 'payment')
                    ->where('dp.is_deleted', false)
                    ->where('d.payment_status_id', '=', 8);
            });
        } elseif ($pmtStatusId == 2) { // Paid
            $qP->where(function($q) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payment_packages as pp')
                        ->join('payments as pmt', 'pmt.id', '=', 'pp.payment_id')
                        ->whereColumn('pp.package_id', 'p.id')
                        ->where('pp.payer_type', 'merchant')
                        ->where('pp.is_deleted', false)
                        ->where('pmt.payment_status_id', '=', 8);
                })->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('disbursement_packages as dp')
                        ->join('disbursements as d', 'd.id', '=', 'dp.disbursement_id')
                        ->whereColumn('dp.package_id', 'p.id')
                        ->where('dp.payee_type', 'merchant')
                        ->where('dp.type', 'payment')
                        ->where('dp.is_deleted', false)
                        ->where('d.payment_status_id', '=', 8);
                });
            });
        }

        $statusKey = 'merchant_payment_status';
        // $clbMapper = null;
        // $exchangeRate = GeneralSettingService::getLatestXRate()->sell_rate;
        $clbMapper = function($package) use($statusKey){
            $cod = $package->cod;
            $statusId = $package->status_id;
            $payer = $package->payer;
            $package->cod = $cod ? 'Yes' : 'No';
            // $package->{$statusKey} = (!$package->{$type.'_payment_id'} && !$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
            $package->{$statusKey} = ($package->payment || $package-> disbursement) ? 'Paid':'Unpaid';
            $package->datetime = ($statusId == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
            $package->delivered_datetime = Helper::formatCustomDateTime($package->assign_driver_datetime);
            $driverCodUsd = $package->driver_cod_usd;
            $driverCodKhr = $package->driver_cod_khr;
            $fees = $package->delivery_fee + $package->extra_charge;//+ $package->additional_fee;s
            $taxiFee = $package->taxi_fee;
            $payerFees = $payer == 'sender' ? $fees : 0;
            if($statusId == 9){
                $total = PackageServiceImpl::deductRowAmountBase($driverCodUsd,$driverCodKhr,$payerFees);
                if($payer == 'receiver' && ($driverCodUsd > $package->price || $driverCodKhr > $package->price_khr)){
                    $total['amount_usd'] = min($total['amount_usd'], $package->price);
                    $total['amount_khr'] = min($total['amount_khr'], $package->price_khr);
                }
                $package->total = $total['amount_usd'];
                $package->total_khr = $total['amount_khr'];
            }else{
                $payerFees = $payer == 'sender' ? $fees : 0;
                $total = PackageServiceImpl::deductRowAmountBase($driverCodUsd,$driverCodKhr,$payerFees);
                $package->total = $payer == 'sender' ? $total['amount_usd'] : 0;
                $package->total_khr = $payer == 'sender' ? $total['amount_khr'] : 0;
            }
            $package->fee = Helper::getNumber($fees,2,true);
            return $package;
        };
        return DataResponse::PaginationV1($qP,$filters,'',[],1000,$clbMapper);
    }


    public static function getPackageTotal($type,$cod,$price,$taxiFee,$extraCharge,$additionalFee,$baseFee,$payer){
        $total = 0;
        // $baseFee += $extraCharge;
        if($type == 'driver'){
            if($cod) $total += $price;
            if($payer == 'receiver') {
                $total += $baseFee + $extraCharge;
            }
            $total -= $taxiFee;
        }
        if($type == 'merchant'){
            if($cod) $total -= $price;
            if($payer == 'sender') {
                $total += $baseFee + $extraCharge;
            }
            $total += $taxiFee;
        }

        return Helper::getNumber($total,2);
    }
    
}
