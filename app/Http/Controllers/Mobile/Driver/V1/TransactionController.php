<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\DTO\Mobile\DisbursementDTO;
use App\DTO\Mobile\DriverCommissionDTO;
use App\DTO\Mobile\DriverTransaction;
use App\DTO\Mobile\DriverUnpaidPackageDTO;
use App\DTO\Mobile\TransactionDTO;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Services\DriverCommissionServiceImpl;
use App\Services\GeneralSettingService;
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
        $user = UserService::getAuthUser('driver');
        $count = 0;
        $total = 0;
        $paidTrx = [];

        $lang = $req->lang;
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');
        $qPmt = Payment::where('payments.is_deleted',0)
            ->where('payments.payer_id', $user->id)
            ->join('users as c', 'c.id', 'payments.receiver_uid')
            ->with('transactionDriver:id,payment_id,details')
            ->selectRaw("
                payments.remarks,payments.package_count,payments.id, payments.payable_amount,
                payments.received_amount_usd,payments.received_amount_khr,
                payments.breakdown_notes, c.username as cashier_name, payments.payment_datetime,
                payments.currency_code as currency,payments.trx_code as tran_id,payments.payment_ref
            ")
            ->orderByDesc('payment_datetime');

        $qDis = Disbursement::where('type', 'payment')
            ->where('disbursements.is_deleted', 0)
            ->where('disbursements.payee_id', $user->id)
            ->join('users as c', 'c.id', 'disbursements.receiptionist_uid')
            ->selectRaw("
                disbursements.remarks,disbursements.package_count, disbursements.id, disbursements.payable_amount,
                disbursements.received_amount_usd,disbursements.received_amount_khr,
                disbursements.breakdown_notes, c.username as cashier_name, disbursements.payment_datetime,
                disbursements.currency_code as currency,disbursements.trx_code as tran_id,disbursements.payment_ref
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
            ->where('driver_id', $user->id)
            ->orderBy('id', 'desc')
            ->get();

        $samePmtId = [];
        $sameDisId = [];
        $unpUsdPkgIds = [];
        $unpKhrPkgIds = [];

        // Map of package_id => payment_id
        $paymentPackages = DB::table('payment_packages')
            ->where('payer_type', 'driver')
            ->where('is_deleted', false)
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->groupBy('package_id');

        // Map of package_id => disbursement_id
        $disbursementPackages = DB::table('disbursement_packages')
            ->where('payee_type', 'driver')
            ->where('is_deleted', false)
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->groupBy('package_id');

        // $paymentDetails = PaymentDetail::whereIn('payment_id', $payments->keys())->get()->keyBy('payment_id');
        $paymentDetails = PaymentDetail::whereIn('payment_id', $payments->keys())->get()->groupBy('payment_id');
        // return $paymentDetails;
        $disbursementDetails = DisbursementDetails::whereIn('disbursement_id', $disbursements->keys())->get()->groupBy('disbursement_id');


        $totalDriverCodUSD = 0;
        $totalDriverCodKHR = 0;
        $totalFee = 0;
        $type = $lang == 'km' ? 'បានទូរទាត់':'Paid';
        foreach ($packages as $p) {
            $price = $p->price;
            $taxiFee = $p->taxi_fee;

            if ($p->status_id == 19) {
                $price = 0;
                $taxiFee = 0;
            }

            // Use disbursement_packages table instead of driver_disbursement_id
            if (isset($disbursementPackages[$p->id])) {
                foreach ($disbursementPackages[$p->id] as $dp) {
                    $disbursementId = $dp->disbursement_id;
                    if (!isset($sameDisId[$disbursementId])) {
                        $dis = TransactionService::getTrxDetailsV1($disbursements, $disbursementId, $disbursementDetails);
                        if ($dis) {
                            $dis->from = config('app.code_prefix') . ' Company';
                            $dis->to = $user->username;
                            // $dis->remarks = 'Receive';
                            $dis->type = 'Received';
                            $dis->amount = $dis->paid_amount_usd;
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
                            // $pmt->from = $user->username;
                            $pmt->from = $pmt?->transactionDriver?->details['data']['payer_account'] ?? $user->username;
                            $pmt->to = config('app.code_prefix') . ' Company';
                            $pmt->type = 'Paid';
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
                    'driver',
                    $p->cod,
                    $price,
                    $taxiFee,
                    $p->extra_charge,
                    $p->additional_fee,
                    $p->delivery_fee,
                    $p->payer
                ));
                $driverCodUsd = $p->driver_cod_usd;
                $driverCodKhr = $p->driver_cod_khr;
                $totalFee += $p->payer === 'receiver' ? $p->taxi_fee + $p->delivery_fee + $p->extra_charge : 0;
                // if driver has both USD & KHR, count only in USD
                if ($p->driver_cod_usd > 0 && $p->driver_cod_khr > 0) {
                    $totalDriverCodUSD += $p->driver_total;
                    if (!in_array($p->id, $unpUsdPkgIds)) {
                        $unpUsdPkgIds[] = $p->id;
                    }
                } else {
                    if ($p->driver_cod_usd > 0) {
                        $totalDriverCodUSD += $driverCodUsd;
                        if (!in_array($p->id, $unpUsdPkgIds)) {
                            $unpUsdPkgIds[] = $p->id;
                        }
                    }
                    if ($p->driver_cod_khr > 0) {
                        $totalDriverCodKHR += $driverCodKhr;
                        // Only add to KHR if not already in USD
                        if (!in_array($p->id, $unpUsdPkgIds) && !in_array($p->id, $unpKhrPkgIds)) {
                            $unpKhrPkgIds[] = $p->id;
                        }
                    }
                }
            }
        }
        // Helper::deductAmountBase($totalDriverCodUSD,$totalDriverCodKHR,$totalFee);

        usort($paidTrx, function ($a, $b) {
            return strtotime($b['payment_datetime']) <=> strtotime($a['payment_datetime']);
        });
        $transactionsByDate = collect($paidTrx)
            ->groupBy(function ($trx) {
                $trx->tran_id = $trx?->transactionDriver?->details['data']['transaction_id'] ?? $trx->tran_id;
                unset($trx->transactionDriver);
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
        return ApiResponse::JsonResult(DriverTransaction::fromModel([
            'settleAmountUsd' => [
                'amount' => (string)$totalDriverCodUSD,
                'fmt_amount' => Helper::getNumber($totalDriverCodUSD,2,true),
                'currency' => Currency::USD->value,
                'pkgIds' => $unpUsdPkgIds
            ],
            'settleAmountKhr' => [
                'amount' => (string)$totalDriverCodKHR,
                'fmt_amount' => Helper::getNumber($totalDriverCodKHR,0,true),
                'currency' => Currency::KHR->value,
                'pkgIds' => $unpKhrPkgIds
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
        // $type = 'driver';
        $lang = $req->lang;
        $qP = Package::query()->from('packages as p')->where('p.is_deleted',0)
        ->join('users as m','m.id','p.merchant_id')
        ->join('tracking_statuses as trs','p.status_id','trs.id')
        ->where('p.driver_id',$user->id)
        ->whereIn('p.status_id',[9,19])
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'p.id')
                ->where('pp.payer_type', 'driver')
                ->where('pp.is_deleted', false);
        })
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.type','payment')
                ->where('dp.is_deleted', false);
        })
        ->orderByDesc('p.updated_at')

        ->selectRaw(
            'p.driver_id,p.returned_uid,p.payer,p.extra_charge,p.cod,p.price as price_usd,p.price_khr,
            p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,p.status_id,trs.name as status_code,
            m.username as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,
            p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,
            p.delivered_datetime,p.arrive_warehouse_datetime,p.driver_cod_usd,p.driver_cod_khr,p.other_fee,
            p.delivery_remarks,p.remarks,p.taxi_fee'
        );
        // ->get();
        $today = now();
        $dateAgo = Helper::getDateDaysAgo(3);
        $attachments = PackageAttachment::where('hidden', 0)
            ->whereBetween('updated_at', [$dateAgo, $today])
            ->limit(1000)
            ->selectRaw("package_id,file_name,file_dir, TO_CHAR(created_at, 'YYYY-MM-DD') as date")
            ->get()
            ->groupBy('package_id');
        $callback = function ($p) use($lang,$attachments){
            if($lang == 'km'){
                $p->status_code = GeneralSettingService::$statusCodeTrans[$p->status_id];
            }
            $p->fees = Helper::currencyAmount($p->delivery_fee + $p->other_fee,'USD');
            $p->taxi_fee = Helper::currencyAmount($p->taxi_fee,'USD');
            $date = $p->status_id == 9 ? $p->delivered_datetime : $p->failed_datetime;
            $p->images = isset($attachments[$p->package_id])
            ? $attachments[$p->package_id]->map(fn($att) =>  Helper::getImageUrl($att->file_name, 1,$att->file_dir,$att->date))->values()->toArray()
            : [];

            $p->arrive_date = Helper::formatCustomDateTime($p->arrive_warehouse_datetime,'d M,Y');
            $p->arrive_time = Helper::formatCustomDateTime($p->arrive_warehouse_datetime,'h:i A');
            $p->date = Helper::formatCustomDateTime($date,'d M,Y');
            $p->time = Helper::formatCustomDateTime($date,'h:i A');
            $p->price_khr = Helper::currencyAmount($p->price_khr,'KHR');
            $p->price_usd = Helper::currencyAmount($p->price_usd,'USD');
            $p->driver_cod_khr = Helper::currencyAmount($p->driver_cod_khr,'KHR');
            $p->driver_cod_usd = Helper::currencyAmount($p->driver_cod_usd,'USD');
            // return $p;
            return DriverUnpaidPackageDTO::fromModel($p);
        };
        return ApiResponse::PaginationV1($qP,$req,'',[],200,$callback);
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
        $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
        ->get();
        $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        $deliveryCommStartDate = $driverCommissionInfo->normal_delivery_commission_start_date;
        $pickUpStartDate = $driverCommissionInfo->normal_pickup_commission_start_date;
        // $delCommDatetime = Helper::dateYMD($deliveryCommStartDate). ' 00:00:00';
        $qP = Package::from('packages')->selectRaw('id,status_id,driver_id,driver_disbursement_id')
        ->whereIn('status_id',[9,19])
        ->where('driver_id',$driverId)
        ->where('is_deleted',0)
        // ->where('driver_disbursement_id',$driverId);
        // ->whereNull('driver_commission_id');
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'packages.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.type','commission')
                ->where('dp.is_deleted', false);
        });


        $packageSubQuery = Package::query()
        ->select(
            'order_id',  // <-- aggregate per order
            DB::raw('COUNT(*) as qty'),
            DB::raw('SUM(delivery_fee) as total_delivery_fee')
        )
        ->where('is_deleted', 0)
        ->where('delivery_fee', '>', 0)
        ->where(function($q) use ($startDate, $endDate,$pickUpStartDate){
            if ($pickUpStartDate) {
                $pickUpStartDate = date('Y-m-d', strtotime($pickUpStartDate));
                if ($startDate < $pickUpStartDate) {
                    $startDate = $pickUpStartDate;
                }
            }
            $q->where(function($q2) use ($startDate, $endDate){
                $q2->where('status_id', 9)
                ->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            })
            ->orWhere(function($q2) use ($startDate, $endDate){
                $q2->where(function($q3){
                    $q3->where('status_id', 19)
                    ->orWhere('prev_status_id', 19);
                })
                ->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            });
        })
        ->groupBy('order_id');
        $qO = Order::query()
            ->select('orders.id', 'orders.driver_id', 'pSub.qty', 'pSub.total_delivery_fee')
            ->where('orders.is_deleted', 0)
            ->whereNull('driver_commission_id')
            ->where('status_id', 5)
            ->joinSub($packageSubQuery, 'pSub', function($join){
                // $join->on('orders.driver_id', '=', 'pSub.driver_id');
                $join->on('orders.id', '=', 'pSub.order_id');
            })
            ->groupBy('orders.id', 'orders.driver_id', 'pSub.qty', 'pSub.total_delivery_fee')
            ->having('pSub.qty', '>', 0);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            if ($deliveryCommStartDate) {
                $deliveryCommStartDate = date('Y-m-d', strtotime($deliveryCommStartDate));
                if ($startDate < $deliveryCommStartDate) {
                    $startDate = $deliveryCommStartDate;
                }
            }
            // \Log::error($startDate.'--'.$endDate);
            // $qP->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            $qP->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($sub) use ($startDate, $endDate) {
                    // For status_id 9 → delivered_datetime
                    $sub->where('status_id', 9)
                        ->whereBetween('delivered_datetime', [
                            "$startDate 00:00:00",
                            "$endDate 23:59:59"
                        ]);
                })->orWhere(function ($sub) use ($startDate, $endDate) {
                    // For status_id 19 → failed_datetime
                    $sub->where('status_id', 19)
                        ->whereBetween('failed_datetime', [
                            "$startDate 00:00:00",
                            "$endDate 23:59:59"
                        ]);
                });
            });
            $qO->whereHas('packages', function ($q) use ($startDate, $endDate,$pickUpStartDate) {
                // Delivered packages within date range
                if ($pickUpStartDate) {
                    $pickUpStartDate = date('Y-m-d', strtotime($pickUpStartDate));
                    if ($startDate < $pickUpStartDate) {
                        $startDate = $pickUpStartDate;
                    }
                }
                $q->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($query) use ($startDate, $endDate) {
                        $query->where('status_id', 9)
                            ->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
                    })

                    // OR Failed packages within date range
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where(function ($sub) {
                                $sub->where('status_id', 19)
                                    ->orWhere('prev_status_id', 19);
                            })
                            ->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
                    });
                })
                ->where('is_deleted', 0);
            });

            // $qP->where('delivered_datetime','>=',$delCommDatetime);
        }

        $orders = $qO->get();
        $pickUpInfo = DriverCommissionServiceImpl::getPickUpDetails($orders, $user->id);
        $pickUpCount = (int)($pickUpInfo->total_package ?? 0);
        if($deliveryCommStartDate && $startDate){
            // $qP->where('delivered_datetime','>=',$delCommDatetime);
            $deliveredCount = $qP->count();
        } else $deliveredCount = 0;
        // $qO = Order::where('is_deleted',0)->where('status_id',5)
        // ->whereNull('driver_commission_id')
        // // ->where('driver_disbursement_id',$driverId)
        // ->where('driver_id',$driverId);
        // if($startDate && $endDate){
        //     $startDate = date('Y-m-d',strtotime($startDate));
        //     $endDate = date('Y-m-d',strtotime($endDate));
        //     $qP->where('order_datetime', '>=', "$startDate 00:00:00")
        //     ->where('order_datetime', '<=', "$endDate 23:59:59");
        // }

        $pickUpRate = $driverCommissionInfo->normal_pickup_commission;
        $deliveryRate = $driverCommissionInfo->normal_delivery_commission;
        $total = $pickUpCount * $pickUpRate + $deliveredCount * $deliveryRate;
        $report = [
            "total" => (float)Helper::getNumber($total),
            "details" => [
                [
                    'category' => 'Pickup',
                    'count' => $pickUpCount,
                    'unit' => (float)$pickUpRate,
                    'total' => (float)Helper::getNumber($pickUpCount * $pickUpRate,2),
                    'remarks' => '',
                ],
                [
                    'category' => 'Delivered',
                    'count' => $deliveredCount,
                    'unit' => (float)$deliveryRate,
                    'total' => (float)Helper::getNumber($deliveryRate * $deliveredCount,2),
                    'remarks' => '',
                ]
            ]
        ];

        return ApiResponse::JsonResult($report);
    }

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

    // public function getCommissonTranxAndReport(Request $req){
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $obj = (object)[
    //         'report' => [
    //             [
    //                 'category' => '',
    //                 'count' => 250,
    //                 'unit' => 0.5,
    //                 'total' => 0,
    //                 'remarks' =>  ''
    //             ]
    //         ],
    //         'transaction' => [
                    // [
                    //     'payment_date' => '',
                    //     'payable_amount' => 0,
                    //     'method' => '',
                    //     'payer_name' => '',
                    // ]
    //         ],
    //     ];

    //     return ApiResponse::JsonResult($obj);
    // }
}
