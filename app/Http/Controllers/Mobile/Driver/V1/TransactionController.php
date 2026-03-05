<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\DTO\Mobile\DisbursementDTO;
use App\DTO\Mobile\DriverCommissionDTO;
use App\DTO\Mobile\MerchantPaidPackageDTO;
use App\DTO\Mobile\MerchantTransactionDTO;
use App\DTO\Mobile\TransactionDTO;
use App\DTO\Mobile\MerchantUnpaidPackageDTO;
use App\Enums\Currency;
use App\Enums\TrackingStatus;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Services\DriverCommissionServiceImpl;
use App\Services\PackageTrailServiceImpl;
use App\Services\TransactionService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //
    public function getTransactionSummary(Request $req){
        $user = UserService::getAuthUser('merchant');
        $count = 0;
        $total = 0;
        $paidTrx = [];

        $lang = $req->lang;
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');

        $qPmt = Payment::where('payments.is_deleted', 0)
            ->where('payments.payer_id', $user->id)
            ->join('users as c', 'c.id', 'payments.receiver_uid')
            ->selectRaw("
                payments.remarks,payments.package_count,payments.id, payments.payable_amount,
                payments.breakdown_notes, c.username as cashier_name, payments.payment_datetime,
                payments.currency_code as currency,payments.trx_code as tran_id,payments.payment_ref,
                payments.received_amount_usd, payments.received_amount_khr
            ")
            ->orderByDesc('payment_datetime');

        $qDis = Disbursement::where('type', 'payment')
            ->where('disbursements.is_deleted', 0)
            ->where('disbursements.payee_id', $user->id)
            ->with('transactionMerchant:payment_id,tran_via')
            ->join('users as c', 'c.id', 'disbursements.receiptionist_uid')
            ->selectRaw("
                disbursements.remarks,disbursements.package_count, disbursements.id, disbursements.payable_amount,
                disbursements.breakdown_notes, c.username as cashier_name, disbursements.payment_datetime,
                disbursements.currency_code as currency,disbursements.trx_code as tran_id,disbursements.payment_ref,
                disbursements.received_amount_usd, disbursements.received_amount_khr
            ")
            ->orderByDesc('payment_datetime');

        if ($startDate && $endDate) {
            $startDateTime = Helper::dateYMD($startDate) . ' 00:00:00';
            $endDateTime = Helper::dateYMD($endDate) . ' 23:59:59';
            $qPmt->whereBetween('payment_datetime', [$startDateTime, $endDateTime]);
            $qDis->whereBetween('payment_datetime', [$startDateTime, $endDateTime]);
        }

        $disbursements = $qDis->get()->keyBy('id');
        $payments = $qPmt->get()->keyBy('id');

        $packages = Package::where('is_deleted', 0)
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->whereIn('status_id', [9, 19])
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $startDateTime = Helper::dateYMD($startDate) . ' 00:00:00';
                $endDateTime = Helper::dateYMD($endDate) . ' 23:59:59';
                $q->where(function ($query) use ($startDateTime, $endDateTime) {
                    $query->whereBetween('delivered_datetime', [$startDateTime, $endDateTime])
                        ->orWhereBetween('failed_datetime', [$startDateTime, $endDateTime]);
                });
            })
            ->where('merchant_id', $user->id)
            ->orderBy('id', 'desc')
            ->get();

        $samePmtId = [];
        $sameDisId = [];
        // $unpUsdPkgIds = [];
        // $unpKhrPkgIds = [];

        // Map of package_id => payment_id
        $paymentPackages = DB::table('payment_packages')
            ->where('payer_type', 'merchant')
            ->where('is_deleted', false)
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->groupBy('package_id');

        // Map of package_id => disbursement_id
        $disbursementPackages = DB::table('disbursement_packages')
            ->where('payee_type', 'merchant')
            ->where('is_deleted', false)
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->groupBy('package_id');

        // $paymentDetails = PaymentDetail::whereIn('payment_id', $payments->keys())->get()->keyBy('payment_id');
        $paymentDetails = PaymentDetail::whereIn('payment_id', $payments->keys())->get()->groupBy('payment_id');
        // return $paymentDetails;
        $disbursementDetails = DisbursementDetails::whereIn('disbursement_id', $disbursements->keys())->get()->groupBy('disbursement_id');


        $totalMerchantCodUSD = 0;
        $totalMerchantCodKHR = 0;
        $totalFee = 0;
        // $type = $lang == 'km' ? 'បានទូរទាត់':'Paid';
        foreach ($packages as $p) {
            $price = $p->price;
            $taxiFee = $p->taxi_fee;

            if ($p->status_id == 19) {
                $price = 0;
                $taxiFee = 0;
            }

            // Use disbursement_packages table instead of merchant_disbursement_id
            if (isset($disbursementPackages[$p->id])) {
                foreach ($disbursementPackages[$p->id] as $dp) {
                    $disbursementId = $dp->disbursement_id;
                    if (!isset($sameDisId[$disbursementId])) {
                        $dis = TransactionService::getTrxDetailsV1($disbursements, $disbursementId, $disbursementDetails);
                        if ($dis) {
                            $dis->from = config('app.company_name');
                            $dis->to = $user->username;
                            // $dis->remarks = 'Receive';
                            $dis->type = __('general.received');
                            $paidTrx[] = $dis;
                            $sameDisId[$disbursementId] = true;
                        }
                    }
                }
            }

            if (isset($paymentPackages[$p->id])) {
                foreach ($paymentPackages[$p->id] as $pp) {
                    $paymentId = $pp->payment_id;
                    if (!isset($samePmtId[$paymentId])) {
                        $pmt = TransactionService::getTrxDetailsV1($payments, $paymentId, $paymentDetails);
                        if ($pmt) {
                            // $pmt->remarks = 'Disbursement'; // This might be better named "Payment"
                            $pmt->from = $user->username;
                            $pmt->to = config('app.company_name');
                            $pmt->type = __('general.paid');
                            $paidTrx[] = $pmt;
                            $samePmtId[$paymentId] = true;
                        }
                    }
                }
            }

            // Check if package is unpaid
            $isPaid = isset($paymentPackages[$p->id]) || isset($disbursementPackages[$p->id]);

            if (!$isPaid) {
                $count += 1;

                // calculate total
                $total += Helper::getNumber(TransactionService::getPackageTotal(
                    'merchant',
                    $p->cod,
                    $price,
                    $taxiFee,
                    $p->extra_charge,
                    $p->additional_fee,
                    $p->delivery_fee,
                    $p->payer
                ));
                $merchantCodUsd = $p->driver_cod_usd;
                $merchantCodKhr = $p->driver_cod_khr;
                $totalFee += $p->payer === 'sender' ? $p->taxi_fee + $p->delivery_fee + $p->extra_charge : 0;
                // if driver has both USD & KHR, count only in USD
                if ($p->driver_cod_usd > 0 && $p->driver_cod_khr > 0) {
                    $totalMerchantCodUSD += $p->driver_total;
                    // if (!in_array($p->id, $unpUsdPkgIds)) {
                    //     $unpUsdPkgIds[] = $p->id;
                    // }
                } else {
                    if ($p->driver_cod_usd > 0) {
                        $totalMerchantCodUSD += $merchantCodUsd;
                        // if (!in_array($p->id, $unpUsdPkgIds)) {
                        //     $unpUsdPkgIds[] = $p->id;
                        // }
                    }
                    if ($p->driver_cod_khr > 0) {
                        $totalMerchantCodKHR += $merchantCodKhr;
                        // Only add to KHR if not already in USD
                        // if (!in_array($p->id, $unpUsdPkgIds) && !in_array($p->id, $unpKhrPkgIds)) {
                        //     $unpKhrPkgIds[] = $p->id;
                        // }
                    }
                }
            }
        }
        Helper::deductAmountBase($totalMerchantCodUSD,$totalMerchantCodKHR,$totalFee);

        usort($paidTrx, function ($a, $b) {
            return strtotime($b['payment_datetime']) <=> strtotime($a['payment_datetime']);
        });
        $transactionsByDate = collect($paidTrx)
            ->groupBy(function ($trx) {
                return Carbon::parse($trx->payment_datetime)->format('d-m-Y');
            })
            ->sortKeysDesc()
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'info' => $items->values(), // ensure it's an array
                ];
            })
            ->values()->toArray();
        return ApiResponse::JsonResult(MerchantTransactionDTO::fromModel([
            'settleAmountUsd' => [
                'amount' => (string)$totalMerchantCodUSD,
                'currency' => Currency::USD->value,
                // 'pkgIds' => $unpUsdPkgIds
            ],
            'settleAmountKhr' => [
                'amount' => (string)$totalMerchantCodKHR,
                'currency' => Currency::KHR->value,
                // 'pkgIds' => $unpKhrPkgIds
            ],
            'transactions' => $transactionsByDate,
        ]));
    }


    // public function getTransactionSummary(Request $req){
    //     $user = UserService::getAuthUser('driver');
    //     // $balanceDue = Package::where('driver_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[9,19])->sum('driver_total');
    //     $count = 0;
    //     $total = 0;
    //     $paidTrx = [];
    //     $startDate = $req->query('startDate');
    //     $endDate = $req->query('endDate');
    //     $qPmt = Payment::where('payments.is_deleted',0)->where('payments.payer_id',$user->id)
    //     // ->where('payments.approved',1)
    //     ->join('users as c','c.id','payments.receiver_uid')
    //     ->selectRaw('payments.package_count,payments.id,payments.payable_amount,payments.breakdown_notes,c.username as cashier_name,payments.payment_datetime')
    //     ->orderByDesc('payment_datetime');

    //     $qDis = Disbursement::where('type','payment')->where('disbursements.is_deleted',0)->where('disbursements.payee_id',$user->id)
    //     // ->where('disbursements.approved',1)
    //     ->join('users as c','c.id','disbursements.receiptionist_uid')
    //     ->selectRaw('disbursements.package_count,disbursements.id,disbursements.payable_amount,disbursements.breakdown_notes,c.username as cashier_name,disbursements.payment_datetime')
    //     ->orderByDesc('payment_datetime');

    //     if($startDate && $endDate){
    //         $startDateTime = Helper::dateYMD($startDate).' 00:00:00';
    //         $endDateTime = Helper::dateYMD($endDate).' 23:59:59';
    //         $qPmt->whereBetween('payment_datetime',[$startDateTime,$endDateTime]);
    //         $qDis->whereBetween('payment_datetime',[$startDateTime,$endDateTime]);
    //     }

    //     $disbursements = $qDis->get();

    //     $payments = $qPmt->get();
    //     $packages = Package::where('is_deleted',0)
    //     ->where('created_at', '>=', Carbon::now()->subMonths(2))
    //     ->whereIn('status_id',[9,19])
    //     ->selectRaw('*')
    //     ->where('driver_id',$user->id)
    //     ->orderBy('driver_payment_id','desc')
    //     ->orderBy('driver_disbursement_id','desc')
    //     ->get();
    //     $samePmtId = [];
    //     $sameDisId = [];
    //     $paymentDetails = PaymentDetail::whereIn('payment_id',Helper::pluckEloCollection($payments,'id'))->get()->keyBy('payment_id');
    //     $disbursementDetails = DisbursementDetails::whereIn('disbursement_id',Helper::pluckEloCollection($disbursements,'id'))->get()->keyBy('payment_id');
    //     foreach($packages as $p){
    //         $price = $p->price;
    //         $taxiFee = $p->taxi_fee;
    //         if($p->status_id == 19){
    //             $price = 0;
    //             $taxiFee = 0;
    //         }
    //         if(!isset($samePmtId[$p->driver_payment_id]) && $p->driver_payment_id){
    //             $pmt = TransactionService::getTrxDetails($payments,$p->driver_payment_id,$paymentDetails);
    //             if($pmt) {
    //                 $pmt->remarks = 'Disbursement';
    //                 // $total -= (float)$pmt->payable_amount;
    //                 $paidTrx[] = $pmt;
    //                 // $count -= $pmt->package_count;
    //             }
    //             $samePmtId[$p->driver_payment_id] = true;
    //         }

    //         if(!isset($sameDisId[$p->driver_disbursement_id]) && $p->driver_disbursement_id){
    //             $dis = TransactionService::getTrxDetails($disbursements,$p->driver_disbursement_id,$disbursementDetails);
    //             if($dis) {
    //                 // $total -= (float)$dis->payable_amount;
    //                 $dis->remarks = 'Receive';
    //                 $paidTrx[] = $dis;
    //                 // $count -= $dis->package_count;
    //             }
    //             $sameDisId[$p->driver_disbursement_id] = true;
    //         }

    //         // else {
    //         //     $total += Helper::getNumber(TransactionService::getPackageTotal('driver',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
    //         //     $count +=1;
    //         // }
    //         if(!$p->driver_payment_id && !$p->driver_disbursement_id) {
    //             $count += 1;
    //             $total += Helper::getNumber(TransactionService::getPackageTotal('driver',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
    //         }


    //     }
    //     usort($paidTrx, function ($a, $b) {
    //         return strtotime($b['payment_datetime']) <=> strtotime($a['payment_datetime']);
    //     });

    //     $obj = (object)[
    //         'balance_due' => (float)Helper::getNumber($total,2),
    //         'count' => $count,
    //         'total' => (float)Helper::getNumber($total,2),
    //         'payment_transaction' => $paidTrx
    //     ];

    //     // $payments =

    //     return ApiResponse::JsonResult($obj);
    // }

    public function getUnpaidPackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'packages.id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','extra_charge','driver_id','zone_name','method',
            'driver_cod_khr','driver_cod_usd','merchant_id','delivered_datetime','payer','remarks',
            'failed_datetime'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [TrackingStatus::DELIVERED->value,TrackingStatus::FAILED_WITH_FEE->value],
            startDate: $req->query('startDate'),
            endDate: $req->query('endDate'),
            select: $select
        );

        $callback = function ($q):MerchantUnpaidPackageDTO{
            $fees = $q->payer == 'sender' ? ($q->delivery_fee + $q->extra_charge): 0;
            // $q->withoutMerchantPayment();
            $finishDate = $q->status_id == 19 ? $q->failed_datetime : $q->delivered_datetime;
            $total = PackageTrailServiceImpl::deductRowAmountBase($q->driver_cod_usd,$q->driver_cod_khr,$fees);
            return new MerchantUnpaidPackageDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::currencyAmount($q->price,'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($q->price_khr,'KHR'),
                receiver_address: $q->receiver_address,
                image: $q->image_url,
                remarks: $q->remarks,
                driver_name: $q->driver->username,
                driver_phone: $q->driver->phone,
                finished_date: Helper::dateDMY($finishDate,'d/m/Y'),
                finished_time: Helper::time($finishDate),
                receiver_amt_usd: Helper::currencyAmount($total['amount_usd'],'USD'),
                receiver_amt_khr: Helper::currencyAmount($total['amount_khr'],'KHR'),
                taxi_fee: ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: Helper::currencyAmount($fees,'USD'),
                submitted_image_urls: $q->submitted_image_urls,
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select);
    }

    public function getPaidPackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','extra_charge','driver_id','zone_name','method',
            'driver_cod_khr','driver_cod_usd','merchant_id','delivered_datetime','payer','remarks',
            'failed_datetime'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [TrackingStatus::DELIVERED->value,TrackingStatus::FAILED_WITH_FEE->value],
            startDate: $req->query('startDate',Carbon::now()->format('Y-m-d')),
            endDate: $req->query('endDate',Carbon::now()->format('Y-m-d')),
            select: $select,
            isPaid: true,
        );

        $callback = function ($q):MerchantPaidPackageDTO{
            $fees = $q->payer == 'sender' ? ($q->delivery_fee + $q->extra_charge + $q->taxi_fee): 0;
            $receivedAmtUsd = 0;
            $receivedAmtKhr = 0;
            if($q->driver_cod_usd > 0 && $q->driver_cod_khr > 0){
                $receivedAmtUsd = $q->driver_cod_usd;
                $receivedAmtKhr = $q->driver_cod_khr;
            }else if($q->driver_cod_usd > 0){
                $receivedAmtUsd = $q->driver_cod_usd;
            }else if($q->driver_cod_khr > 0){
                $receivedAmtKhr = $q->driver_cod_khr;
            }
            $pmtStatus = __('general.received');
            // }
            $receivedAmt = PackageTrailServiceImpl::deductRowAmountBase($receivedAmtUsd,$receivedAmtKhr,$fees);
            // $q->withoutMerchantPayment();
            $finishDate = $q->status_id == 19 ? $q->failed_datetime : $q->delivered_datetime;
            return new MerchantPaidPackageDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::currencyAmount($q->price,'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime,'d/m/Y'),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($q->price_khr,'KHR'),
                receiver_address: $q->receiver_address,
                remarks: $q->remarks,
                driver_name: $q->driver->username,
                driver_phone: $q->driver->phone,
                finished_date: Helper::dateDMY($finishDate,'d/m/Y'),
                finished_time: Helper::time($finishDate),
                taxi_fee: ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: Helper::currencyAmount($fees,'USD'),
                method: $q->method != 'cod' ? 'Bank':'',
                pmt_status: $pmtStatus,
                receiver_amt_usd: Helper::currencyAmount($receivedAmt['amount_usd'],'USD'),
                receiver_amt_khr: Helper::currencyAmount($receivedAmt['amount_khr'],'KHR'),
                submitted_image_urls: $q->submitted_image_urls,
                image: $q->image_url,
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select);
    }

    // public function getPaymentMethods($details,$pmtId){
    //     $method = null;
    //     foreach ($details as $d) {
    //         // Ensure $d is an object before accessing its properties
    //         if (is_object($d) && isset($d->payment_id) && $d->payment_id == $pmtId) {
    //             if (!$method) {
    //                 $pMtd = $d->method;
    //                 if($d->currency_code == 'KHR'){
    //                     $pMtd = $pMtd.':KHR';
    //                 }
    //                 $method = $pMtd;
    //             } else {
    //                 $pMtd = $d->method;
    //                 if($d->currency_code == 'KHR'){
    //                     $pMtd = $pMtd.':KHR';
    //                 }
    //                 $method .= '|' . $pMtd;
    //             }
    //         }
    //     }
    //     return (object)[
    //         'method' => $method,
    //     ];
    // }

    public function getCommissionReport(Request $req){
        $user = UserService::getAuthUser('driver');
        $driverId = $user->id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $lang = $req->lang;
        $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
        // ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission,delivery_commission_type,pickup_commission_start_date,delivery_commission_start_date')
        ->get();

        $commissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId,$startDate,$endDate);
        $normalDeliveryStartDate = $commissionInfo->normal_delivery_commission_start_date ?? Carbon::today()->startOfDay()->format('Y-m-d H:i:s');
        $fastDeliveryStartDate = $commissionInfo->fast_delivery_commission_start_date ?? Carbon::today()->startOfDay()->format('Y-m-d H:i:s');

        $normalPickupStartDate = $commissionInfo->normal_pickup_commission_start_date ?? Carbon::today()->startOfDay()->format('Y-m-d H:i:s');
        $fastPickupStartDate = $commissionInfo->fast_pickup_commission_start_date ?? Carbon::today()->startOfDay()->format('Y-m-d H:i:s');

        /** Rate & Type */
        $normalDeliveryCommType = $commissionInfo->normal_delivery_commission_type;
        $fastDeliveryCommType = $commissionInfo->fast_delivery_commission_type;
        $normalPickupCommType = $commissionInfo->normal_pickup_commission_type;
        $fastPickupCommType = $commissionInfo->fast_pickup_commission_type;

        $normalDeliveryCommRate = $commissionInfo->normal_delivery_commission;
        $fastDeliveryCommRate = $commissionInfo->fast_delivery_commission;
        $normalPickupCommRate = $commissionInfo->normal_pickup_commission;
        $fastPickupCommRate = $commissionInfo->fast_pickup_commission;



        $endDate = Carbon::parse($endDate)->endOfDay()->format('Y-m-d H:i:s');

        $deliveryResult = Package::query()->from('packages as p')
            ->where('p.delivery_fee', '>', 0)
            ->where('p.driver_id', $driverId)
            ->where(function ($query) {
                $query->whereIn('p.status_id', [9, 19])
                    ->orWhere('p.prev_status_id', 19);
            })
            ->where('p.is_deleted', 0)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('disbursement_packages as dp')
                    ->whereColumn('dp.package_id', 'p.id')
                    ->where('dp.payee_type', 'driver')
                    ->where('dp.type', 'commission')
                    ->where('dp.is_deleted', false);
            })
            ->where(function ($q) use ($normalDeliveryStartDate, $fastDeliveryStartDate, $normalPickupStartDate, $fastPickupStartDate, $endDate) {
                $q->where(function ($q2) use ($normalDeliveryStartDate, $endDate) {
                    $q2->where('p.status_id', 9)
                        ->where('p.delivery_type', 'normal')
                        ->whereBetween('p.delivered_datetime', [$normalDeliveryStartDate, $endDate]);
                })
                ->orWhere(function ($q2) use ($fastDeliveryStartDate, $endDate) {
                    $q2->where('p.status_id', 9)
                        ->where('p.delivery_type', 'fast')
                        ->whereBetween('p.delivered_datetime', [$fastDeliveryStartDate, $endDate]);
                })
                ->orWhere(function ($q2) use ($normalPickupStartDate, $endDate) {
                    $q2->where(function($q3){
                        $q3->where('p.status_id', 19)
                            ->orWhere('p.prev_status_id', 19);
                    })
                    ->where('p.delivery_type', 'normal')
                    ->whereBetween('p.failed_datetime', [$normalPickupStartDate, $endDate]);
                })
                ->orWhere(function ($q2) use ($fastPickupStartDate, $endDate) {
                    $q2->where(function($q3){
                        $q3->where('p.status_id', 19)
                            ->orWhere('p.prev_status_id', 19);
                    })
                    ->where('p.delivery_type', 'fast')
                    ->whereBetween('p.failed_datetime', [$fastPickupStartDate, $endDate]);
                });
            })
            ->selectRaw("
                SUM(CASE WHEN p.status_id = 9 AND p.delivery_type = 'normal' THEN 1 ELSE 0 END) as normal_delivered_count,
                SUM(CASE WHEN p.status_id = 9 AND p.delivery_type = 'fast' THEN 1 ELSE 0 END) as fast_delivered_count,
                SUM(CASE WHEN (p.status_id = 19 OR p.prev_status_id = 19) AND p.delivery_type = 'normal' THEN 1 ELSE 0 END) as normal_failed_with_fee_count,
                SUM(CASE WHEN (p.status_id = 19 OR p.prev_status_id = 19) AND p.delivery_type = 'fast' THEN 1 ELSE 0 END) as fast_failed_with_fee_count,
                SUM(CASE WHEN p.delivery_type = 'normal' THEN p.delivery_fee ELSE 0 END) as total_normal_delivery_fee
            ")
            ->first();
        

        $packageSubQuery = Package::query()
            ->select(
                'order_id',
                DB::raw('COUNT(*) as qty'),
                DB::raw('SUM(delivery_fee) as total_delivery_fee')
            )
            ->where('is_deleted', 0)
            ->where('delivery_fee', '>', 0)
            ->where(function($q) use ($normalDeliveryStartDate, $endDate){
                $q->where(function($q2) use ($normalDeliveryStartDate, $endDate){
                    $q2->where('status_id', 9)
                    ->whereBetween('delivered_datetime', [$normalDeliveryStartDate, $endDate]);
                })
                ->orWhere(function($q2) use ($normalDeliveryStartDate, $endDate){
                    $q2->where(function($q3){
                        $q3->where('status_id', 19)
                        ->orWhere('prev_status_id', 19);
                    })
                    ->whereBetween('failed_datetime', [$normalDeliveryStartDate, $endDate]);
                });
            })
            ->groupBy('order_id')
            ->having(DB::raw('COUNT(*)'), '>', 0);

        $pickupResult = Order::query()
            ->where('orders.is_deleted', 0)
            ->whereNull('orders.driver_commission_id')
            ->where('orders.status_id', 5)
            ->where('orders.driver_id', $driverId)
            ->joinSub($packageSubQuery, 'psub', function($join){  // Changed to lowercase 'psub'
                $join->on('orders.id', '=', 'psub.order_id');     // Changed to lowercase 'psub'
            })
            ->selectRaw('SUM(psub.qty) as total_qty, SUM(psub.total_delivery_fee) as total_fee')  // Changed to lowercase 'psub'
            ->first();
        // Access both values:
        $pickUpCount = $pickupResult->total_qty ?? 0;
        $totalPickupDeliveryFee = $pickupResult->total_fee ?? 0;

        $normalDeliveredCount = $deliveryResult->normal_delivered_count ?? 0;
        $fastDeliveredCount = $deliveryResult->fast_delivered_count ?? 0;
        $normalFailedWithFeeCount = $deliveryResult->normal_failed_with_fee_count ?? 0;
        $fastFailedWithFeeCount = $deliveryResult->fast_failed_with_fee_count ?? 0;
        $totalNormalDeliveryFee = $deliveryResult->total_normal_delivery_fee ?? 0;

        $normalDeliveredCommAmt = TransactionService::calculateCommission(
            count: $normalDeliveredCount,
            rate: $normalDeliveryCommRate,
            rateType: $normalDeliveryCommType,
            totalBaseFee: $totalNormalDeliveryFee
        );
        

        $normalFailedWithFeeCommAmt = TransactionService::calculateCommission(
            count: $normalFailedWithFeeCount,
            rate: $normalDeliveryCommRate,
            rateType: $normalDeliveryCommType,
            totalBaseFee: $totalNormalDeliveryFee
        );

        $normalPickUpCommAmt = TransactionService::calculateCommission(
            count: $pickUpCount,
            rate: $normalPickupCommRate,
            rateType: $normalPickupCommType,
            totalBaseFee: $totalPickupDeliveryFee
        );

        $totalDeliveryCount = $normalDeliveredCount + $normalFailedWithFeeCount;
        $totalDeliveryAmt = $normalDeliveredCount > 0 ? $normalDeliveredCommAmt:0;
        $totalDeliveryAmt += $normalFailedWithFeeCount > 0 ? $normalFailedWithFeeCommAmt : 0;
        $report = [
            "total" => (float)Helper::getNumber(0),
            "details" => [
                [
                    'category' => 'Pickup',
                    'count' => $pickUpCount,
                    'unit' => (float)$normalPickupCommRate,
                    'total' => (float)Helper::getNumber($normalPickUpCommAmt,2),
                    'remarks' => '',
                ],
                [
                    'category' => 'Delivered',
                    'count' => $totalDeliveryCount,
                    'unit' => (float)$normalDeliveryCommRate,
                    'total' => $totalDeliveryCount > 0 ? (float)Helper::getNumber($totalDeliveryAmt,2) : 0,
                    'remarks' => '',
                ]
            ]
        ];

        return ApiResponse::JsonResult($report);
    }


    // public function getCommissionReport(Request $req){
    //     $user = UserService::getAuthUser('driver');
    //     $driverId = $user->id;
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
    //     ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
    //     ->get();
    //     $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
    //     $deliveryCommStartDate = $driverCommissionInfo->normal_delivery_commission_start_date;
    //     $pickUpStartDate = $driverCommissionInfo->normal_pickup_commission_start_date;
    //     // $delCommDatetime = Helper::dateYMD($deliveryCommStartDate). ' 00:00:00';
    //     $qP = Package::from('packages')->selectRaw('id,status_id,driver_id,driver_disbursement_id')
    //     ->whereIn('status_id',[9,19])
    //     ->where('driver_id',$driverId)
    //     ->where('is_deleted',0)
    //     // ->where('driver_disbursement_id',$driverId);
    //     // ->whereNull('driver_commission_id');
    //     ->whereNotExists(function ($sub) {
    //         $sub->select(DB::raw(1))
    //             ->from('disbursement_packages as dp')
    //             ->whereColumn('dp.package_id', 'packages.id')
    //             ->where('dp.payee_type', 'driver')
    //             ->where('dp.type','commission')
    //             ->where('dp.is_deleted', false);
    //     });


    //     $packageSubQuery = Package::query()
    //     ->select(
    //         'order_id',  // <-- aggregate per order
    //         DB::raw('COUNT(*) as qty'),
    //         DB::raw('SUM(delivery_fee) as total_delivery_fee')
    //     )
    //     ->where('is_deleted', 0)
    //     ->where('delivery_fee', '>', 0)
    //     ->where(function($q) use ($startDate, $endDate,$pickUpStartDate){
    //         if ($pickUpStartDate) {
    //             $pickUpStartDate = date('Y-m-d', strtotime($pickUpStartDate));
    //             if ($startDate < $pickUpStartDate) {
    //                 $startDate = $pickUpStartDate;
    //             }
    //         }
    //         $q->where(function($q2) use ($startDate, $endDate){
    //             $q2->where('status_id', 9)
    //             ->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
    //         })
    //         ->orWhere(function($q2) use ($startDate, $endDate){
    //             $q2->where(function($q3){
    //                 $q3->where('status_id', 19)
    //                 ->orWhere('prev_status_id', 19);
    //             })
    //             ->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
    //         });
    //     })
    //     ->groupBy('order_id');
    //     $qO = Order::query()
    //         ->select('orders.id', 'orders.driver_id', 'pSub.qty', 'pSub.total_delivery_fee')
    //         ->where('orders.is_deleted', 0)
    //         ->whereNull('driver_commission_id')
    //         ->where('status_id', 5)
    //         ->joinSub($packageSubQuery, 'pSub', function($join){
    //             // $join->on('orders.driver_id', '=', 'pSub.driver_id');
    //             $join->on('orders.id', '=', 'pSub.order_id');
    //         })
    //         ->groupBy('orders.id', 'orders.driver_id', 'pSub.qty', 'pSub.total_delivery_fee')
    //         ->having('pSub.qty', '>', 0);
    //     if($startDate && $endDate){
    //         $startDate = date('Y-m-d',strtotime($startDate));
    //         $endDate = date('Y-m-d',strtotime($endDate));
    //         if ($deliveryCommStartDate) {
    //             $deliveryCommStartDate = date('Y-m-d', strtotime($deliveryCommStartDate));
    //             if ($startDate < $deliveryCommStartDate) {
    //                 $startDate = $deliveryCommStartDate;
    //             }
    //         }
    //         // \Log::error($startDate.'--'.$endDate);
    //         // $qP->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
    //         $qP->where(function ($q) use ($startDate, $endDate) {
    //             $q->where(function ($sub) use ($startDate, $endDate) {
    //                 // For status_id 9 → delivered_datetime
    //                 $sub->where('status_id', 9)
    //                     ->whereBetween('delivered_datetime', [
    //                         "$startDate 00:00:00",
    //                         "$endDate 23:59:59"
    //                     ]);
    //             })->orWhere(function ($sub) use ($startDate, $endDate) {
    //                 // For status_id 19 → failed_datetime
    //                 $sub->where('status_id', 19)
    //                     ->whereBetween('failed_datetime', [
    //                         "$startDate 00:00:00",
    //                         "$endDate 23:59:59"
    //                     ]);
    //             });
    //         });
    //         $qO->whereHas('packages', function ($q) use ($startDate, $endDate,$pickUpStartDate) {
    //             // Delivered packages within date range
    //             if ($pickUpStartDate) {
    //                 $pickUpStartDate = date('Y-m-d', strtotime($pickUpStartDate));
    //                 if ($startDate < $pickUpStartDate) {
    //                     $startDate = $pickUpStartDate;
    //                 }
    //             }
    //             $q->where(function ($q) use ($startDate, $endDate) {
    //                 $q->where(function ($query) use ($startDate, $endDate) {
    //                     $query->where('status_id', 9)
    //                         ->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
    //                 })

    //                 // OR Failed packages within date range
    //                 ->orWhere(function ($query) use ($startDate, $endDate) {
    //                     $query->where(function ($sub) {
    //                             $sub->where('status_id', 19)
    //                                 ->orWhere('prev_status_id', 19);
    //                         })
    //                         ->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
    //                 });
    //             })
    //             ->where('is_deleted', 0);
    //         });

    //         // $qP->where('delivered_datetime','>=',$delCommDatetime);
    //     }

    //     $orders = $qO->get();
    //     $pickUpInfo = DriverCommissionServiceImpl::getPickUpDetails($orders, $user->id);
    //     $pickUpCount = (int)($pickUpInfo->total_package ?? 0);
    //     if($deliveryCommStartDate && $startDate){
    //         // $qP->where('delivered_datetime','>=',$delCommDatetime);
    //         $deliveredCount = $qP->count();
    //     } else $deliveredCount = 0;
    //     // $qO = Order::where('is_deleted',0)->where('status_id',5)
    //     // ->whereNull('driver_commission_id')
    //     // // ->where('driver_disbursement_id',$driverId)
    //     // ->where('driver_id',$driverId);
    //     // if($startDate && $endDate){
    //     //     $startDate = date('Y-m-d',strtotime($startDate));
    //     //     $endDate = date('Y-m-d',strtotime($endDate));
    //     //     $qP->where('order_datetime', '>=', "$startDate 00:00:00")
    //     //     ->where('order_datetime', '<=', "$endDate 23:59:59");
    //     // }

    //     $pickUpRate = $driverCommissionInfo->normal_pickup_commission;
    //     $deliveryRate = $driverCommissionInfo->normal_delivery_commission;
    //     $total = $pickUpCount * $pickUpRate + $deliveredCount * $deliveryRate;
    //     $report = [
    //         "total" => (float)Helper::getNumber($total),
    //         "details" => [
    //             [
    //                 'category' => 'Pickup',
    //                 'count' => $pickUpCount,
    //                 'unit' => (float)$pickUpRate,
    //                 'total' => (float)Helper::getNumber($pickUpCount * $pickUpRate,2),
    //                 'remarks' => '',
    //             ],
    //             [
    //                 'category' => 'Delivered',
    //                 'count' => $deliveredCount,
    //                 'unit' => (float)$deliveryRate,
    //                 'total' => (float)Helper::getNumber($deliveryRate * $deliveredCount,2),
    //                 'remarks' => '',
    //             ]
    //         ]
    //     ];

    //     return ApiResponse::JsonResult($report);
    // }

    public function getCommissionTrx(Request $req){
        $user = UserService::getAuthUser('driver');
        $lang = $req->lang;
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');
        $disbursements = Disbursement::query()
            ->where('is_deleted',false)
            ->where('payee_type', 'driver')
            ->where('type', 'commission')
            ->where('is_deleted', false)
            ->where('payee_id', $user->id)
            ->with([
                'receiptionist:id,username',
                'transactionDriver:payment_id,id,payment_ref as tran_id,amount,payment_method,from_account,to_account,remarks,payment_date as transaction_date'
            ])
            // ->select(['id','amount','receiptionist_uid','payment_datetime'])
            ->orderByDesc('payment_datetime');
        if($startDate && $endDate){
            $disbursements->whereBetween('payment_datetime',[
                Helper::dateYMD($startDate).' 00:00:00',
                Helper::dateYMD($endDate).' 23:59:59'
            ]);
        }
        $select = ['id','payment_datetime','payable_amount as amount','receiptionist_uid'];
        $groupCallback = function($items) use($lang) {
            return collect($items)
                ->filter(fn($d) => $d instanceof Disbursement)
                ->groupBy(function($q){
                    return Helper::formatCustomDateTime($q->payment_datetime,'d-M-Y');
                })
                ->map(function ($group, $date) use($lang): DriverCommissionDTO {
                    return DriverCommissionDTO::fromModel([
                        'date' => $date,
                        'type' => 'Commission',
                        'list' => $group->map(function($d) use($lang):DisbursementDTO{
                            return DisbursementDTO::fromArray([
                                'type' => $lang == 'km' ? 'បានទូរទាត់':'Paid',
                                'time' => Helper::formatCustomDateTime($d->payment_datetime,'h:i A'),
                                'amount' => '$'.$d->amount,
                                'details' => $d->transactionDriver
                                    ? TransactionDTO::fromModel($d->transactionDriver,$d->receiptionist->username)
                                    : new TransactionDTO( // or TransactionDTO::empty() if you have it
                                        amount: '',
                                        tran_id: '',
                                        payment_method: '',
                                        from_account: '',
                                        to_account: '',
                                        cashier: '',
                                        remarks: '',
                                        transaction_date: null
                                    ),

                            ]);
                        })->toArray()
                    ]);
                })->values();
        };

        return ApiResponse::PaginationV2($disbursements, $req,'',[],100,null,$select,false,null,null,$groupCallback);

    }

    private function getQueryPackages($userId, array $statusIds, $startDate, $endDate, $select = ['*'],?bool $isPaid = false,?string $paidUserType = 'merchant')
    {
        $qP = Package::query()
            ->where('is_deleted', false)
            ->where('merchant_id', $userId)
            ->select($select)
            ->whereIn('status_id', $statusIds)
            ->when($paidUserType == 'merchant', function ($query) use ($isPaid) {
                return $isPaid 
                    ? $query->withMerchantPayment() 
                    : $query->withoutMerchantPayment();
            });

        // $table = 'packages';
        // if($isPaid && $paidUserType == 'merchant'){
        //     // $qP = $qP->withMerchantPayment();
        //     $qP->withMerchantPayment();
        // } else if(!$isPaid && $paidUserType == 'merchant'){
        //     $qP->withoutMerchantPayment();
        // }

        // Date filtering
        if ($startDate && $endDate) {
            $startDatetime = Helper::dateYMD($startDate) . ' 00:00:00';
            $endDatetime   = Helper::dateYMD($endDate) . ' 23:59:59';

            // Apply datetime conditions depending on which status is included
            $qP->where(function ($q) use ($startDatetime, $endDatetime, $statusIds) {
                $statusToColumnMap = [
                    19 => 'failed_datetime',
                    9  => 'delivered_datetime',
                    11 => 'returned_datetime',
                    5  => 'arrive_warehouse_datetime',
                ];

                foreach ($statusIds as $statusId) {
                    if (isset($statusToColumnMap[$statusId])) {
                        $column = $statusToColumnMap[$statusId];
                        $q->orWhere(function ($subQ) use ($column, $startDatetime, $endDatetime, $statusId) {
                            $subQ->where('status_id', $statusId)
                                 ->whereBetween($column, [$startDatetime, $endDatetime]);
                        });
                    }
                }
            });
        }
        return $qP;
    }
}
