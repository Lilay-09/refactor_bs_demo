<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TrackingStatus;
use App\Enums\TransactionType;
use App\Jobs\SendNotificationJob;
use App\Models\Bank;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DisbursementPackage;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentPackage;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\UserBank;
use App\Models\Zone;
use Carbon\Carbon;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionService
{

    public function getDeliveryPackages(Request $req,$type,$user){
        $driverId = $req->driver_id;
        $merchantId = $req->merchant_id;
        $pmtStatusId = $req->payment_status_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $search = $req->search;
        Log::info($req->all());
        $qP = Package::query()
            ->from('packages as p')
            ->with(['payment' => function ($query) use ($type) {
                // Apply the dynamic payer_type condition
                $query->where('payer_type', $type)
                ->where('is_deleted',0);
            }])
            ->with(['disbursement' => function ($query) use ($type) {
                // Apply the dynamic payer_type condition
                $query->where('payee_type', $type)
                ->where('is_deleted',0);
            }])
            ->where('p.is_deleted',0)
            ->join('users as d','d.id','p.driver_id')
            ->join('tracking_statuses as ts','ts.id','p.status_id')
            ->join('users as m','m.id','p.merchant_id')
            ->whereIn('p.status_id',[9,19])
            ->select([
                'p.extra_charge','p.additional_fee','p.remarks','p.cod','p.price','p.price_khr','d.phone as driver_phone','p.taxi_fee','p.payer','p.delivery_fee','p.assign_driver_datetime',
                'p.merchant_total','m.username as merchant_name','m.phone as merchant_phone','d.username as driver_name','p.status_id','p.id as package_id','d.id as driver_id',
                'p.qr_code','ts.name as status_code','p.delivered_datetime','p.failed_datetime','p.zone_code','p.receiver_phone','p.delivery_type','p.zone_name',
                'p.receiver_address','p.driver_cod_usd','p.driver_cod_khr','p.other_fee','p.arrive_warehouse_datetime'
            ]);
            if($type == 'driver'){
                $qP->whereNotExists(function ($sub) use ($type) {
                    $sub->select(DB::raw(1))
                        ->from('payment_packages as pp')
                        ->whereColumn('pp.package_id', 'p.id')
                        ->where('pp.payer_type', 'driver')
                        ->where('pp.is_deleted', false);
                })
                ->whereNotExists(function ($sub) use ($type) {
                    $sub->select(DB::raw(1))
                        ->from('disbursement_packages as dp')
                        ->whereColumn('dp.package_id', 'p.id')
                        ->where('dp.payee_type', 'driver')
                        ->where('dp.is_deleted', false);
                });
            }

            if ($type === 'merchant') {
                if ($pmtStatusId == 1) { // Unpaid
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
                } elseif ($pmtStatusId == 2) { // Paid
                    $qP->where(function ($qP){
                            $qP->whereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('payment_packages as pp')
                                ->whereColumn('pp.package_id', 'p.id')
                                ->where('pp.payer_type', 'merchant')
                                ->where('pp.is_deleted', false);
                        })->orWhereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('disbursement_packages as dp')
                                ->whereColumn('dp.package_id', 'p.id')
                                ->where('dp.payee_type', 'merchant')
                                ->where('dp.type','payment')
                                ->where('dp.is_deleted', false);
                        });
                    });
                }
            }


        if($driverId || $merchantId){
            if($type == 'driver') {
                $qP->where('p.driver_id',$driverId);
            }
            else {
                $qP->where('p.merchant_id',$merchantId);
            }
        }
        if($search){
            $qP->where('p.qr_code',$search);
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
                    ->whereBetween('p.delivered_datetime',[
                        $startDateTime,$endDateTime
                    ])
                    ->orWhere('p.status_id', 19)
                    ->whereBetween('p.failed_datetime',[
                        $startDateTime,$endDateTime
                    ]);
                    // ->whereRaw('p.delivered_datetime >= ? AND p.delivered_datetime <= ?', [$startDateTime, $endDateTime]);
                });
                // Check for status_id = 19, failed_datetime should be within the date range
                // ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                //     $q->where('p.status_id', 19)
                //     ->whereBetween('p.failed_datetime',[
                //         $startDateTime,$endDateTime
                //     ]);
                //     // ->whereRaw('p.failed_datetime >= ? AND p.failed_datetime <= ?', [$startDateTime, $endDateTime]);
                // });
            });
        }

        $statusKey = $type.'_payment_status';

        // $clbMapper = null;
        $clbMapper = function($package) use($statusKey,$type){
            $cod = $package->cod;
            $package->cod = $cod ? 'Yes' : 'No';
            // $package->{$statusKey} = (!$package->{$type.'_payment_id'} && !$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
            if($type == 'merchant') $package->{$statusKey} = ($package->payment || $package-> disbursement) ? 'Paid':'Unpaid';
            $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
            $package->delivered_datetime = Helper::formatCustomDateTime($package->assign_driver_datetime);
            $package->{$type.'_total'} = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            if($type == 'merchant') $package->total = -self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            $taxiFee = self::getTaxiFee($package->taxi_fee,$package->status_id,$package->payer,'merchant');
            $total = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$taxiFee,$package->other_fee,$package->payer,$package->status_id);
            // if($package->status_id == 19){
            //     if($type == 'merchant'){
            //         $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
            //         $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer):0;
            //     }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->extra_charge : 0;
            // }
            $package->total = $total['total_usd'];
            $package->total_khr = $total['total_khr'];
            
            $package->fee = Helper::getNumber($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
            return $package;
        };
        return DataResponse::PaginationV1($qP,$req,'',[],1000,$clbMapper);
    }

    public static function getTaxiFee($taxiFee,$statusId,$payer,$targetUser){
        if($targetUser == 'driver'){
            return $statusId == 9 && $payer == 'sender' ? $taxiFee : 0;
        }else if($targetUser == 'merchant'){
            return $statusId == 9 && $payer == 'sender' ? $taxiFee : 0;
        }
        return 0;
    }

    public function getDeliveryPackagesV1(Request $req,$type,$user){
        $driverId = $req->driver_id;
        $merchantId = $req->merchant_id;
        $pmtStatusId = $req->payment_status_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $search = $req->search;
        $qP = Package::query()
            ->from('packages as p')
            ->with(['payment' => function ($query) use ($type) {
                // Apply the dynamic payer_type condition
                $query->where('payer_type', $type)
                ->where('is_deleted',0);
            }])
            ->with(['disbursement' => function ($query) use ($type) {
                // Apply the dynamic payer_type condition
                $query->where('payee_type', $type)
                ->where('is_deleted',0);
            }])
            ->where('p.is_deleted',0)
            ->join('users as d','d.id','p.driver_id')
            ->join('tracking_statuses as ts','ts.id','p.status_id')
            ->join('users as m','m.id','p.merchant_id')
            ->whereIn('p.status_id',[9,19])
            ->select([
                'p.extra_charge','p.additional_fee','p.remarks','p.cod','p.price','p.price_khr','d.phone as driver_phone','p.taxi_fee','p.payer','p.delivery_fee','p.assign_driver_datetime',
                'p.merchant_total','m.username as merchant_name','m.phone as merchant_phone','d.username as driver_name','p.status_id','p.id as package_id','d.id as driver_id',
                'p.qr_code','ts.name as status_code','p.delivered_datetime','p.failed_datetime','p.zone_code','p.receiver_phone','p.delivery_type','p.zone_name',
                'p.receiver_address','p.driver_cod_usd','p.driver_cod_khr','p.other_fee','p.original_driver_cod_usd','p.original_driver_cod_khr','p.method'
            ]);
            if ($pmtStatusId == 1) { // Unpaid
                $qP->whereNotExists(function ($sub) use($type) {
                    $sub->select(DB::raw(1))
                        ->from('payment_packages as pp')
                        ->whereColumn('pp.package_id', 'p.id')
                        ->where('pp.payer_type', $type)
                        ->where('pp.is_deleted', false);
                })->whereNotExists(function ($sub) use($type) {
                    $sub->select(DB::raw(1))
                        ->from('disbursement_packages as dp')
                        ->whereColumn('dp.package_id', 'p.id')
                        ->where('dp.payee_type', $type)
                        ->where('dp.type','payment')
                        ->where('dp.is_deleted', false);
                });
            } elseif ($pmtStatusId == 2) { // Paid
                $qP->where(function ($q) use ($type) {
                    $q->whereExists(function ($sub) use ($type) {
                        $sub->select(DB::raw(1))
                            ->from('payment_packages as pp')
                            ->whereColumn('pp.package_id', 'p.id')
                            ->where('pp.payer_type', $type)
                            ->where('pp.is_deleted', false);
                    })->orWhereExists(function ($sub) use ($type) {
                        $sub->select(DB::raw(1))
                            ->from('disbursement_packages as dp')
                            ->whereColumn('dp.package_id', 'p.id')
                            ->where('dp.payee_type', $type)
                            ->where('dp.type','payment')
                            ->where('dp.is_deleted', false);
                    });
                });
            }

        if(!$search){
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

            if($driverId || $merchantId){
                if($type == 'driver') {
                    $qP->where('p.driver_id',$driverId);
                }
                else {
                    $qP->where('p.merchant_id',$merchantId);
                }
            }
        }

        else{
            $qP->where('p.qr_code',$search);
        }

        $statusKey = $type.'_payment_status';
        $qP->orderByDesc('arrive_warehouse_datetime');
        // $clbMapper = null;
        $clbMapper = function($package) use($statusKey,$type){
            $cod = $package->cod;
            $package->cod = $cod ? 'Yes' : 'No';
            $package->has_paid = ($package->payment || $package->disbursement) ? true : false;
            if($type == 'driver'){
                $package->payment_status = $package->payment ? 'Paid' : ' Unpaid';
            }
            // $package->{$statusKey} = (!$package->{$type.'_payment_id'} && !$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
            if($type == 'merchant') $package->{$statusKey} = ($package->payment || $package->disbursement) ? 'Paid':'Unpaid';
            $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
            $package->delivered_datetime = Helper::formatCustomDateTime($package->assign_driver_datetime);
            // $taxiFee = !($package->status_id === TrackingStatus::FAILED_WITH_FEE->value) ? $package->taxi_fee : 0;
            $taxiFee = self::getTaxiFee($package->taxi_fee,$package->status_id,$package->payer,'merchant');
            $total = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$taxiFee,$package->other_fee,$package->payer,$package->status_id);
            $package->{$type.'_total_usd'} = $package->status_id == 19 && $package->payer == 'receiver' ? -$total['total_usd'] : $total['total_usd'];
            $package->{$type.'_total_khr'} = $total['total_khr'];
            // if($type == 'merchant') $package->total = -$total;
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->other_fee : 0;
                    $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->other_fee,$package->additional_fee,$package->delivery_fee,$package->payer):0;
                }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->other_fee : 0;
            }
            if(empty($package->method) || $package->method == PaymentMethod::COD->value){
                $package->original_driver_cod_usd = 0;
                $package->original_driver_cod_khr = 0;
            }
            $package->fee = Helper::getNumber($package->delivery_fee + $package->other_fee + $package->additional_fee,2);
            return $package;
        };
        return DataResponse::PaginationV1($qP,$req,'',[],1000,$clbMapper);
    }

    public function getMerchantDeliveryPackages(Request $req, object $authUser) {
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $paymentType = $req->query('paymentType');
        $search = $req->query('search');
        // Expressions for amount to be paid
        $totalFeeExpr = "
            SUM(
                CASE 
                    WHEN packages.payer = 'sender' 
                        THEN packages.delivery_fee + packages.other_fee 
                            + CASE WHEN packages.status_id = 19 THEN 0 ELSE packages.taxi_fee END
                    ELSE 0
                END
            )
        ";

        $amountUsdExpr = "
            CASE
                WHEN SUM(packages.driver_cod_usd) >= {$totalFeeExpr}
                    THEN SUM(packages.driver_cod_usd) - {$totalFeeExpr}
                WHEN SUM(packages.driver_cod_usd) < {$totalFeeExpr}
                    AND SUM(packages.driver_cod_khr) >= ({$totalFeeExpr} - SUM(packages.driver_cod_usd)) * 4000
                    THEN 0
                ELSE SUM(packages.driver_cod_usd) - (({$totalFeeExpr} - SUM(packages.driver_cod_usd)) * 4000 - SUM(packages.driver_cod_khr))/4000
            END
        ";

        $amountKhrExpr = "
            CASE
                WHEN SUM(packages.driver_cod_usd) >= {$totalFeeExpr}
                    THEN SUM(packages.driver_cod_khr)
                WHEN SUM(packages.driver_cod_usd) < {$totalFeeExpr}
                    AND SUM(packages.driver_cod_khr) >= ({$totalFeeExpr} - SUM(packages.driver_cod_usd)) * 4000
                    THEN SUM(packages.driver_cod_khr) - ({$totalFeeExpr} - SUM(packages.driver_cod_usd)) * 4000
                ELSE 0
            END
        ";


        // $amountUsdExpr = "
        //     CASE
        //         WHEN SUM(packages.driver_cod_usd) >= SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee)
        //             THEN SUM(packages.driver_cod_usd) - SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee)
        //         WHEN SUM(packages.driver_cod_usd) < SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee)
        //             AND SUM(packages.driver_cod_khr) >= (SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee) - SUM(packages.driver_cod_usd)) * 4000
        //             THEN 0
        //         ELSE SUM(packages.driver_cod_usd) - ((SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee) - SUM(packages.driver_cod_usd)) * 4000 - SUM(packages.driver_cod_khr))/4000
        //     END
        // ";

        // $amountKhrExpr = "
        //     CASE
        //         WHEN SUM(packages.driver_cod_usd) >= SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee)
        //             THEN SUM(packages.driver_cod_khr)
        //         WHEN SUM(packages.driver_cod_usd) < SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee)
        //             AND SUM(packages.driver_cod_khr) >= (SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee) - SUM(packages.driver_cod_usd)) * 4000
        //             THEN SUM(packages.driver_cod_khr) - (SUM(packages.delivery_fee + packages.other_fee + packages.taxi_fee) - SUM(packages.driver_cod_usd)) * 4000
        //         ELSE 0
        //     END
        // ";

        $select = [
            'merchant_id',
            DB::raw("COUNT(packages.id) as package_count"),
            DB::raw("SUM(packages.price) as total_price"),
            DB::raw("SUM(packages.price_khr) as total_price_khr"),
            // DB::raw("SUM(CASE WHEN packages.payer = 'sender' THEN packages.delivery_fee ELSE 0 END) as delivery_fee"),
            // DB::raw("SUM(CASE WHEN packages.payer = 'sender' THEN packages.other_fee ELSE 0 END) as other_fee"),
            DB::raw("
                SUM(
                    CASE WHEN packages.payer = 'sender' THEN packages.delivery_fee ELSE 0 END +
                    CASE WHEN packages.payer = 'sender' THEN packages.other_fee ELSE 0 END
                ) as fees
            "),
            DB::raw("SUM(CASE WHEN packages.payer = 'sender' THEN packages.taxi_fee ELSE 0 END) as taxi_fee"),
            DB::raw('SUM(packages.driver_cod_khr) as total_driver_cod_khr'),
            DB::raw("array_agg(DISTINCT packages.id) as package_ids"),
            DB::raw('SUM(packages.driver_cod_usd) as total_driver_cod_usd'),
            DB::raw("
                CASE
                    WHEN packages.status_id = 9 THEN DATE(packages.delivered_datetime)
                    WHEN packages.status_id = 19 THEN DATE(packages.failed_datetime)
                END as finish_date
            "),
            DB::raw("MAX(d.id) as disbursement_id"),
            DB::raw("MAX(d.payment_status_id) as disbursement_status_id"),
            DB::raw("MAX(p.id) as payment_id"),
            DB::raw("MAX(p.payment_status_id) as payment_status_id"),
            DB::raw("($amountUsdExpr) as amount_to_be_paid_usd"),
            DB::raw("($amountKhrExpr) as amount_to_be_paid_khr"),
        ];

        $qP = Package::query()
            ->where('packages.is_deleted', false)
            ->whereIn('packages.status_id', [9, 19])
            ->leftJoin('disbursement_packages as dp', function($join) {
                $join->on('packages.id', '=', 'dp.package_id')
                    ->where('dp.is_deleted', false)
                    ->where('dp.type', 'payment');
            })
            ->leftJoin('disbursements as d', function($join) {
                $join->on('dp.disbursement_id', '=', 'd.id')
                    ->where('d.payee_type','merchant')
                    ->where('d.is_deleted', false);
            })
            ->leftJoin('payment_packages as pp', function($join) {
                $join->on('packages.id', '=', 'pp.package_id')
                    ->where('pp.is_deleted', false);
            })
            ->leftJoin('payments as p', function($join) {
                $join->on('pp.payment_id', '=', 'p.id')
                    ->where('p.payer_type','merchant');
            })
            ->where(function($query) {
                $query->where(function ($q) {
                    $q->whereNull('dp.id')
                    ->orWhereIn('d.payment_status_id', [PaymentStatus::PARTIAL->value, PaymentStatus::DECLINED->value])
                    ->orWhereNull('d.id');
                });
                $query->where(function ($q) {
                    $q->whereNull('pp.id')
                    ->orWhereIn('p.payment_status_id', [PaymentStatus::PARTIAL->value, PaymentStatus::DECLINED->value])
                    ->orWhereNull('p.id');
                });
            })
            ->with(['merchant:id,username,code', 'merchant.primaryBank'])
            ->select($select)
            ->groupBy('merchant_id', 'finish_date')
            ->when($paymentType === TransactionType::TRANSFER_IN->value, fn($q) =>
                $q->havingRaw("($amountUsdExpr) < 0 OR ($amountKhrExpr) < 0")
            )
            ->when($paymentType === TransactionType::TRNASFER_OUT->value, fn($q) =>
                $q->havingRaw("($amountUsdExpr) > 0 OR ($amountKhrExpr) > 0")
            )
            ->orderBy('finish_date', 'desc');

        // Date filter
        if(!$search){
            if ($startDate && $endDate) {
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qP->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->where('status_id', 9)
                            ->whereDate('delivered_datetime', '>=', $startDate)
                            ->whereDate('delivered_datetime', '<=', $endDate);
                    })->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('status_id', 19)
                            ->whereDate('failed_datetime', '>=', $startDate)
                            ->whereDate('failed_datetime', '<=', $endDate);
                    });
                });
            }
        }
        else{
            $qP->whereHas('merchant', function($q) use($search){
                $q->where('username', 'ilike', "%$search%")
                ->orWhere('code', 'ilike', "%$search%")
                ->orWhere('phone', 'ilike', "%$search%");
            });
        }

        $callback = function ($q) {
            $q->amount_to_be_paid_usd = Helper::getNumber($q->amount_to_be_paid_usd, 2, true);
            $q->amount_to_be_paid_khr = Helper::getNumber($q->amount_to_be_paid_khr, 2, true);
            $q->code = $q->merchant->code;
            $q->merchant_name = $q->merchant->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->finish_date = Helper::dateDMY($q->finish_date,'d-m-Y');
             $q->transaction_type = TransactionType::TRNASFER_OUT->value;
            if ($q->amount_to_be_paid_khr < 0 || $q->amount_to_be_paid_usd < 0) {
                $q->transaction_type = TransactionType::TRANSFER_IN->value;
            }

            if ($bankInfo = $q->merchant->primaryBank) {
                $q->bank_name = $bankInfo->bank_name;
                $q->bank_account_number = $bankInfo->bank_number;
                $q->bank_account_name = $bankInfo->account_name;
            }

            $q->status = isset($q->disbursement_id)
                ? PaymentStatus::tryFrom($q->disbursement_status_id)->label()
                : (isset($q->payment_id) ? PaymentStatus::tryFrom($q->payment_status_id)->label() : 'Unpaid');
            unset($q->merchant);
            return $q;
        };

        return DataResponse::PaginationV1($qP, $req, '', [], 1000, $callback, $select);
    }


    static function getTrxDetailsV1($rows, $pmtId, $pmtBillings = null)
    {
        foreach ($rows as $row) {
            if ($row->id == $pmtId) {
                $row->breakdown_notes = str_replace(['|', 'USD '], [' & ', '$'], $row->breakdown_notes);
                $row->breakdown_notes = preg_replace('/KHR (\d+)/', '$1៛', $row->breakdown_notes);
                // 🛠 Fix: Collect unique methods
                $methods = [];
                if (isset($pmtBillings[$row->id])) {
                    // Log::info($pmtBillings[$row->id]);
                    foreach ($pmtBillings[$row->id] as $billing) {

                        if (!in_array($billing->method, $methods)) {
                            if($billing->method == 'cash') $billing->method = 'Cash';
                            $methods[] = $billing->method;
                        }
                    }
                }

                // 🛠 Concat methods into a string
                $pmtMethod = implode(', ', $methods);
                $row->payment_method = PaymentMethod::tryFrom($pmtMethod)?->label() ?? $pmtMethod;
                $row->payment_date = Helper::dateDMY($row->payment_datetime, 'd M Y');
                $row->payment_time = Helper::formatCustomDateTime($row->payment_datetime, 'h:i A');
                $row->payment_datetime = Helper::formatCustomDateTime($row->payment_datetime,'d M Y h:i A');
                // $row->payable_amount = Helper::currencyAmount($row->received_amount_usd,'USD').'|'.Helper::currencyAmount($row->received_amount_khr,'KHR');
                $parts = [];
                $received_amount_usd = Helper::currencyAmount($row->received_amount_usd, 'USD');
                $received_amount_khr = Helper::currencyAmount($row->received_amount_khr, 'KHR');

                $row->received_amount_khr = $received_amount_khr;
                $row->received_amount_usd = $received_amount_usd;
                if (!empty($row->received_amount_usd)) {
                    $parts[] = $received_amount_usd;
                }
                if (!empty($row->received_amount_khr)) {
                    $parts[] = $received_amount_khr;
                }
                $row->paid_amount = implode(' | ', $parts);

                return $row;
            }
        }
        return null;
    }

    //* type must be one of driver or merchant
    public function receiveDriverSettleAmount(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::receivePaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payerId = $inputs['driver_id'] ?? $inputs['merchant_id'];
        $packageIds = $inputs['packages'];
        $packages = $this->getBulkPaymentPackages($packageIds,$type,[$payerId]);
        $clPkg = clone $packages;
        $currency = $inputs['currency'] ?? 'USD';
        $packageKeyById = $clPkg->keyBy('id');
        $validPackages = $this->validBulkPackagesV1($packageKeyById,$packageIds,$payerId,$type,true);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmtUSD = 0;
        $dueAmtKHR = 0;
        if($currency === Currency::KHR->value){
            $dueAmtKHR = $validPackages->data['total_due_amount_khr'];
        } else {
            $dueAmtUSD = $validPackages->data['total_due_amount_usd'];
        }
        $markSettle = (!empty($inputs['mark_settle']) && $inputs['mark_settle'] == '1') ? true : false;
        if(($dueAmtKHR + $dueAmtUSD) < 0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive not disbursement'
        ]));
        $method = $inputs['method'] ?? null;
        $methodName = PaymentMethod::tryFrom($method)->label();
        $validPayment = $this->validPaymentV2($dueAmtUSD,$dueAmtKHR,$bankAmount,$bankAmountKh,$bankAmount,$bankAmountKh);
        if($validPayment->error) return $validPayment;
        $breakDownNotes = null;
        if(($dueAmtKHR + $dueAmtUSD)  > 0){
            if($cash > 0) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh > 0) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount > 0) $breakDownNotes .= $methodName.': USD '.$bankAmount.'|';
            if($bankAmountKh > 0) $breakDownNotes .= $methodName.': KHR '.$bankAmountKh.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
        DB::beginTransaction();
        try{
            $pmtArr = [
                'payer_id' => $payerId,
                'payer_type' => $type,
                'taxi_fee' => $validPackages->data['total_taxi_fee'],
                'delivery_fee' => $validPackages->data['total_delivery_fee'],
                'payable_amount' => $currency === Currency::USD->value ? $bankAmount : $bankAmountKh,
                'amount_due_usd' => $dueAmtUSD,
                'amount_due_khr' => $dueAmtKHR,
                'currency_code' => $currency,
                'received_amount_usd' => $bankAmount,
                'received_amount_khr' => $bankAmountKh,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $validPackages->data['total_amount'],
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->data['total_package'],
                'delivered_package_count' => $validPackages->data['delivered_package_count'],
                'update_uid' => $user->id,
                'cod_amount' => $validPackages->data['total_cod'],
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'received_datetime' => now(),
                'branch_id' => $user->branch_id
            ];
            if($type == 'merchant'){
                $pmtArr['is_approved'] = 1;
                $pmtArr['is_settled'] = 1;
                $pmtArr['settled_uid'] = $user->id;
                $pmtArr['approved_uid'] = $user->id;
                $pmtArr['approved_datetime'] = now();
                $pmtArr['settled_datetime'] = now();
            }
            if($markSettle){
                $pmtArr['is_approved'] = 1;
                $pmtArr['is_settled'] = 1;
                $pmtArr['settled_uid'] = $user->id;
                $pmtArr['approved_uid'] = $user->id;
                $pmtArr['approved_datetime'] = now();
                $pmtArr['settled_datetime'] = now();
            }
            $createPayment = Payment::create($pmtArr);
            $paymentId = $createPayment->id;
            $trx = self::transactionCodeGenerator('transaction_sequences','payment','payments','trx_code',$user->branch_id,$user->company_id,$paymentId);
            if($bankAmount > 0){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => $method,
                    'amount' => $bankAmount,
                    'method_type' => $inputs['method_type'] ?? 'bank',
                    'original_amount' => $bankAmount,
                    'currency_code' => 'USD'
                ]);
            }

            if($bankAmountKh > 0){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => $method,
                    'amount' => $bankAmountKh,
                    'original_amount' => $bankAmount,
                    'method_type' => $inputs['method_type'] ?? 'bank',
                    'currency_code' => 'KHR'
                ]);
            }

            // $fkField = [
            //         $type.'_payment_id' => $paymentId
            // ];
            Package::whereIn('id',$packageIds)->update([
                'method' => $method,
                'original_driver_cod_usd' => DB::raw('driver_cod_usd'),
                'original_driver_cod_khr' => DB::raw('driver_cod_khr')
            ]);
            $paymentPackageArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'payment_id' => $paymentId,
                'type' => 'payment',
                'payer_type' => $type,
            ])->toArray();
            PaymentPackage::insert($paymentPackageArr);

            // $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payerId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payerId,
                'title' => __('messages.info',[
                    'info' => 'Transaction',
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->data['total_package'].' packages have been processed for this payment.'
            ]);
            // $notif->sendNotificationByTopic($notifReq,$user);
            $queueFCMName = config('queue_job_names.'.config('app.env').'.notification');
            SendNotificationJob::dispatch($notifReq, $user)->onQueue($queueFCMName);
            $accountList = UserBank::where('is_deleted',false)
            ->select(['id','bank_name','account_name','bank_number as account_number','currency','user_id'])
            ->where('user_id',$payerId)->get();
            $dueAccount = MerchantTransactionServiceImpl::dueBankAccounts($accountList, $payerId, 'USD');
            if (empty($dueAccount)) {
                Log::error(__('messages.info', [
                    'info'   => "You have no bank account for {KHR or USD}",
                    'khInfo' => "អ្នកមិនមានគណនីសម្រាប់រូបិយប័ណ្ណ {KHR ឬ USD}"
                ]));
            }
            PaymentTransaction::insert([
                'currency' => $currency,
                'amount' => $currency == 'USD' ? $bankAmount : $bankAmountKh,
                'payment_date' => now(),
                'tran_via' => $method,
                'remarks' => $inputs['remarks'] ?? null,
                'payment_id' => $paymentId,
                'payment_ref' => $trx->code,
                'transaction_type' => TransactionType::TRANSFER_IN->value,
                'from_account' => !empty($dueAccount) ? $dueAccount['account_number'] : '',
                'payment_method' => $method,
                'to_account' => 'NG Account',
                'approved_uid' => $user->id,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'branch_id' => $user->branch_id,
                'company_id' => $user->company_id
            ]);
            Package::where('is_deleted',false)
                ->where('driver_id',$user->id)
                ->where('status_id',6)
                ->whereIn('id',$packageIds)
                ->update([
                    'method' => $method,
                    'original_driver_cod_usd' => DB::raw('driver_cod_usd'),
                    'original_driver_cod_khr' => DB::raw('driver_cod_khr')
                ]);
            DB::commit();
            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult([
                'payment_id' => $paymentId
            ],false,__('messages.created',[
                'info' => 'Payment',
                'khInfo' => 'ទទួលការបង់ប្រាក់'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
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

    public static function getPackageTotalV1(
        $type,
        $driverCODUsd,
        $driverCODKh,
        $baseFee,
        $taxiFee,
        $otherFee,
        $payer=null,
        $statusId=null,
        $exchangeRateBase = 4000
    ) {
        $fees = $baseFee + $otherFee;

        $totalUsd = max(0, $driverCODUsd);
        $totalKhr = max(0, $driverCODKh);
        // helper closure to deduct in USD first, then KHR

        if ($type === 'driver') {
            // 1. Always deduct taxi fee
            // 2. Deduct fees if payer is receiver
            if ($fees > 0 || $taxiFee > 0) {
                if($payer == 'sender'){
                    $fees = 0;
                }
                if($statusId === TrackingStatus::FAILED_WITH_FEE->value){
                    $taxiFee = 0;
                }
                Helper::deductAmountBase($totalUsd,$totalKhr,($fees + $taxiFee),$exchangeRateBase);
            }
        }
        if($type === 'merchant'){
            if ($payer === 'sender' && ($fees > 0 || $taxiFee > 0)) {
                Helper::deductAmountBase($totalUsd,$totalKhr,$fees + $taxiFee,$exchangeRateBase);
            }
        }
        return [
            'total_usd'  => Helper::getNumber($totalUsd, 2),
            'total_khr'  => Helper::getNumber($totalKhr, 2),
            'fees'       => Helper::getNumber($fees, 2),
            'taxi_fee'   => Helper::getNumber($taxiFee, 2),
            'other_fee'  => Helper::getNumber($otherFee, 2),
        ];
    }



    public function requestBulkPayments(Request $req){
        $validator = validator($req->all(),[
            'merchant_ids' => 'array',
            'amount'
        ]);
    }


    public function receivePaymentValidation(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'cash' => 'nullable|numeric',
            'cash_kh' => 'nullable|numeric',
            'bank_amount' => 'nullable|numeric',
            'bank_amount_kh' => 'nullable|numeric',
            'bank_id' => 'nullable|int',
            'method' => 'nullable|string',
            'remarks' => 'nullable|string|max:500',
            'currency' => 'nullable|string',
            'packages' => 'required|array',
            'exchange_rate' => 'nullable|numeric'
        ]);
    }

    public function receivePaymentValidationV1(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'cash_usd' => 'nullable|numeric',
            'cash_khr' => 'nullable|numeric',
            'bank_amount_usd' => 'nullable|numeric',
            'bank_amount_khr' => 'nullable|numeric',
            'bank_id' => 'nullable|int',
            'method' => 'nullable|string',
            'remarks' => 'nullable|string|max:500',
            'packages' => 'required|array',
            'exchange_rate' => 'nullable|numeric'
        ]);
    }

    //* type must be one of driver or merchant
    public function receivePaymentService(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::receivePaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payerId = $inputs['driver_id'] ?? $inputs['merchant_id'];
        $packageIds = $inputs['packages'];
        $validPackages = $this->validPackages($packageIds,$payerId,$type);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $currency = $inputs['currency'] ?? 'USD';
        $dueAmount = $validPackages->total_due_amount;
        if($dueAmount < 0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive not disbursement'
        ]));
        // $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        // if($validPayment->error) return $validPayment;
        $method = $inputs['method'] ?? null;
        // return $validPackages;
        if($method){
            $validPayment = $this->validPaymentWithMethodV1($cash,$cashKh,$bankAmount,$bankAmountKh,$method,$dueAmount,$exchangeRate,$currency);
        } else {
            $validPayment =  $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        }
        if($validPayment->error) return $validPayment;
        // return $validPayment;
        // if($validPayment->total_input_amount !== $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
        //     'info' => 'Total input amount must be $'.$dueAmount
        // ]));
        $paymentType = 'payment';
        $breakDownNotes = null;
        if($dueAmount > 0){
            if($cash > 0) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh > 0) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount > 0) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh > 0) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
        DB::beginTransaction();
        try{
            $pmtArr = [
                'payer_id' => $payerId,
                'payer_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'currency_code' => $currency,
                'received_amount_usd' => $bankAmount + $cashKh,
                'received_amount_khr' => $cashKh + $bankAmountKh,
                'paid_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $validPackages->total_amount,
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->delivered_package_count,
                'update_uid' => $user->id,
                'cod_amount' => $validPackages->total_cod,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'received_datetime' => now(),
                'branch_id' => $user->branch_id
            ];
            if($type == 'merchant'){
                $pmtArr['approved'] = 1;
                $pmtArr['is_settled'] = 1;
                $pmtArr['settled_uid'] = $user->id;
                $pmtArr['approved_uid'] = $user->id;
                $pmtArr['approved_datetime'] = now();
                $pmtArr['settled_datetime'] = now();
            }
            $createPayment = Payment::create($pmtArr);
            $paymentId = $createPayment->id;
            self::transactionCodeGenerator('transaction_sequences',$paymentType,'payments','trx_code',$user->branch_id,$user->company_id,$paymentId);
            if($cash && $dueAmount > 0){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh && $dueAmount > 0){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }

            if($bankId){
                if($bankAmount > 0 && $dueAmount > 0){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh > 0 && $dueAmount > 0){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }

            $paymentPackageArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'payment_id' => $paymentId,
                'type' => $paymentType,
                'payer_type' => $type,
            ])->toArray();
            PaymentPackage::insert($paymentPackageArr);

            // $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payerId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payerId,
                'title' => __('messages.info',[
                    'info' => 'Transaction',
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            // SendNotificationJob::dispatch($notifReq)->onQueue()
            $queueFCMName = config('queue_job_names.'.config('app.env').'.notification');
            SendNotificationJob::dispatch($notifReq, $user)->onQueue($queueFCMName);
            Package::whereIn('id',$packageIds)->update([
                'method' => $method
            ]);
            // $notif->sendNotificationByTopic($notifReq,$user);
            DB::commit();
            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment',
                'khInfo' => 'ទទួលការបង់ប្រាក់'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }
    

    public function receivePaymentServiceV1(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::receivePaymentValidationV1($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payerId = $inputs['driver_id'] ?? $inputs['merchant_id'];
        $packageIds = $inputs['packages'];
        $validPackages = $this->validPackagesV1($packageIds,$payerId,$type);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKHR = $inputs['cash_khr'] ?? 0;
        $cashUSD = $inputs['cash_usd'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmountUSD = $inputs['bank_amount_usd'] ?? 0;
        $bankAmountKHR = $inputs['bank_amount_khr'] ?? 0;
        $dueAmountUsd = $validPackages->total_due_amount_usd;
        $dueAmountKhr = $validPackages->total_due_amount_khr;
        // Log::info($req->all());
        $bankName = null;
        if($bankId) {
            $existsBank = Bank::where('is_deleted',0)->find($bankId);
            if(!$existsBank) return DataResponse::NotFound(__('messages.not_found',['info' =>'Bank']));
            $bankName = $existsBank->name;
        }
        if($dueAmountUsd < 0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive not disbursement'
        ]));
        $validPayment = $this->validPaymentV1($dueAmountUsd,$dueAmountKhr,$cashUSD,$cashKHR,null,$bankAmountUSD,$bankAmountKHR,$exchangeRate);
        if($validPayment->error){
            return $validPayment;
        }
        if(!($validPayment->isValidUSD && $validPayment->isValidKHR)){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Amount in USD must be '.$dueAmountUsd.' & KHR '.$dueAmountKhr
            ]));
        }
        // return DataResponse::ValidateFail(json_encode($validPayment));
        // if($validPayment->total_input_amount !== $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
        //     'info' => 'Total input amount must be $'.$dueAmount
        // ]));
        $paymentType = 'payment';
        $breakDownNotes = null;
        if (($dueAmountUsd || $dueAmountKhr) > 0) {
            $notes = [];

            if ($cashUSD > 0) {
                $notes[] = 'Cash: USD ' . $cashUSD;
            }
            if ($cashKHR > 0) {
                $notes[] = 'Cash: KHR ' . $cashKHR;
            }
            if ($bankAmountUSD > 0) {
                $notes[] = $bankName . ': USD ' . $bankAmountUSD;
            }
            if ($bankAmountKHR > 0) {
                $notes[] = $bankName . ': KHR ' . $bankAmountKHR;
            }
            $breakDownNotes = implode(' | ', $notes);
        }
        // if(($dueAmountUsd || $dueAmountKhr) > 0){
        //     if($cashUSD > 0) $breakDownNotes .= 'Cash: USD '.$cashUSD.' | ';
        //     if($cashKHR > 0) $breakDownNotes .= 'Cash: KHR '.$cashKHR.' | ';
        //     if($bankAmountUSD > 0) $breakDownNotes .= $bankName.': USD '.$bankAmountUSD.' | ';
        //     if($bankAmountKHR > 0) $breakDownNotes .= $bankName.': KHR '.$bankAmountKHR.' | ';
        // }
        // $breakDownNotes = trim($breakDownNotes, '| ');
        DB::beginTransaction();
        try{
            $pmtArr = [
                'payer_id' => $payerId,
                'payer_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                // 'payable_amount' => $dueAmount,
                // 'paid_amount' => $dueAmount,
                'amount_due_khr' => $dueAmountKhr,
                'amount_due_usd' => $dueAmountUsd,
                'received_amount_usd' => $bankAmountUSD + $cashUSD,
                'received_amount_khr' => $cashKHR + $bankAmountKHR,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $validPackages->total_amount,
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->delivered_package_count,
                'update_uid' => $user->id,
                'cod_amount' => $validPackages->total_cod,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'received_datetime' => now(),
                'branch_id' => $user->branch_id
            ];
            if($type == 'merchant'){
                $pmtArr['is_approved'] = 1;
                $pmtArr['is_settled'] = 1;
                $pmtArr['settled_uid'] = $user->id;
                $pmtArr['approved_uid'] = $user->id;
                $pmtArr['approved_datetime'] = now();
                $pmtArr['settled_datetime'] = now();
            }
            $createPayment = Payment::create($pmtArr);
            $paymentId = $createPayment->id;
            self::transactionCodeGenerator('transaction_sequences',$paymentType,'payments','trx_code',$user->branch_id,$user->company_id,$paymentId);

            // USD cash payment
            if ($cashUSD > 0) {
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashUSD,
                    'original_amount' => $cashUSD,
                    'currency_code' => 'USD'
                ]);
            }

            // KHR cash payment
            if ($cashKHR > 0) {
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashKHR,
                    'original_amount' => $cashKHR,
                    'currency_code' => 'KHR'
                ]);
            }

            // Bank payments
            if ($bankId) {
                // USD bank payment
                if ($bankAmountUSD > 0) {
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $bankName,
                        'amount' => $bankAmountUSD,
                        'original_amount' => $bankAmountUSD,
                        'currency_code' => 'USD'
                    ]);
                }

                // KHR bank payment
                if ($bankAmountKHR > 0) {
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $bankName,
                        'amount' => $bankAmountKHR,
                        'original_amount' => $bankAmountKHR,
                        'currency_code' => 'KHR'
                    ]);
                }
            }


            // if($cashUSD && $dueAmount > 0){
            //     PaymentDetail::create([
            //         'payment_id' => $paymentId,
            //         'method' => 'cash',
            //         'amount' => $cashUSD,
            //         'original_amount' => $cashUSD,
            //         'currency_code' => 'USD'
            //     ]);
            // }
            // if($cashKHR && $dueAmount > 0){
            //     PaymentDetail::create([
            //         'payment_id' => $paymentId,
            //         'method' => 'cash',
            //         'amount' => $cashKHR,
            //         'original_amount' => $validPayment->original_cash_amount_kh,
            //         'currency_code' => 'KHR'
            //     ]);
            // }

            // if($bankId){
            //     if($bankAmountUSD > 0 && $dueAmount > 0){
            //         PaymentDetail::create([
            //             'payment_id' => $paymentId,
            //             'method' => $validPayment->bank_name,
            //             'amount' => $bankAmountUSD,
            //             'original_amount' => $bankAmountUSD,
            //             'currency_code' => 'USD'
            //         ]);
            //     }

            //     if($bankAmountKHR > 0 && $dueAmount > 0){
            //         PaymentDetail::create([
            //             'payment_id' => $paymentId,
            //             'method' => $validPayment->bank_name,
            //             'amount' => $bankAmountKHR,
            //             'original_amount' => $validPayment->original_bank_amount_kh,
            //             'currency_code' => 'KHR'
            //         ]);
            //     }
            // }

            $paymentPackageArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'payment_id' => $paymentId,
                'type' => $paymentType,
                'payer_type' => $type,
            ])->toArray();
            PaymentPackage::insert($paymentPackageArr);

            $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payerId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payerId,
                'title' => __('messages.info',[
                    'info' => 'Transaction',
                    'khInfo' => 'Transaction'
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            DB::commit();
            // Log::info(json_encode($createPayment));
            // return Package::whereIn('id',$packageIds)->get();
            // Log::info(json_encode(PaymentDetail::where('payment_id',$paymentId)->get()));
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment',
                'khInfo' => 'ទទួលការបង់ប្រាក់'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    public function validPaymentV1(
        float $dueAmountUsd,
        float $dueAmountKhr,
        float $cashUSD = 0,
        float $cashKHR = 0,
        ?int $bankId = null,
        float $bankAmountUSD = 0,
        float $bankAmountKHR = 0,
        float $exchangeRate = 4000,
        bool $requireFullPayment = true // the flag
    ) {
        // Total paid amounts
        $totalPaidUSD = $cashUSD + $bankAmountUSD;
        $totalPaidKHR = $cashKHR + $bankAmountKHR;

        if ($totalPaidUSD < $dueAmountUsd) {
            $remainingUSD = $dueAmountUsd - $totalPaidUSD;
            $dueAmountKhr += $remainingUSD * $exchangeRate;
            $remainingUSD = 0; // After transfer to KHR, USD remaining is zero
        } else {
            $remainingUSD = 0;
        }

        $remainingKHR = max($dueAmountKhr - $totalPaidKHR, 0);

        $epsilon = 0.0001; // tolerance for float comparison
        $isValidUSD = $requireFullPayment ? (abs($remainingUSD) < $epsilon) : $totalPaidUSD > 0;
        $isValidKHR = $requireFullPayment ? (abs($remainingKHR) < $epsilon) : $totalPaidKHR > 0;

        if($remainingKHR > 0){
            $paymentSuggestion = $this->paymentSuggestionByCurrency(0,$cashKHR,0,$bankAmountKHR,$dueAmountKhr,'KHR',$exchangeRate);
            if($paymentSuggestion->error) return $paymentSuggestion;
        }

        // Log::info($totalPaidKHR.' --- '.$totalPaidUSD);
        $data = (object)[
            'error' => false,
            'totalPaidUSD' => $totalPaidUSD,
            'totalPaidKHR' => $totalPaidKHR,
            'remainingUSD' => $remainingUSD,
            'remainingKHR' => $remainingKHR,
            'isValidUSD' => $isValidUSD,
            'isValidKHR' => $isValidKHR,
            'bankId' => $bankId
        ];
        // Log::info(json_encode($data));
        return $data;
    }

    private function validPaymentWithMethodV1($cash,$cashKh,$bankAmount,$bankAmountKh,$method,$dueAmount,$exhangeRate,$currency=null): object{
        $bankName = null;
        if($bankAmount > 0 && !$method) return DataResponse::ValidateFail(__('messages.error',[
            'info' => 'Please enter bank',
            'khInfo' => 'សូមរើសធនាគារ'
        ]));
        if($bankAmountKh > 0 && !$method) return DataResponse::ValidateFail(__('messages.error',[
            'info' => 'Please enter bank',
            'khInfo' => 'សូមរើសធនាគារ'
        ]));
        if($method && (!$bankAmount && !$bankAmountKh)) return DataResponse::ValidateFail(__('messages.error',[
            'info' => 'Please enter bank amount in USD or KHR',
            'khInfo' => 'សូមបញ្ចូលប្រាក់បង់តាមធនាគារ'
        ]));
        if($method) {
            $existsBank = PaymentMethod::tryFrom($method);
            if(!$existsBank?->value) return DataResponse::NotFound(__('messages.not_found',[
                'info' =>'Bank'
            ]));
            $bankName = $existsBank->label();
        }
        $cashKhToUS = $cashKh / $exhangeRate;
        $bankAmountKhToUS = $bankAmountKh / $exhangeRate;
        $totalInputAmount = $cash + $cashKhToUS + $bankAmountKhToUS + $bankAmount;
        $totalInputAmount = floor($totalInputAmount * 100) / 100;
        // if($cash && $cashKh) return
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($dueAmount > 0){
            if($totalInputAmount <=0) return DataResponse::ValidateFail('Invalid payment amount');
            // Log::info('currency---'.$currency);
            $paymentSuggestion = !empty($currency) ? $this->paymentSuggestionByCurrency($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$currency,$exhangeRate,true):$this->paymentSuggestion($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$exhangeRate);
            if($paymentSuggestion->error) return $paymentSuggestion;
            $originalCashKh = $paymentSuggestion->original_cash_amount_kh;
            $originalBankAmtKh = $paymentSuggestion->original_bank_amount_kh;
        }
        return DataResponse::JsonRaw([
            'error' => false,
            'total_input_amount' => $totalInputAmount,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh,
            'bank_name' => $bankName
        ]);
    }




    public function receiveOrDisburesementV1(Request $req,$user,$type){
        $paymentType = $req->payment_type;
        if(!in_array($paymentType,['disbursement','receive']) || !$paymentType){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment type must be on of disbursement or receive'
            ]));
        }

        if($paymentType == 'disbursement'){
            return $this->disbursementPaymentV1($req,$user,$type);
        }else if($paymentType == 'receive'){
            return $this->receivePaymentServiceV1($req,$user,$type);
        }
    }


    public function receiveOrDisburesement(Request $req,$user,$type){
        $paymentType = $req->payment_type;
        if(!in_array($paymentType,['disbursement','receive']) || !$paymentType){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment type must be on of disbursement or receive'
            ]));
        }

        if($paymentType == 'disbursement'){
            return $this->disbursementPayment($req,$user,$type);
        }else if($paymentType == 'receive'){
            return $this->receivePaymentService($req,$user,$type);
        }
    }

    private function disburesementBulkV1Validator(Request $req,$type){
        return validator($req->all(), [
            'currency' => 'required|in:KHR,USD',
            $type.'s' => ['required', 'array'],
            $type.'s.*.id' => ['required', 'integer', 'exists:users,id'],
            $type.'s.*.transaction_type' => 'required|in:in,out',
            $type.'s.*.packages' => ['required', 'array', 'min:1'],
            $type.'s.*.packages.*' => ['integer', 'exists:packages,id']
        ]);
    }


    private function getPayInPackagesKeyByPackageId(array $packageIds,string $type):object{
        return PaymentPackage::where('is_deleted', false)
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
                        'type',
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
            ->keyBy('package_id');
    }


    private function getPayOutPackagesKeyByPackageId(array $packageIds,string $type):object{
        return DisbursementPackage::where('is_deleted', false)
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
                        'fast_delivery_rate',
                        'fast_pickup_rate',
                        'create_uid',
                        'branch_id',
                        'company_id'
                    )->where('is_deleted', false)
                    ->where('payee_type',$type)
                    ->whereIn('payment_status_id',[
                        PaymentStatus::DECLINED->value,
                        PaymentStatus::PARTIAL->value
                    ]);
                }
            ])
            ->get()
            ->keyBy('package_id');
    }

    private function getBulkPaymentPackages(array $packageIds,string $type,array $userIds){
        return Package::where('is_deleted',false)
        ->whereIn('status_id',[19,9])
        // ->with(['merchant:id,username,code'])
        ->whereIn("{$type}_id",$userIds)
        ->whereIn('id',$packageIds)
        ->get();
    }


    public function disburesementBulkV1(Request $req,$user,$type){
        $validator = $this->disburesementBulkV1Validator($req,$type);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        // Log::info($req->all());
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
        $payOutPackages = $this->getPayOutPackagesKeyByPackageId($packageIds,$type);
        $payInPackages = $this->getPayInPackagesKeyByPackageId($packageIds,$type);

        // return DataResponse::JsonResult($payOutPackages);
        foreach ($userInfo as $m) {
            $mId = $m['id'];
            $transactionType = $m['transaction_type'] ?? null;
            if(!$transactionType){
                return DataResponse::BadRequest('Transaction Type must be provided');
            }
            $packages = $m['packages'];
            // Validate packages
            $validPkg = $this->validBulkPackagesV1($packageKeyById, $packages, $mId, $type);
            if ($validPkg->error) {
                return $validPkg;
            }

            $totalDueUSD = $validPkg->data['total_due_amount_usd'] ?? 0;
            $totalDueKHR = $validPkg->data['total_due_amount_khr'] ?? 0;

            if (($totalDueUSD + $totalDueKHR) == 0) {
                $merchantName = $validPkg->data['merchant_name'];
                $merchantCode = $validPkg->data['merchant_code'];
                return DataResponse::Duplicated(__('messages.info', [
                    'info'   => "No payment is required for merchant {$merchantName} (ID: {$merchantCode}) because all packages have no due amount.",
                    'khInfo' => "មិនចាំបាច់បង់សម្រាប់អ្នកលក់ {$merchantName} (ID: {$merchantCode}) ពីព្រោះគ្រប់កញ្ចប់គ្មានប្រាក់ដែលត្រូវបង់ទេ។"
                ]));
            }

            $paidPackageIds   = [];
            $receivedUSD      = 0;
            $receivedKHR      = 0;
            $hasPmtId = false;
            $targetPmt = null;
            // $disbursementId = null;

            $allUSDReceived = true;
            $allKHRReceived = true;

            foreach ($packages as $pkgId) {
                if($transactionType === TransactionType::TRNASFER_OUT->value){
                    $res = $this->preparePayout($pkgId, 'merchant', $payingCurrency, $validPkg, $mId, $payOutPackages);
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
                    $res = $this->preparePayIn($pkgId, 'merchant', $payingCurrency, $validPkg, $mId, $payInPackages);
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
                $paidPackageIds[] = $pkgId;
            }
            // Log::info("{$allUSDReceived} --- {$allKHRReceived}");
            $paymentStatusId = ($allUSDReceived && $allKHRReceived)
                ? PaymentStatus::REQUESTED->value
                : PaymentStatus::PARTIAL->value;

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
                        // Log::info($col.' ==> '.$targetPmt->$col);
                    }
                }

                if($transactionType === TransactionType::TRNASFER_OUT->value){
                    $updatePmt['payee_id']          = $targetPmt->payee_id;
                    $updatePmt['payee_type']        = $targetPmt->payee_type;
                    $updatePmt['receiptionist_uid']        = $targetPmt->receiptionist_uid;
                    $updatePmt['delivery_rate']        = $targetPmt->delivery_rate;
                    $updatePmt['pickup_rate']        = $targetPmt->pickup_rate;
                    $updatePmt['fast_delivery_rate']        = $targetPmt->fast_delivery_rate;
                    $updatePmt['fast_pickup_rate']        = $targetPmt->fast_pickup_rate;
                    $updatePmt['pickup_package_count']  = $targetPmt->pickup_package_count;
                    $upSertPayout[] = $updatePmt;
                }

                if($transactionType === TransactionType::TRANSFER_IN->value){
                    $updatePmt['payer_id']          = $targetPmt->payer_id;
                    $updatePmt['payer_type']        = $targetPmt->payer_type;
                    $updatePmt['receiver_uid']        = $targetPmt->receiver_uid;
                    $upSertPayIn[] = $updatePmt;
                }
                // Log::info(json_encode($updatePmt));
                // $targetDisbursement->update($updatePmt);

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
                    'package_ids'            => json_encode($paidPackageIds),
                ];
                if($transactionType === TransactionType::TRNASFER_OUT->value){
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
            // $count = count($fullyPaidInfo);
            // $packages = implode(', ', array_column($fullyPaidInfo, 'package_id'));
            // $users = implode(', ', array_unique(array_column($fullyPaidInfo, "{$type}_name")));
            return DataResponse::Duplicated("All fully paid packages detected for {$type}(s): {$type}s.");
        }

        if (!empty($invalidAmountInfo)) {
            return DataResponse::Duplicated(__('messages.info', [
                'info'   => "Some packages have invalid negative received amounts.",
                'khInfo' => "មានកញ្ចប់មួយចំនួនមានចំនួនទឹកប្រាក់អវិជ្ជមាន។",
                // 'details' => json_encode($invalidAmountInfo)
            ]));
        }


        // Report currency conflicts
        if (!empty($currencyConflictInfo)) {
            // $count = count(array_unique(array_column($currencyConflictInfo, 'package_id')));
            $packages = implode(', ', array_column($currencyConflictInfo, 'package_id'));
            // $users = implode(', ', array_unique(array_column($currencyConflictInfo, "{$type}_name")));
            $currency = $currencyConflictInfo[0]['currency'] ?? '';
            return DataResponse::Duplicated("These packages already partially paid with {$currency} for {$type}(s): {$type}s.");
        }

        try {
            DB::beginTransaction();
            if(!empty($insertPayout) || !empty($upSertPayout)){
                $this->batchPayoutUpSert($insertPayout,$upSertPayout,$payingCurrency,$user);
            }
            if(!empty($insertPayIn) || !empty($upSertPayIn)){
                $this->batchPayInUpSert($insertPayIn,$upSertPayIn,$payingCurrency,$user);
            }
            DB::commit();
            // Log::info($insertPayout);
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

    private function preparePayIn(int $pkgId, string $type, string $payingCurrency, object $validPkg, int $mId, object $payOutPkgs){
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

        $payOutPkg = $payOutPkgs[$pkgId] ?? null;
        $validUsdAmt = abs($validPkg->data['total_due_amount_usd']); // abs for not sign when insert
        $validKhrAmt = abs($validPkg->data['total_due_amount_khr']); // abs for not sign when insert
        if ($payOutPkg && $payOutPkg->payment) {
            $payment = $payOutPkg->payment;
            // Log::info($payOutPkg);
            $usdDue = $payment->amount_due_usd - $payment->received_amount_usd;
            $khrDue = $payment->amount_due_khr - $payment->received_amount_khr;

            $result['target'] = $payment;
            $result['targetId'] = $payment->id;
            // Already fully paid
            if ($usdDue == 0 && $khrDue == 0) {
                $result['fullyPaidInfo'][] = [
                    'package_id'    => $pkgId,
                    "{$type}_id"    => $mId,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] ?? $mId,
                ];
                if ($payingCurrency === 'USD') {
                    $result['allUSDReceived'] = false;
                }
                if ($payingCurrency === 'KHR') {
                    $result['allKHRReceived'] = false;
                }
                return $result; // stop here
            }

            // Handle USD
            if ($payingCurrency === 'USD') {
                $result['receivedUSD'] = $validUsdAmt;
                if ($usdDue == $validUsdAmt) {
                    if ($khrDue == $validKhrAmt) {
                        $result['allKHRReceived'] = true;
                    }
                } else {
                    $result['allUSDReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'USD',
                        'message'       => $usdDue == 0
                            ? 'This package is already fully settled in USD.'
                            : 'This package has partial USD payment already, cannot pay again in USD.',
                    ];
                }
            }

            // Handle KHR
            if ($payingCurrency === 'KHR') {
                $result['receivedKHR'] = $validKhrAmt;
                if ($khrDue == $validKhrAmt) {
                    if ($usdDue == $validUsdAmt) {
                        $result['allUSDReceived'] = true;
                    }
                } else {
                    $result['allKHRReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'KHR',
                        'message'       => $khrDue == 0
                            ? 'This package is already fully settled in KHR.'
                            : 'This package has partial KHR payment already, cannot pay again in KHR.',
                    ];
                    return $result;
                }
            }
        } else {
            // No disbursement yet → validate amounts
            if ($payingCurrency === 'USD') {
                $receivedUSD = $validPkg->data['total_due_amount_usd'];
                $result['receivedUSD'] = $validUsdAmt;
                if ($receivedUSD > 0) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'USD',
                        'message'       => "Invalid amount: received USD ({$receivedUSD}) cannot be positive."
                    ];
                    return $result;
                }
                if ($validPkg->data['total_due_amount_khr'] > 0) {
                    $result['allKHRReceived'] = false;
                }
            }

            if ($payingCurrency === 'KHR') {
                $receivedKHR = $validPkg->data['total_due_amount_khr'];
                $result['receivedKHR'] = $validKhrAmt;
                if ($receivedKHR > 0) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'KHR',
                        'message'       => "Invalid amount: received KHR ({$receivedKHR}) cannot be positive."
                    ];
                    return $result;
                }
                if ($validPkg->data['total_due_amount_usd'] > 0) {
                    $result['allUSDReceived'] = false;
                }
            }
        }

        return $result;
    }


    private function preparePayout(int $pkgId, string $type, string $payingCurrency, object $validPkg, int $mId, object $disbursementPkg): array
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

        $disbPkg = $disbursementPkg[$pkgId] ?? null;
        $validUsdAmt = $validPkg->data['total_due_amount_usd'];
        $validKhrAmt = $validPkg->data['total_due_amount_khr'];
        if ($disbPkg && $disbPkg->disbursement) {
            $disbursement = $disbPkg->disbursement;
            $amountUsd = $disbursement->amount_due_usd ?? 0;
            $amountKhr = $disbursement->amount_due_khr ?? 0;
            $usdDue = $amountUsd - $disbursement->received_amount_usd;
            $khrDue = $amountKhr - $disbursement->received_amount_khr ?? 0;

            if($amountUsd > 0 && $usdDue == 0 && $payingCurrency === 'USD'){
                $result['currencyConflictInfo'][] = [
                    'package_id'    => $pkgId,
                    "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                    'currency'      => 'USD',
                    'message'       => $usdDue == 0
                        ? 'This package is already fully settled in USD.'
                        : 'This package has partial USD payment already, cannot pay again in USD.',
                ];
            }

            if($amountKhr > 0 && $khrDue == 0 && $payingCurrency === 'KHR'){
                $result['currencyConflictInfo'][] = [
                    'package_id'    => $pkgId,
                    "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                    'currency'      => 'KHR',
                    'message'       => $usdDue == 0
                        ? 'This package is already fully settled in KHR.'
                        : 'This package has partial KHR payment already, cannot pay again in KHR.',
                ];
            }

            $result['target'] = $disbursement;
            $result['targetId'] = $disbursement->id;
            // Already fully paid
            if ($usdDue == 0 && $khrDue == 0) {
                $result['fullyPaidInfo'][] = [
                    'package_id'    => $pkgId,
                    "{$type}_id"    => $mId,
                    "{$type}_name"  => $validPkg->data["{$type}_name"] ?? $mId,
                ];
                if ($payingCurrency === 'USD') {
                    $result['allUSDReceived'] = false;
                }
                if ($payingCurrency === 'KHR') {
                    $result['allKHRReceived'] = false;
                }
                // return $result; // stop here
            }

            // Handle USD
            if ($payingCurrency === 'USD') {
                $result['receivedUSD'] = $validUsdAmt;
                if ($usdDue == $validUsdAmt) {
                    if ($usdDue == $validKhrAmt) {
                        $result['allKHRReceived'] = true;
                    }
                } else {
                    $result['allUSDReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'USD',
                        'message'       => $usdDue == 0
                            ? 'This package is already fully settled in USD.'
                            : 'This package has partial USD payment already, cannot pay again in USD.',
                    ];
                }
            }

            // Handle KHR
            if ($payingCurrency === 'KHR') {
                $result['receivedKHR'] = $validKhrAmt;
                if ($khrDue == $validKhrAmt) {
                    if ($usdDue == $validUsdAmt) {
                        $result['allUSDReceived'] = true;
                    }
                } else {
                    $result['allKHRReceived'] = false;
                    $result['currencyConflictInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'KHR',
                        'message'       => $khrDue == 0
                            ? 'This package is already fully settled in KHR.'
                            : 'This package has partial KHR payment already, cannot pay again in KHR.',
                    ];
                    // return $result;
                }
            }
        } else {
            if($validKhrAmt === 0 && $validUsdAmt === 0){
                $result['invalidAmountInfo'][] = [
                    'package_id'    => $pkgId,
                    "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                    'currency'      => 'USD',
                    'message'       => "Invalid amount: there are no valid amount to be settle."
                ];
                // return $result;
            }
            // No disbursement yet → validate amounts
            if ($payingCurrency === 'USD') {
                $receivedUSD = $validUsdAmt;
                $result['receivedUSD'] = $validUsdAmt;
                if ($receivedUSD <= 0) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'USD',
                        'message'       => "Invalid amount: received USD ({$receivedUSD}) cannot be negative."
                    ];
                    // return $result;
                }
                if ($validKhrAmt > 0) {
                    $result['allKHRReceived'] = false;
                }
            }

            if ($payingCurrency === 'KHR') {
                $receivedKHR = $validKhrAmt;
                $result['receivedKHR'] = $validKhrAmt;
                if ($receivedKHR <= 0) {
                    $result['invalidAmountInfo'][] = [
                        'package_id'    => $pkgId,
                        "{$type}_name" => $validPkg->data["{$type}_name"] ?? $mId,
                        'currency'      => 'KHR',
                        'message'       => "Invalid amount: received KHR ({$receivedKHR}) cannot be negative."
                    ];
                    // return $result;
                }
                if ($validUsdAmt > 0) {
                    $result['allUSDReceived'] = false;
                }
            }
        }
        return $result;
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
            foreach ($insertPayin as $payIn) {
                // Insert into disbursements table (main payment record)
                // Log::info(json_encode($payIn));
                $payment = Payment::create($this->filterBulkPaymentColumns($payIn,$user,$payIn['transaction_type']));
                self::transactionCodeGenerator('transaction_sequences','payment','payments','trx_code',$user->branch_id,$user->company_id,$payment->id);
                // Attach each package to this payment
                $packageIds = json_decode($payIn['package_ids'], true);
                $insertPayInPkgs = [];
                foreach ($packageIds as $pkgId) {
                    $insertPayInPkgs[] = [
                        'payment_id' => $payment->id,
                        'package_id' => $pkgId,
                        'type' => 'payment',
                        'payer_type' => $payIn['payer_type'],
                        'is_deleted' => false,
                    ];
                }
                if(!empty($insertPayInPkgs)){
                    PaymentPackage::insert($insertPayInPkgs);
                }

                $insertPayinDetails = [];
                if (!empty($payIn['payments'])) {
                    foreach ($payIn['payments'] as $payment) {
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
                    'payment_status_id',
                    'payment_datetime',
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
                // Log::info(json_encode($disbData));
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
            'type','receiptionist_uid', 'failed_with_fee_count', 'trx_code', 'paid_amount', 'fast_delivery_rate',
            'payment_status_id','fast_pickup_rate','requested_date',
        ];
        if($tranType === TransactionType::TRNASFER_OUT->value){
            $allowed[] = 'payee_id';
            $allowed[] = 'payee_type';
        }

        if($tranType === TransactionType::TRANSFER_IN->value){
            $allowed[] = 'payer_id';
            $allowed[] = 'payer_type';
            $allowed[] = 'receiver_uid';
        }

        $out = [
            'payment_datetime' => now(),
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


    public function getDriverCommissions($user,$driverId){
        $driver = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('code,username,employment_date,shift_type,salary')
        ->where('account_type','driver')->find($driverId);
        if(!$driver) return DataResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        $driverCommissions = DriverCommission::where('driver_id',$driverId)->where('is_deleted',0)->orderByDesc('id')->get();
        foreach($driverCommissions as $driverComm){
            if($driverComm->delivery_type == 'fast'){
                $dc->fast_pickup_commission = $driverComm->pickup_commission;
                $dc->fast_delivery_commission = $driverComm->delivery_commission;
            }
            if($driverComm->delivery_type == 'normal'){
                $dc->normal_pickup_commission = $driverComm->pickup_commission;
                $dc->normal_delivery_commission = $driverComm->delivery_commission;
            }
        }
        foreach($dc as $key=>$d){
            $driver->{$key} = $dc->{$key};
        }

        return DataResponse::JsonResult($driver);
    }

    public static function amountToOneCurrency($currencyCode,$cashUSD,$cashKHR,$bankUSD,$bankKHR,$exchangeRate = 4000){
        if($currencyCode == 'USD'){
            $cashKHRToUSD = $cashKHR / $exchangeRate;
            $bankKHRToUSD = $bankKHR / $exchangeRate;
            return [
                'total' => Helper::getNumber($cashUSD + $bankUSD + $cashKHRToUSD + $bankKHRToUSD),
                'cash' => Helper::getNumber($cashUSD + $cashKHRToUSD),
                'bank' => Helper::getNumber($bankUSD  + $bankKHRToUSD)
            ];
        }
        return null;
    }

    private function validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exhangeRate){
        $bankName = null;
        if($bankAmount > 0 && !$bankId) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank']));
        if($bankAmountKh > 0 && !$bankId) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank']));
        if($bankId && (!$bankAmount && !$bankAmountKh)) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank amount in USD or KHR']));
        if($bankId) {
            $existsBank = Bank::where('is_deleted',0)->find($bankId);
            if(!$existsBank) return DataResponse::NotFound(__('messages.not_found',['info' =>'Bank']));
            $bankName = $existsBank->name;
        }
        $cashKhToUS = $cashKh / $exhangeRate;
        $bankAmountKhToUS = $bankAmountKh / $exhangeRate;
        $totalInputAmount = $cash + $cashKhToUS + $bankAmountKhToUS + $bankAmount;
        $totalInputAmount = floor($totalInputAmount * 100) / 100;
        // if($cash && $cashKh) return
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($dueAmount > 0){
            if($totalInputAmount <=0) return DataResponse::ValidateFail('Invalid payment amount');
            $paymentSuggestion = $this->paymentSuggestion($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$exhangeRate);
            if($paymentSuggestion->error) return $paymentSuggestion;
            $originalCashKh = $paymentSuggestion->original_cash_amount_kh;
            $originalBankAmtKh = $paymentSuggestion->original_bank_amount_kh;
        }
        return DataResponse::JsonRaw([
            'error' => false,
            'total_input_amount' => $totalInputAmount,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh,
            'bank_name' => $bankName
        ]);
    }


    public function paymentSuggestionByCurrency(
        $cash,
        $cashKh,
        $bankAmount,
        $bankAmountKh,
        $dueAmount,
        $currency,
        $exchangeRate,
        $allowPartial = false // new param
    ) {
        $dueAmount = (float)Helper::getNumber($dueAmount, 2);
        $totalAmountUSD = (float)Helper::getNumber($cash + $bankAmount, 2);
        $totalAmountKHR = (float)Helper::getNumber($cashKh + $bankAmountKh, 2);

        $hasMoreThanTwoDecimals = function ($amount) {
            $amountStr = (string)$amount;
            if (strpos($amountStr, '.') !== false) {
                $decimalPart = explode('.', $amountStr)[1];
                return strlen($decimalPart) > 2;
            }
            return false;
        };

        if (
            $hasMoreThanTwoDecimals($cash) ||
            $hasMoreThanTwoDecimals($cashKh) ||
            $hasMoreThanTwoDecimals($bankAmount) ||
            $hasMoreThanTwoDecimals($bankAmountKh) ||
            $hasMoreThanTwoDecimals($dueAmount)
        ) {
            return DataResponse::ValidateFail(__('messages.info', [
                'info' => 'Your input amount includes more than two decimal digits',
                'khInfo' => 'សូមបញ្ចូលទឹកប្រាក់ដែល​មានទសភាគមិនលើសពីពីរខ្ទង់'
            ]));
        }

        $originalCashKh = 0;
        $originalBankAmtKh = 0;

        if ($currency === 'USD') {
            if ($totalAmountUSD && $totalAmountKHR) {
                $totalAmountKHR_to_USD = $totalAmountKHR / $exchangeRate;
                $totalAllAmt = Helper::getNumber($totalAmountUSD + $totalAmountKHR_to_USD, 2);

                if (!$allowPartial) {
                    if ($totalAllAmt > $dueAmount) {
                        return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $' . $dueAmount . ' in total');
                    }
                }

                $remainingAmt = abs($totalAmountUSD - $dueAmount);
                $totalSuggestionAmt_KH = $remainingAmt * $exchangeRate;

                $suggestionAmtBankKh = abs($totalSuggestionAmt_KH - $bankAmountKh);
                $suggestionAmtCashKh = abs($totalSuggestionAmt_KH - $cashKh);

                if ($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh;
                if ($cashKh) $originalCashKh += $suggestionAmtCashKh;

                $roundSuggestionAmtUp = ceil($totalSuggestionAmt_KH / 100) * 100;
                $roundSuggestionAmtDown = floor($totalSuggestionAmt_KH / 100) * 100;

                if (!$allowPartial) {
                    if (!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'Amount KHR must be around (KHR ' . $roundSuggestionAmtUp . ' & KHR ' . $roundSuggestionAmtDown . '), base ' . $totalSuggestionAmt_KH
                        ]));
                    }

                    if ($totalAllAmt != $dueAmount) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'If USD amount($' . $totalAmountUSD . ') additional in KHR must be (' . $roundSuggestionAmtUp . ' or ' . $totalSuggestionAmt_KH . ')',
                            'khInfo' => 'If USD amount($' . $totalAmountUSD . ') additional in KHR must be (' . $roundSuggestionAmtUp . ' or ' . $totalSuggestionAmt_KH . ')'
                        ]));
                    }
                }
            }

            if ($totalAmountUSD && !$totalAmountKHR) {
                if ($cash && $bankAmount && !$allowPartial) {
                    $additionalSuggestion = Helper::getNumber(abs($dueAmount - $cash), 2);
                    $misMatch = $additionalSuggestion !== $bankAmount;

                    if ($misMatch) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'If Cash Amount USD ' . Helper::getNumber($cash, 2) . ', so bank amount must be USD ' . Helper::getNumber($additionalSuggestion, 2),
                            'khInfo' => 'លុយដុល្លា ' . Helper::getNumber($cash, 2) . ', ដូច្នេះលុយទូរទាត់តាមធនាគារត្រូវតែ ' . Helper::getNumber($additionalSuggestion, 2),
                        ]));
                    }
                }

                if (!$allowPartial) {
                    if ($totalAmountUSD < $dueAmount) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'Payment amount must be $' . $dueAmount . ' remaining amount ($' . ($dueAmount - $totalAmountUSD) . ')'
                        ]));
                    }

                    if (abs($totalAmountUSD - $dueAmount) > 0) {
                        return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $' . $dueAmount . ' in total');
                    }
                }
            }

        } elseif ($currency === 'KHR') {
            if ($totalAmountKHR && empty($totalAmountUSD)) {
                $totalAmountKHR_to_USD = $totalAmountKHR / $exchangeRate;
                $totalAmountKHR_to_USD = floor($totalAmountKHR_to_USD * 100) / 100;

                $remainingAmt = abs($totalAmountKHR_to_USD - $dueAmount);
                $suggestionAmt = $dueAmount;

                $suggestionAmtBankKh = abs($suggestionAmt - $bankAmountKh);
                $suggestionAmtCashKh = abs($suggestionAmt - $cashKh);

                $roundSuggestionAmtUp = ceil($suggestionAmt / 100) * 100;
                $roundSuggestionAmtDown = floor($suggestionAmt / 100) * 100;

                if ($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh;
                if ($cashKh) $originalCashKh += $suggestionAmtCashKh;

                if ($bankAmountKh && $cashKh && !$allowPartial) {
                    $minSuggestionAmt = abs($bankAmountKh - $suggestionAmt);
                    $minSuggestionAmtDown = floor($minSuggestionAmt / 100) * 100;
                    $maxSuggestionAmt = ceil($minSuggestionAmt / 100) * 100;

                    if (!($cashKh >= $minSuggestionAmtDown && $cashKh <= $maxSuggestionAmt)) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'Based on Bank Amount KHR ' . Helper::getNumber($bankAmountKh, 2) . ', the cash amount should be around KHR ' . Helper::getNumber($minSuggestionAmtDown, 2) . ' to KHR ' . Helper::getNumber($maxSuggestionAmt, 2)
                        ]));
                    }
                } elseif (!$allowPartial && $suggestionAmt > 0) {
                    if (!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'Amount KHR must be around (KHR ' . $roundSuggestionAmtUp . ' & KHR ' . $roundSuggestionAmtDown . ') based on exchange_rate (' . $exchangeRate . ')'
                        ]));
                    }
                }
            }
        } else {
            return DataResponse::ValidateFail('Unsupported currency type');
        }

        return DataResponse::JsonRaw([
            'error' => false,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh
        ]);
    }



    // public function paymentSuggestionByCurrency($cash, $cashKh, $bankAmount, $bankAmountKh, $dueAmount, $currency, $exchangeRate) {
    //     $dueAmount = (float)Helper::getNumber($dueAmount, 2);
    //     $totalAmountUSD = (float)Helper::getNumber($cash + $bankAmount, 2);
    //     $totalAmountKHR = (float)Helper::getNumber($cashKh + $bankAmountKh, 2);

    //     $hasMoreThanTwoDecimals = function ($amount) {
    //         $amountStr = (string)$amount;
    //         if (strpos($amountStr, '.') !== false) {
    //             $decimalPart = explode('.', $amountStr)[1];
    //             return strlen($decimalPart) > 2;
    //         }
    //         return false;
    //     };

    //     if (
    //         $hasMoreThanTwoDecimals($cash) ||
    //         $hasMoreThanTwoDecimals($cashKh) ||
    //         $hasMoreThanTwoDecimals($bankAmount) ||
    //         $hasMoreThanTwoDecimals($bankAmountKh) ||
    //         $hasMoreThanTwoDecimals($dueAmount)
    //     ) {
    //         return DataResponse::ValidateFail(__('messages.info', [
    //             'info' => 'Your input amount includes more than two decimal digits',
    //             'khInfo' => 'សូមបញ្ចូលទឹកប្រាក់ដែល​មានទសភាគមិនលើសពីពីរខ្ទង់'
    //         ]));
    //     }

    //     $originalCashKh = 0;
    //     $originalBankAmtKh = 0;

    //     if ($currency === 'USD') {
    //         if ($totalAmountUSD && $totalAmountKHR) {
    //             $totalAmountKHR_to_USD = $totalAmountKHR / $exchangeRate;
    //             $totalAllAmt = Helper::getNumber($totalAmountUSD + $totalAmountKHR_to_USD, 2);

    //             if ($totalAllAmt > $dueAmount) {
    //                 return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $' . $dueAmount . ' in total');
    //             }

    //             $remainingAmt = abs($totalAmountUSD - $dueAmount);
    //             // Convert remaining USD to KHR
    //             $totalSuggestionAmt_KH = $remainingAmt * $exchangeRate;

    //             $suggestionAmtBankKh = abs($totalSuggestionAmt_KH - $bankAmountKh);
    //             $suggestionAmtCashKh = abs($totalSuggestionAmt_KH - $cashKh);

    //             if ($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh;
    //             if ($cashKh) $originalCashKh += $suggestionAmtCashKh;

    //             $roundSuggestionAmtUp = ceil($totalSuggestionAmt_KH / 100) * 100;
    //             $roundSuggestionAmtDown = floor($totalSuggestionAmt_KH / 100) * 100;

    //             if (!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) {
    //                 return DataResponse::ValidateFail(__('messages.info', [
    //                     'info' => 'Amount KHR must be around (KHR ' . $roundSuggestionAmtUp . ' & KHR ' . $roundSuggestionAmtDown . '), base ' . $totalSuggestionAmt_KH
    //                 ]));
    //             }

    //             if ($totalAllAmt != $dueAmount) {
    //                 return DataResponse::ValidateFail(__('messages.info', [
    //                     'info' => 'If USD amount($' . $totalAmountUSD . ') additional in KHR must be (' . $roundSuggestionAmtUp . ' or ' . $totalSuggestionAmt_KH . ')',
    //                     'khInfo' => 'If USD amount($' . $totalAmountUSD . ') additional in KHR must be (' . $roundSuggestionAmtUp . ' or ' . $totalSuggestionAmt_KH . ')'
    //                 ]));
    //             }
    //         }

    //         if ($totalAmountUSD && !$totalAmountKHR) {
    //             if ($cash && $bankAmount) {
    //                 $additionalSuggestion = Helper::getNumber(abs($dueAmount - $cash), 2);
    //                 $misMatch = $additionalSuggestion !== $bankAmount;

    //                 if ($misMatch) {
    //                     return DataResponse::ValidateFail(__('messages.info', [
    //                         'info' => 'If Cash Amount USD ' . Helper::getNumber($cash, 2) . ', so bank amount must be USD ' . Helper::getNumber($additionalSuggestion, 2),
    //                         'khInfo' => 'លុយដុល្លា ' . Helper::getNumber($cash, 2) . ', ដូច្នេះលុយទូរទាត់តាមធនាគារត្រូវតែ ' . Helper::getNumber($additionalSuggestion, 2),
    //                     ]));
    //                 }
    //             }

    //             if ($totalAmountUSD < $dueAmount) {
    //                 return DataResponse::ValidateFail(__('messages.info', [
    //                     'info' => 'Payment amount must be $' . $dueAmount . ' remaining amount ($' . ($dueAmount - $totalAmountUSD) . ')'
    //                 ]));
    //             }

    //             if (abs($totalAmountUSD - $dueAmount) > 0) {
    //                 return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $' . $dueAmount . ' in total');
    //             }
    //         }

    //     } elseif ($currency === 'KHR') {
    //         if ($totalAmountKHR && empty($totalAmountUSD)) {
    //             $totalAmountKHR_to_USD = $totalAmountKHR / $exchangeRate;
    //             $totalAmountKHR_to_USD = floor($totalAmountKHR_to_USD * 100) / 100;

    //             $remainingAmt = abs($totalAmountKHR_to_USD - $dueAmount);
    //             // Use dueAmount directly (in KHR)
    //             $suggestionAmt = $dueAmount;

    //             $suggestionAmtBankKh = abs($suggestionAmt - $bankAmountKh);
    //             $suggestionAmtCashKh = abs($suggestionAmt - $cashKh);

    //             $roundSuggestionAmtUp = ceil($suggestionAmt / 100) * 100;
    //             $roundSuggestionAmtDown = floor($suggestionAmt / 100) * 100;

    //             if ($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh;
    //             if ($cashKh) $originalCashKh += $suggestionAmtCashKh;

    //             if ($bankAmountKh && $cashKh) {
    //                 // Log::info($dueAmount);
    //                 $minSuggestionAmt = abs($bankAmountKh - $suggestionAmt);
    //                 $minSuggestionAmtDown = floor($minSuggestionAmt / 100) * 100;
    //                 $maxSuggestionAmt = ceil($minSuggestionAmt / 100) * 100;

    //                 if (!($cashKh >= $minSuggestionAmtDown && $cashKh <= $maxSuggestionAmt)) {
    //                     return DataResponse::ValidateFail(__('messages.info', [
    //                         'info' => 'Based on Bank Amount KHR ' . Helper::getNumber($bankAmountKh, 2) . ', the cash amount should be around KHR ' . Helper::getNumber($minSuggestionAmtDown, 2) . ' to KHR ' . Helper::getNumber($maxSuggestionAmt, 2)
    //                     ]));
    //                 }
    //             }


    //             else {
    //                 if ($suggestionAmt > 0) {
    //                     if (!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) {
    //                         return DataResponse::ValidateFail(__('messages.info', [
    //                             'info' => 'Amount KHR must be around (KHR ' . $roundSuggestionAmtUp . ' & KHR ' . $roundSuggestionAmtDown . ') based on exchange_rate (' . $exchangeRate . ')'
    //                         ]));
    //                     }
    //                 }

    //             }
    //         }
    //     } else {
    //         return DataResponse::ValidateFail('Unsupported currency type');
    //     }

    //     return DataResponse::JsonRaw([
    //         'error' => false,
    //         'original_cash_amount_kh' => $originalCashKh,
    //         'original_bank_amount_kh' => $originalBankAmtKh
    //     ]);
    // }


    public function paymentSuggestion($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$exchangeRate){
        $dueAmount = Helper::getNumber($dueAmount,2);
        $totalAmountUSD = Helper::getNumber($cash + $bankAmount);
        $totalAmountKHR = Helper::getNumber($cashKh + $bankAmountKh);
        $hasMoreThanTwoDecimals = function ($amount) {
            $amountStr = (string)$amount;
            if (strpos($amountStr, '.') !== false) {
                $decimalPart = explode('.', $amountStr)[1]; // Get the decimal part
                return strlen($decimalPart) > 2; // Check if there are more than 2 digits after the decimal point
            }
            return false; // No decimal part, so no need to check
        };
        if ($hasMoreThanTwoDecimals($cash) || $hasMoreThanTwoDecimals($cashKh) || $hasMoreThanTwoDecimals($bankAmount) || $hasMoreThanTwoDecimals($bankAmountKh) || $hasMoreThanTwoDecimals($dueAmount)) {
            return DataResponse::ValidateFail(__('messages.info',[
                'info' =>  'Your input amount includes more than two decimal digits',
                'khInfo' => 'សូមបញ្ចូលទឹកប្រាក់ដែល​មានទសភាគមិនលើសពីពីរខ្ទង់'
            ]));
        }
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($totalAmountUSD && $totalAmountKHR){
            $totalAmountKHR_to_USD = $totalAmountKHR/$exchangeRate;
            $totalAllAmt = Helper::getNumber($totalAmountUSD + $totalAmountKHR_to_USD);
            if($totalAllAmt > $dueAmount) return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $'.$dueAmount.' in total');
            $remainingAmt = abs($totalAmountUSD - $dueAmount);
            $totalSuggestionAmt_KH = $remainingAmt * $exchangeRate;
            $suggestionAmtBankKh = abs($totalSuggestionAmt_KH  - $cashKh);
            $suggestionAmtCashKh = abs($totalSuggestionAmt_KH - $bankAmountKh);
            if($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh; //** keep original amount */
            if($cashKh) $originalCashKh += $suggestionAmtCashKh; //** keep original amount */
            $roundSuggestionAmtUp = ceil($totalSuggestionAmt_KH / 100) * 100;
            $roundSuggestionAmtDown = floor($totalSuggestionAmt_KH / 100) * 100;
            if(!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) return DataResponse::ValidateFail(message: __('messages.info',[
                'info' => 'Amount KHR must be around (KHR '.$roundSuggestionAmtUp .' & KHR '.$roundSuggestionAmtDown.'), base '.$totalSuggestionAmt_KH
            ]));
            // Log::error($totalAllAmt.'---'.$dueAmount.'--------'.$totalAmountKHR_to_USD.'------'.$totalAmountKHR);
            if($totalAllAmt != $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')',
                'khInfo' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')'
            ]));
        }

        if($totalAmountUSD && !$totalAmountKHR){
            if($cash && $bankAmount){
                $additionalSuggestion = Helper::getNumber(abs($dueAmount - $cash));
                $misMatch = $additionalSuggestion !== $bankAmount;
                if ($misMatch) {
                    return DataResponse::ValidateFail(__('messages.info', [
                        'info' => 'If Cash Amount USD ' . Helper::getNumber($cash, 2) . ', so bank amount must be USD ' . Helper::getNumber($additionalSuggestion, 2),
                        'khInfo' => 'លុយដុល្លា ' . Helper::getNumber($cash, 2) . ', ដូច្នេះលុយទូរទាត់តាមធនាគារត្រូវតែ ' . Helper::getNumber($additionalSuggestion, 2),
                    ]));
                }
            }
            if($totalAmountUSD < $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment amount must be $'.$dueAmount.' remaining amount ($'.$dueAmount - $totalAmountUSD.')'
            ]));
            $totalAmountUSD = (float) $totalAmountUSD; // Ensures it's a float
            $dueAmount = (float) $dueAmount; // Casts dueAmount to float
            $misMatchTotal = abs($totalAmountUSD - $dueAmount) > 0;
            if($misMatchTotal) return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $'.$dueAmount.' in total');
        }

        if($totalAmountKHR && !$totalAmountUSD){
            $totalAmountKHR_to_USD = $totalAmountKHR / $exchangeRate;
            $totalAmountKHR_to_USD = floor($totalAmountKHR_to_USD * 100) / 100;
            $remainingAmt = abs($totalAmountKHR_to_USD - $dueAmount);
            $suggestionAmt = $dueAmount * $exchangeRate;
            $suggestionAmtBankKh = abs($suggestionAmt - $cashKh);
            $suggestionAmtCashKh = abs($suggestionAmt - $bankAmountKh);
            $roundSuggestionAmtUp = ceil($suggestionAmt / 100) * 100;
            $roundSuggestionAmtDown = ceil($suggestionAmt / 100) * 100;
            if($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh; //** keep original amount */
            if($cashKh) $originalCashKh += $suggestionAmtCashKh; //** keep original amount */
            if($cashKh && $bankAmountKh){
                $minSuggestionAmt = abs($cashKh - $suggestionAmt);
                $minSuggestionAmtDown = floor($minSuggestionAmt / 100) * 100;
                $maxSuggestionAmt = ceil($minSuggestionAmt / 100) * 100;
                if(!($bankAmountKh >= $minSuggestionAmtDown && $bankAmountKh <= $maxSuggestionAmt)){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'If Cash Amount KHR '.Helper::getNumber($cashKh,2).' bank amount must be around KHR '.Helper::getNumber($minSuggestionAmtDown,2).' or KHR '.Helper::getNumber($maxSuggestionAmt,2)
                    ]));
                }
            }else{
                if(!($totalAmountKHR >= $suggestionAmt && $totalAmountKHR <= $roundSuggestionAmtUp)){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'Amount KHR must around (KHR '.$roundSuggestionAmtUp .' & KHR '.$suggestionAmt.') base on exchange_rate ('.$exchangeRate.')'
                    ]));
                }

            }
        }

        return DataResponse::JsonRaw([
            'error'=>false,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh
        ]);
    }

    public function validPackages($packageIds,$driverOrMerchantId,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $obj = (object)[
            'total_packages' => 0,
            'total_cod' => 0 ,
            'total_delivery_fee' => 0,
            'delivered_package_count' => 0,
            'failed_with_fee_count' => 0,
            'total_taxi_fee' => 0,
            'driver_total' => 0,
            'merchant_total' => 0,
            'total_due_amount' => 0,
            'total_package_price'=>0,
            'total_amount' => 0
        ];
        $packages = Package::where('is_deleted', 0)
        ->whereIn('status_id', [9, 19])
        ->whereIn('id', $packageIds)
        ->get()
        ->keyBy('id');

        $paidPackageIds = DB::table('payment_packages')
            ->whereIn('package_id', $packageIds)
            ->where('payer_type', $type)  // 'driver' or 'merchant'
            ->where('is_deleted', false)
            ->pluck('package_id')
            ->toArray();

        $disbursedPackageIds = DB::table('disbursement_packages')
            ->whereIn('package_id', $packageIds)
            ->where('payee_type', $type)  // 'driver' or 'merchant'
            ->where('is_deleted', false)
            ->where('type', 'payment')
            ->pluck('package_id')
            ->toArray();

        // Combine to one array of package IDs that are paid or disbursed
        $paidOrDisbursedPackageIds = array_unique(array_merge($paidPackageIds, $disbursedPackageIds));

        foreach($packageIds as $index=>$id){
            $package = $packages->get($id);

            if (!$package) {
                return DataResponse::ValidateFail(__('messages.info', [
                    'info' => 'Invalid package on row (' . ($index + 1) . ')',
                    'khInfo' => 'កញ្ចប់មិនត្រឹមត្រូវនៅជួរលេខ (' . ($index + 1) . ')',
                ]));
            }
            if (in_array($id, $paidOrDisbursedPackageIds)) {
                return DataResponse::ValidateFail(__('messages.error', [
                    'info' => 'Check list might include package that has been paid',
                    'khInfo' => 'សូមពិនិត្យមើលថាតើបញ្ជីនេះមានកញ្ចប់ដែលបានបង់ប្រាក់រួចហើយ',
                ]));
            }
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
            $rowTotal = self::getPackageTotal($type,$package->cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotal($type,$package->cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
                    $rowTotal = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
                }else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            $obj->total_due_amount += $rowTotal;
            if($package->cod) $obj->total_cod += $package->price;
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
        }
        // if($paymentType == 'disbursement') $obj->total_due_amount = abs($obj->total_due_amount);
        // Log::error(json_encode($obj));
        return DataResponse::JsonRaw([
            'error' => false,
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => round($obj->total_delivery_fee,2),
            'total_package' => $obj->total_packages,
            'driver_total' => $obj->driver_total,
            'total_taxi_fee' => $obj->total_taxi_fee,
            'merchant_total' => $obj->merchant_total,
            'total_package_price' => $obj->total_package_price,
            'failed_with_fee_count' => $obj->failed_with_fee_count,
            'total_cod' => $obj->total_cod,
            'total_due_amount' => $obj->total_due_amount,
            'total_amount' => $obj->total_amount,
            'delivered_package_count' => $obj->delivered_package_count,
        ]);
    }

    public function validPackagesV1($packageIds,$driverOrMerchantId,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $obj = (object)[
            'total_packages' => 0,
            'total_cod' => 0 ,
            'total_delivery_fee' => 0,
            'delivered_package_count' => 0,
            'failed_with_fee_count' => 0,
            'total_taxi_fee' => 0,
            'driver_total' => 0,
            'merchant_total' => 0,
            'total_due_amount_khr' => 0,
            'total_due_amount_usd' => 0,
            'total_package_price'=>0,
            'total_amount' => 0,
            'total_fees' => 0
        ];
        $packages = Package::where('is_deleted', 0)
        ->whereIn('status_id', [9, 19])
        ->whereIn('id', $packageIds)
        ->get()
        ->keyBy('id');

        $paidPackageIds = DB::table('payment_packages')
            ->whereIn('package_id', $packageIds)
            ->where('payer_type', $type)  // 'driver' or 'merchant'
            ->where('is_deleted', false)
            ->pluck('package_id')
            ->toArray();

        $disbursedPackageIds = DB::table('disbursement_packages')
            ->whereIn('package_id', $packageIds)
            ->where('payee_type', $type)  // 'driver' or 'merchant'
            ->where('is_deleted', false)
            ->where('type', 'payment')
            ->pluck('package_id')
            ->toArray();

        // Combine to one array of package IDs that are paid or disbursed
        $paidOrDisbursedPackageIds = array_unique(array_merge($paidPackageIds, $disbursedPackageIds));

        $totalDriverCodUsd = 0;
        $totalDriverCodKhr = 0;
        foreach($packageIds as $index=>$id){
            $package = $packages->get($id);

            if (!$package) {
                return DataResponse::ValidateFail(__('messages.info', [
                    'info' => 'Invalid package on row (' . ($index + 1) . ')',
                    'khInfo' => 'កញ្ចប់មិនត្រឹមត្រូវនៅជួរលេខ (' . ($index + 1) . ')',
                ]));
            }
            if (in_array($id, $paidOrDisbursedPackageIds)) {
                return DataResponse::ValidateFail(__('messages.error', [
                    'info' => 'Check list might include package that has been paid',
                    'khInfo' => 'សូមពិនិត្យមើលថាតើបញ្ជីនេះមានកញ្ចប់ដែលបានបង់ប្រាក់រួចហើយ',
                ]));
            }
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
            $totalDriverCodUsd += $package->driver_cod_usd;
            $totalDriverCodKhr += $package->driver_cod_khr;
            $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer,$package->status_id);
            // Log::info('Row Total'.json_encode($rowTotal));
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotalV1($type,$package->cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
                    $rowTotal = $package->payer == 'sender' ? $package->delivery_fee + $package->extra_charge : 0;
                }
                // else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            // Log::info(json_encode($rowTotal));
            $obj->total_due_amount_khr += $rowTotal['total_khr'];
            $obj->total_due_amount_usd += $rowTotal['total_usd'];
            if($package->cod) $obj->total_cod += $package->price;
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
            $obj->total_fees += $rowTotal['fees'];
        }
        // if($paymentType == 'disbursement') $obj->total_due_amount = abs($obj->total_due_amount);
        // Log::error(json_encode($obj));
        // Helper::deductAmountBase($totalDriverCodUsd,$totalDriverCodKhr,$obj->total_fees + $obj->total_taxi_fee);
        // Log::info($totalDriverCodUsd);
        return DataResponse::JsonRaw([
            'error' => false,
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => round($obj->total_delivery_fee,2),
            'total_package' => $obj->total_packages,
            'driver_total' => $obj->driver_total,
            'total_taxi_fee' => $obj->total_taxi_fee,
            'merchant_total' => $obj->merchant_total,
            'total_package_price' => $obj->total_package_price,
            'failed_with_fee_count' => $obj->failed_with_fee_count,
            'total_cod' => $obj->total_cod,
            'total_due_amount_khr' => $obj->total_due_amount_khr,
            'total_due_amount_usd' => $obj->total_due_amount_usd,
            'total_amount' => $obj->total_amount,
            'delivered_package_count' => $obj->delivered_package_count,
        ]);
    }

    public function validBulkPackagesV1($packages,$packageIds,$driverOrMerchantId,$type){
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
        foreach($packageIds as $index=>$id){
            $package = $packages->get($id);
            if (!$package) {
                return DataResponse::ValidateFail(__('messages.info', [
                    'info' => 'Invalid package on row (' . ($index + 1) . ')',
                    'khInfo' => 'កញ្ចប់មិនត្រឹមត្រូវនៅជួរលេខ (' . ($index + 1) . ')',
                ]));
            }
            // Log::info($package);
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
            $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer,$package->status_id);
            // Log::info('Row Total => '.$rowTotal['total_usd']);
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer,$package->status_id);
                    // $rowTotal = $package->payer == 'sender' ? $package->delivery_fee + $package->extra_charge : 0;
                }
                // else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            // Log::info(json_encode($rowTotal));
            // $obj->total_due_amount_khr += $rowTotal['total_khr'];
            // $obj->total_due_amount_usd += $rowTotal['total_usd'];

            $obj->total_fees += $rowTotal['fees'];
            if($package->cod) $obj->total_cod += $package->price;
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
            $obj->merchant_name = $package->merchant?->username;
            $obj->merchant_code = $package->merchant?->code;
            $totalDriverCodUsd += $package->driver_cod_usd;
            $totalDriverCodKhr += $package->driver_cod_khr;
        }
        // if($paymentType == 'disbursement') $obj->total_due_amount = abs($obj->total_due_amount);
        // Log::error(json_encode($obj));
        Helper::deductAmountBase($totalDriverCodUsd,$totalDriverCodKhr,$obj->total_fees + $obj->total_taxi_fee);
        // Log::info($totalDriverCodUsd.'---'.$totalDriverCodKhr);
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

    public function getPayments(Request $req,$user,$type='driver',$isApproved=false){
        $payeeOrPayerId = $req->{$type.'_id'};
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $allPayments = [];
        $payerOnApprove = '';
        if($isApproved){
            $payerOnApprove = ',ap.username as payer_name';
        }
        $qP = Payment::fromRaw('payments as p')
        ->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',$isApproved)
        ->join('users as ap','ap.id','p.receiver_uid')
        ->where('payer_type',$type)
        ->selectRaw('p.received_amount_usd,p.received_amount_khr,p.trx_code,p.is_settled,p.payment_datetime,p.package_count,ap.username as booked_user,p.payable_amount,p.id as payment_id,d.username as driver_name,p.exchange_rate,ap.username as receiver_name,p.taxi_fee,p.approved,p.breakdown_notes'.$payerOnApprove)
        ->orderByDesc('p.payment_datetime');
        // if(!$isApproved) $qP->join('users as d','d.id','p.payer_id');

        if($payeeOrPayerId) $qP->where('p.payer_id',$payeeOrPayerId);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function($q) use($startDate,$endDate){
                // $q->whereRaw('payment_datetime::DATE >= ? AND payment_datetime::DATE <= ?', [$startDate, $endDate]);
                $q->whereRaw('payment_datetime >= ? AND payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            });
        }

        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $cashUSD = $pmt_details->cash_usd;
            $bankUSD = $pmt_details->bank_usd;
            $cashKHR = $pmt_details->cash_khr;
            $bankKHR = $pmt_details->bank_khr;
            if($isApproved){
                $pmt->status_code = $pmt->is_settled ? 'Settled' : 'Pending';
            }
            $pmt->payment_date = Helper::dateDMY($pmt->payment_datetime);
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $pmt->cash_usd = Helper::displayMoney($cashUSD,'USD');
            $pmt->cash_khr = Helper::displayMoney($cashKHR,'KHR');
            $pmt->bank_usd = Helper::displayMoney($bankUSD,'USD');
            $pmt->bank_khr = Helper::displayMoney($bankKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            // $pmt->total = (float)Helper::getNumber($totalUSD + $totalKHR_to_USD);
            $pmt->payment_type = 'receive';
            $pmt->payable_amount = Helper::currencyAmount($pmt->received_amount_usd,'USD').' | '.Helper::currencyAmount($pmt->received_amount_khr,'KHR');
            $allPayments[] = $pmt;
        }

        $disbursementDetails = DisbursementDetails::get();
        $qD = Disbursement::from('disbursements as dis')
        ->where('dis.is_deleted',0)
        ->where('dis.type','payment')
        ->where('dis.approved',$isApproved)
        ->join('users as d','d.id','dis.payee_id')
        ->leftJoin('users as ap','ap.id','dis.receiptionist_uid')
        ->where('payee_type',$type)
        ->leftJoin('users as py','py.id','dis.approved_uid')
        ->selectRaw('dis.received_amount_usd,dis.received_amount_khr,dis.is_settled,dis.payment_datetime,dis.package_count,ap.username as booked_user,dis.payable_amount,dis.id as payment_id,d.username as driver_name,py.username as payer_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes')
        ->orderByDesc('dis.payment_datetime');
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qD->where(function($q) use($startDate,$endDate){
                // $q->whereRaw('payment_datetime::DATE >= ? AND payment_datetime::DATE <= ?', [$startDate, $endDate]);
                $q->whereRaw('payment_datetime >= ? AND payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            });
        }
        $disbursements = $qD->get();
        foreach($disbursements as $d){
            $pmt_details = $this->preparePaymentPackageAmount($disbursementDetails,$d->payment_id,'disbursement');
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            // if($isApproved){
            $d->status_code = $d->is_settled ? 'Settled' : 'Pending';
            // }
            $d->total_usd = Helper::displayMoney($totalUSD,'USD');
            $d->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$d->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $d->total = $totalUSD + $totalKHR_to_USD;
            $d->payment_type = 'disbursement';
            $d->payment_date = Helper::dateDMY($d->payment_datetime);
            $d->payable_amount = Helper::currencyAmount($d->received_amount_usd,'USD').' | '.Helper::currencyAmount($d->received_amount_khr,'KHR');
            // $d->payable_amount = Helper::currencyAmount($d->received_amount_usd,'USD').'|'.Helper::currencyAmount($d->received_amount_khr,'KHR');
            $allPayments[] = $d;
        }
        $allPayments = collect($allPayments);
        return DataResponse::Pagination($allPayments,$req);
    }


    public function getTransaction(Request $req,$type='merchant'){
        $payeeOrPayerId = $req->{$type.'_id'};
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $transactionType = $req->transaction_type ?? null;
        $allPayments = [];
        if(!$transactionType || $transactionType == 'receive'){
            $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
            ->where('p.is_deleted',0)
            ->join('users as ap','ap.id','p.receiver_uid')
            ->where('payer_type',$type)
            ->selectRaw('p.received_amount_usd,p.received_amount_khr,p.trx_code,p.is_settled,p.payment_datetime,p.package_count,ap.username as booked_user,p.payable_amount,p.id as payment_id,p.delivery_fee,p.cod_amount,d.username as payer_name,d.username as merchant_name,p.exchange_rate,p.taxi_fee,p.approved,p.breakdown_notes,p.remarks')
            ->orderByDesc('p.payment_datetime');
            if($payeeOrPayerId) $qP->where('p.payer_id',$payeeOrPayerId);
            if($startDate && $endDate){
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qP->where(function($q) use($startDate,$endDate){
                    $q->whereRaw('p.payment_datetime >= ? AND p.payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                });
            }
            $payments = $qP->get();
            $paymentDetails = PaymentDetail::get();
            foreach($payments as $pmt){
                $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
                $totalUSD = $pmt_details->total_usd;
                $totalKHR = $pmt_details->total_khr;
                $pmt->total = Helper::currencyAmount($pmt->received_amount_usd,'USD').'|'.Helper::currencyAmount($pmt->received_amount_khr,'KHR');
                $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
                $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
                $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
                $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
                $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
                $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
                // $pmt->total = $totalUSD + $totalKHR_to_USD;
                $pmt->payment_type = 'receive';
                $allPayments[] = $pmt;
            }
        }

        if($transactionType == 'disbursement' || !$transactionType){
            $paymentDetails = DisbursementDetails::get();
            $qD = Disbursement::fromRaw('disbursements as dis')
            ->where('dis.is_deleted',0)
            ->where('dis.type','payment')
            ->join('users as d','d.id','dis.payee_id')
            ->join('users as ap','ap.id','dis.receiptionist_uid')
            ->where('payee_type',$type)
            ->selectRaw('dis.received_amount_usd,dis.received_amount_khr,dis.is_settled,dis.payment_datetime,dis.package_count,dis.cod_amount,dis.delivery_fee,ap.username as booked_user,dis.payable_amount,dis.id as payment_id,d.username as merchant_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes,dis.remarks')
            ->orderByDesc('dis.payment_datetime');
            if($startDate && $endDate){
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qD->where(function($q) use($startDate,$endDate){
                    $q->whereRaw('dis.payment_datetime >= ? AND dis.payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                });
            }
            $disbursements = $qD->get();
            foreach($disbursements as $d){
                $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$d->payment_id);
                $totalUSD = $pmt_details->total_usd;
                $totalKHR = $pmt_details->total_khr;
                $d->payment_date = Helper::formatCustomDateTime($d->payment_datetime,'d-M-Y');
                $d->payment_time = Helper::formatCustomDateTime($d->payment_datetime,'h:i:s A');
                $d->total_usd = Helper::displayMoney($totalUSD,'USD');
                $d->total_khr = Helper::displayMoney($totalKHR,'KHR');
                $totalKHR_to_USD = $totalKHR/$d->exchange_rate;
                $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
                // $d->total = $totalUSD + $totalKHR_to_USD;
                $d->payment_type = 'disbursement';
                // $d->delivery_fee = $d->delivery_fee + $d->extra_charge;
                $d->payment_date = Helper::dateDMY($d->payment_datetime);
                $d->total = Helper::currencyAmount($d->received_amount_usd,'USD').'|'.Helper::currencyAmount($d->received_amount_khr,'KHR');
                $allPayments[] = $d;
            }
        }

        $allPayments = collect($allPayments);
        return DataResponse::Pagination($allPayments,$req);
    }


    static function strReplaceCurrencySymbols(string $input): string {
        // Define the replacements
        $replacements = [
            'USD' => '$',
            'KHR' => '៛'
        ];

        // Replace occurrences using str_replace
        return str_replace(array_keys($replacements), array_values($replacements), $input);
    }
    public function getApprovedPayments(Request $req,$user){
        $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',1)
        ->join('users as r','r.id','p.receiver_uid')
        ->leftJoin('users as st','st.id','p.settled_uid')
        ->orderBy('p.is_settled')
        ->selectRaw('p.is_deleted,st.username as settlement_username,p.is_settled,r.username as receiver_name,p.payment_datetime,p.id as payment_id,d.username as payer_name,p.exchange_rate,p.amount,p.taxi_fee,p.breakdown_notes as remarks');
        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $pmt->total_usd = $totalUSD;
            $pmt->total_khr = $totalKHR;
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = (float)Helper::getNumber($totalUSD + $totalKHR_to_USD);
            $pmt->status_code = $pmt->is_settled ? 'Settled' : 'Pending';
            unset($pmt->payment_datetime);
        }
        return DataResponse::Pagination($payments,$req);
    }

    public static function getDriverCommissionInfo($driverCommissions,$driverId){
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_pickup_commission_type' => 'percentage',
            'normal_pickup_commission_start_date' => null,

            'normal_delivery_commission' => 0,
            'normal_delivery_commission_type' => 'percentage',
            'normal_delivery_commission_start_date' => null,

            'fast_pickup_commission' => 0,
            'fast_pickup_commission_type' => 'percentage',
            'fast_pickup_commission_start_date' => null,

            'fast_delivery_commission' => 0,
            'fast_delivery_commission_type' => 'percentage',
            'fast_delivery_commission_start_date' => null,

        ];
        foreach($driverCommissions as $driverComm){
            if($driverComm->driver_id == $driverId){
                    if($driverComm->delivery_type == 'fast'){
                    $dc->fast_pickup_commission = $driverComm->pickup_commission;
                    $dc->fast_pickup_commission_type = $driverComm->pickup_commission_type;
                    $dc->fast_pickup_commission_start_date = $driverComm->pickup_commission_start_date ?? $driverComm->updated_date;

                    $dc->fast_delivery_commission = $driverComm->delivery_commission;
                    $dc->fast_delivery_commission_type = $driverComm->delivery_commission_type;
                    $dc->fast_delivery_commission_start_date = $driverComm->delivery_commission_start_date ?? $driverComm->updated_date;
                }
                if($driverComm->delivery_type == 'normal'){
                    $dc->normal_pickup_commission = $driverComm->pickup_commission;
                    $dc->normal_pickup_commission_type = $driverComm->pickup_commission_type;
                    $dc->normal_pickup_commission_start_date = $driverComm->pickup_commission_start_date ?? $driverComm->updated_date;

                    $dc->normal_delivery_commission = $driverComm->delivery_commission;
                    $dc->normal_delivery_commission_type = $driverComm->delivery_commission_type;
                    $dc->normal_delivery_commission_start_date = $driverComm->delivery_commission_start_date ?? $driverComm->updated_date;
                }
            }
        }

        return $dc;
    }

    // Delete first stage of payment
    public function deletePayment($id,$trxType,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        if($trxType=='receive'){
            // Log::info($id);
            $payment = Payment::where('is_deleted',0)->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            if($payment->is_settled) return DataResponse::Duplicated(__('messages.info',[
                'info' => 'Payment has already been settled'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_payment_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id
            ]);

            PaymentPackage::where('payment_id',$id)->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }else if($trxType == 'disbursement'){
            $payment = Disbursement::where('is_deleted',0)->where('type','payment')->orderByDesc('id')->find($id);
            if($payment->is_settled) return DataResponse::Duplicated(__('messages.info',[
                'info' => 'Payment has already been settled'
            ]));
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_disbursement_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id
            ]);

            DisbursementPackage::where('disbursement_id',$id)->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }
        return DataResponse::JsonResult(null,false,__('messages.removed'));
    }


    // delete after approved
    public function deleteSettlePayment($id,$trxType,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        if(!in_array($trxType,['receive','disbursement'])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select payment type'
        ]));
        if($trxType =='receive'){
            $payment = Payment::where('is_deleted',0)->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            // $pmtKey = $type.'_payment_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id
            ]);
            PaymentPackage::where('payment_id',$id)->update([
                'is_deleted' => true,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }else if($trxType == 'disbursement'){
            $payment = Disbursement::where('is_deleted',0)->where('type','payment')->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            // $pmtKey = $type.'_disbursement_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
            ]);

            DisbursementPackage::where('disbursement_id',$id)->update([
                'is_deleted' => true,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Payment'
        ]));
    }

    private function validType($type){
        $validType = ['driver','merchant'];
        if(!in_array($type,$validType)) return DataResponse::ValidateFail('Invalid type');
        return DataResponse::JsonResult(null);
    }

    public static function preparePaymentPackageAmount($paymentDetails,$paymentId,$type='receive'){
        $converter = (object)[
            'total_usd' => 0,
            'total_khr' => 0,
            'cash_usd' => 0,
            'cash_khr' => 0,
            'bank_usd' => 0,
            'bank_khr' => 0,
        ];
        foreach ($paymentDetails as $d) {
        // Determine if the record matches the given type and ID
            $isMatchingType = ($type === 'receive' && $d->payment_id == $paymentId) ||
                            ($type === 'disbursement' && $d->disbursement_id == $paymentId);

            if ($isMatchingType) {
                // Update totals based on currency
                if ($d->currency_code === 'USD') {
                    $converter->total_usd += $d->amount;
                } else {
                    $converter->total_khr += $d->amount;
                }

                // Update cash and bank details
                if ($d->method === 'cash') {
                    if ($d->currency_code === 'USD') {
                        $converter->cash_usd += $d->amount; // Use += to sum amounts
                    } else {
                        $converter->cash_khr += $d->amount; // Sum for KHR
                    }
                } else {
                    if ($d->currency_code === 'USD') {
                        $converter->bank_usd += $d->amount; // Sum for USD bank
                    } else {
                        $converter->bank_khr += $d->amount; // Sum for KHR bank
                    }
                }
            }
        }
        return $converter;
    }


    public function approvePayments(Request $req,$user){
        $payments = $req->payments ?? [];
        if(!isset($payments[0])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please check payments you want to approve'
        ]));

        DB::beginTransaction();
        try{
            foreach($payments as $p){
                $id = $p['id'];
                if($p['payment_type'] == 'receive'){
                    $pmt = Payment::where('is_deleted',0)->find($id);
                    if(!$pmt) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($pmt->approved) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes approved payment'
                    ]));
                    $pmt->update([
                        'approved' => 1,
                        'approved_datetime' => now(),
                        'approved_uid' => $user->id,
                    ]);
                }else if($p['payment_type'] == 'disbursement'){
                    $dis = Disbursement::where('is_deleted',0)->where('type','payment')->find($id);
                    if(!$dis) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($dis->approved) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes approved payment'
                    ]));
                    $dis->update([
                        'receiptionist_uid' => $user->id,
                        'approved' => 1,
                        'approved_datetime' => now(),
                        'approved_uid' => $user->id,
                    ]);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Approved',
                'khInfo' => 'ឯកភាព'
            ]));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error'));
        }
    }

    public function settlePayments(Request $req,$user){
        $payments = $req->payments ?? [];
        if(!isset($payments[0])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select payments you want to settle'
        ]));

        DB::beginTransaction();
        try{
            foreach($payments as $p){
                $id = $p['id'];
                if($p['payment_type'] == 'receive'){
                    $pmt = Payment::where('is_deleted',0)->find($id);
                    if(!$pmt) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($pmt->is_settled) return DataResponse::ValidateFail('check list includes settled payment');
                    $pmt->update([
                        'is_settled' => 1,
                        'settled_datetime' => now(),
                        'settled_uid' => $user->id,
                    ]);
                }else if($p['payment_type'] == 'disbursement'){
                    $dis = Disbursement::where('is_deleted',0)->where('type','payment')->find($id);
                    if(!$dis) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($dis->is_settled) return DataResponse::ValidateFail('check list includes settled payment');
                    $dis->update([
                        'is_settled' => $user->id,
                        'setteled_datetime' => now(),
                        'settled_uid' => $user->id,
                    ]);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Settled',
                'khInfo' => 'បញ្ជាក់ការទូរទាត់'
            ]));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return DataResponse::Error('Failed to settle');
        }
    }

    public function getBalance(Request $req,$user,$type='driver'){
        $driverId = $req->driver_id ?? null;
        $merchantId = $req->merchant_id ?? null;
        $userId = $driverId ?? $merchantId;
        $startDate = $req->startDate ? Helper::dateYMD($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateYMD($req->endDate) : null;
        // $pmtKey = $type.'_payment_id';
        // $disKey = $type.'_disbursement_id';
        $qP = User::from('users as d')
            ->where('d.account_type',$type)
            ->where('d.is_deleted', 0)
            ->where('d.company_id', $user->company_id) // Uncomment if needed
            ->join('packages as p', 'p.'.$type.'_id', '=', 'd.id')
            ->where('p.is_deleted',0)
            ->whereIn('p.status_id',[9,19])
            // ->where(function ($q) use($pmtKey,$disKey) {
            //     $q->whereNull($pmtKey)
            //     ->whereNull($disKey);
            // })
            ->whereNotExists(function ($sub) use ($type) {
                $sub->select(DB::raw(1))
                    ->from('payment_packages as pp')
                    ->whereColumn('pp.package_id', 'p.id')
                    ->where('pp.payer_type', $type)
                    ->where('pp.is_deleted', false);
            })
            ->whereNotExists(function ($sub) use ($type) {
                $sub->select(DB::raw(1))
                    ->from('disbursement_packages as dp')
                    ->whereColumn('dp.package_id', 'p.id')
                    ->where('dp.payee_type', $type)
                    ->where('dp.is_deleted', false);
            })

            ->orderByRaw('COALESCE(p.failed_datetime, p.delivered_datetime) DESC NULLS LAST')
            // ->join('payments as pmt','p.driver_payment_id','pmt.id')
            // ->where('pmt.is_settled',0)
            ->select([
                'p.additional_fee','p.extra_charge','p.payer','p.cod','p.delivery_fee','p.price','p.taxi_fee',
                'p.other_fee','p.delivered_datetime','p.failed_datetime','d.id as driver_id','d.id',
                'd.username as driver_name','d.code','p.status_id','p.updated_at','p.driver_cod_khr',
                'p.driver_cod_usd'
            ]);
            // ->groupBy(['d.id','pmt.payable_amount',DB::raw('DATE(p.delivered_datetime)'),DB::raw('DATE(p.failed_datetime)')]);
        if($userId){
            $qP->where('d.id',$userId);
        }
        if ($startDate && $endDate) {
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

            // $qP->where(function ($q) use ($startDatetime, $endDatetime) {
            //     $q->whereRaw(
            //         "(p.failed_datetime >= ? AND p.failed_datetime <= ?)
            //         OR
            //         (p.delivered_datetime >= ? AND p.delivered_datetime <= ?)",
            //         [$startDatetime, $endDatetime, $startDatetime, $endDatetime]
            //     );
            // });
        }


        $drivers = $qP->get();
        $totalPackages = 0;
        $totalAmount = 0;
        $totalAmountKhr = 0;
        $totalDriverCodUsd = 0;
        $totalDriverCodKhr = 0;
        $totalFees = 0;
        $totalTaxiFee = 0;

        $groupData = collect($drivers)->map(function ($item) {
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
        })->map(function ($group, $key) use(
            &$totalPackages,&$totalAmount,&$totalAmountKhr,&$totalDriverCodUsd,&$totalDriverCodKhr,
            &$totalFees,&$totalTaxiFee,$type
        ) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group
            $payer = ($type == 'merchant') ? 'sender':'receiver';
            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            $totalPrice = $group->where('cod',1)->where('status_id','=',9)->sum('price');
            $driverCodUsd = $group->whereIn('status_id',[9,19])->sum('driver_cod_usd');
            $driverCodKhr = $group->whereIn('status_id',[9,19])->sum('driver_cod_khr');
            $representative = $group->first();
            $taxiFee = $group->where('status_id','=',9)->sum('taxi_fee');
            $totalTaxiFee += $taxiFee;
            $deliveryFee = $group->sum('delivery_fee');
            $otherFee = $group->sum('other_fee');

            $fees =  + $group->where('payer',$payer)->sum('other_fee') + $group->sum('delivery_fee');
            // Log::info("$type -- $driverCodUsd -- $payer --delivery: $deliveryFee ---other: $otherFee -- taxi fee=$taxiFee");
            $userTotal = self::getPackageTotalV1($type,$driverCodUsd,$driverCodKhr,$deliveryFee,$taxiFee,$otherFee,$payer);
            // $amountUsd = $userTotal['total_usd'];
            // $amountKhr = $userTotal['total_khr'];
            $totalPackages += $packageTotal;
            $totalAmount += $totalPrice;
            $totalAmountKhr += $group->where('cod',1)->where('status_id','=',9)->sum('price_khr');
            $totalDriverCodUsd += $driverCodUsd;
            $totalDriverCodKhr += $driverCodKhr;
            $totalFees += $fees;

            // $representative->package_count = $packageTotal; // Add the summed total_package
            unset($representative->groupDate);
            return [
                'finished_date' => $date,
                'driver_id' => $driver_id,
                'driver_name' => $representative->driver_name,
                'amount_usd' => $userTotal['total_usd'],
                'amount_khr' => $userTotal['total_khr'],
                'fees' => $fees,
                'taxi_fee' => $taxiFee,
                'code' => $representative->code,
                'status_id' => $representative->status_id,
                'package_count' => $packageTotal,
            ];
        })->values();

        return DataResponse::Pagination(collect($groupData),$req,__('messages.Get List'),[
            'total_packages' => Helper::getNumber($totalPackages,0,true),
            'total_cod_usd' => (float)Helper::getNumber($totalAmount,2),
            'total_cod_khr' => (float)Helper::getNumber($totalAmountKhr,2),
            'total_amount_usd' => (float)Helper::getNumber($totalDriverCodUsd,2),
            'total_amount_khr' => (float)Helper::getNumber($totalDriverCodKhr,2),
            'taxi_fee' => Helper::getNumber($totalTaxiFee,2,true),
            'fees' => Helper::getNumber($totalFees,2,true)
        ]);
    }

    public function getDriverCommissionBalance($user,$driver_id,$type='pick_up',$filter=null){
        $commissions = $this->getDriverCommissions($user,$driver_id);
        $totalPickUpCommission = 0;
        $totalFastDeliveryCommission = 0;
        $totalNormalDeliveryCommission = 0;
        $normalPickUpCommission = $commissions->data->normal_pickup_commission;
        $normalDeliveryCommission = $commissions->data->normal_delivery_commission;
        $fastPickUpCommission = $commissions->data->fast_pickup_commission;
        $fastDeliveryCommission = $commissions->data->fast_delivery_commission;
        if($type == 'pick_up' || $type == 'all'){
            $orders = Order::where('driver_id',$driver_id)->whereIn('status_id',[2,4,5,21])->selectRaw('SUM(qty) as total_qty')->get();
            foreach($orders as $order){
                $totalPickUpCommission += $order->total_qty * $normalPickUpCommission;
            }
            if($type != 'all') return (object)['total' => Helper::getNumber($totalPickUpCommission,2)];
        }else if($type == 'delivery' || $type == 'all'){
            $packages = Package::where('driver_id',$driver_id)
            ->where('is_deleted',0)
            ->whereIn('status_id',[9,19])
            ->selectRaw('delivery_type')
            ->get();
            foreach($packages as $pkg){
                if($pkg->delivery_type == 'fast'){
                    $totalFastDeliveryCommission += $fastDeliveryCommission;
                }else if($pkg->delivery_type == 'normal'){
                    $totalNormalDeliveryCommission += $normalDeliveryCommission;
                }
            }
            if($type != 'all') return (object)[
                'total' => Helper::getNumber($totalFastDeliveryCommission + $totalNormalDeliveryCommission,2),
                'total_fast_delivery' => Helper::getNumber($totalFastDeliveryCommission,2),
                'total_normal_delivery' => Helper::getNumber($totalNormalDeliveryCommission,2),
            ];
        }
        //
        return (object)[
            'total_pickup' => Helper::getNumber($totalPickUpCommission,2),
            'total_fast_delivery' => Helper::getNumber($totalFastDeliveryCommission,2),
            'total_normal_delivery' => Helper::getNumber($totalNormalDeliveryCommission,2)
        ];
    }

    public function updatePackageFromTransaction(int $id,$data,$user){
        $validate = validator($data,[
            'driver_cod_usd' => 'numeric|min:0',
            'driver_cod_khr' => 'numeric|min:0',
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Package',
            'khInfo' => 'កញ្ចប់'
        ]));
        $inputs['update_uid'] = $user->id;
        if(!$package->method || $package->method == PaymentMethod::COD->value){
            $inputs['orginal_driver_cod_usd'] = $inputs['driver_cod_usd'];
            $inputs['orginal_driver_cod_khr'] = $inputs['driver_cod_khr'];
        }
        $package->update($inputs);
        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }

    public function updateDeliveryPackage(Request $req,$type,$user){
        $id = $req->id;
        $validate = validator($req->all(),[
            'remarks' => 'nullable|string|max:250',
            'cod' => 'required|in:1,0',
            'price' => 'nullable|numeric',
            'price_khr' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'receiver_address' => 'nullable|string|max:100',
            'driver_cod_usd' => 'numeric|min:0',
            'driver_cod_khr' => 'numeric|min:0',
            'receiver_phone' => 'nullable|string',
            'taxi_fee' => 'numeric|min:0',
            'zone_code' => 'required',
            'extra_charge' => 'nullable|numeric|min:0'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        $hasPayment = $package->hasMerchantPayment();
        // Log::info($hasPayment);
        if($hasPayment){
            $updateArr = [
                'remarks' => $inputs['remarks'] ?? '',
                'receiver_address' => $inputs['receiver_address'] ?? ''
            ];
            // if(isset($inputs['remarks'])) $updateArr['remarks'] = $inputs['remarks'];
            // if(isset($inputs['receiver_address'])) $updateArr['receiver_address'] = $inputs['receiver_address'];
            $package->update($updateArr);
            return DataResponse::JsonResult(null, false, __('messages.info', [
                'info' => 'Only remark and receiver address were updated. Price-related fields cannot be modified for paid packages.',
                'khInfo' => 'បានកែសំគាល់នឹងទីតាំងតែប៉ុណ្ណោះ។ ពាក់ព័ន្ធនឹងតម្លៃមិនអាចកែប្រែបានទេសម្រាប់កញ្ចប់ដែលបានទូរទាត់ប្រាក់រួច។'
            ]));
        }
        // return DataResponse::Duplicated(__('messages.info',[
        //     'info' => 'Package has already been paid, cannot update price-related fields',
        //     'khInfo' => 'កញ្ចប់បានទូរទាត់ប្រាក់រួចហើយ, មិនអាចកែប្រែតម្លៃបានទេ'
        // ]));
        // $driverPayment = $package->driver_payment_id || $package->driver_disbursement_id;
        // $merchantPayment = $package->merchant_payment_id || $package->merchant_disbursement_id;
        // if($merchantPayment || $driverPayment){
        //     // if(isset($inputs['remarks']) || isset($inputs['receiver_address'])){
                // $updateArr = [
                //     'remarks' => $inputs['remarks'] ?? '',
                //     'receiver_address' => $inputs['receiver_address'] ?? ''
                // ];
                // // if(isset($inputs['remarks'])) $updateArr['remarks'] = $inputs['remarks'];
                // // if(isset($inputs['receiver_address'])) $updateArr['receiver_address'] = $inputs['receiver_address'];
                // $package->update($updateArr);
                // return DataResponse::JsonResult(null, false, __('messages.info', [
                //     'info' => 'Only remark and receiver address were updated. Price-related fields cannot be modified for paid packages.',
                //     'khInfo' => 'បានកែសំគាល់នឹងទីតាំងតែប៉ុណ្ណោះ។ ពាក់ព័ន្ធនឹងតម្លៃមិនអាចកែប្រែបានទេសម្រាប់កញ្ចប់ដែលបានទូរទាត់ប្រាក់រួច។'
                // ]));
        //     // }
        // }
        // if($driverPayment) return DataResponse::Duplicated(__('messages.info',[
        //     'info' => 'It seems like you try to update package which is on payment pending or paid with driver',
        //     'khInfo' => 'មិនអាចកែកញ្ចប់បានទេ, កញ្ចប់បានទូរទាត់ជាមួយអ្នកដឹករួចហើយ (Driver)'
        // ]));
        // if($merchantPayment) return DataResponse::Duplicated(__('messages.info',[
        //     'info' => 'It seems like you try to update package which is on payment pending or paid with merchant',
        //     'khInfo' => 'មិនអាចកែកញ្ចប់បានទេ, កញ្ចប់បានទូរទាត់ជាមួយអ្នកផ្ញើរួចហើយ (Merchant)'
        // ]));
        $cod = $inputs['cod'] ?? $package->cod;
        $payer = $inputs['payer'] ?? $package->payer;
        $extraCharge = $inputs['extra_charge'] ?? $package->extra_charge;
        $taxi_fee = $inputs['taxi_fee'] ?? $package->taxi_fee;
        $price = $inputs['price'] ?? $package->price;
        if($package->status_id == 19){
            $price = 0;
            $taxi_fee = 0;
        }
        $zoneCode = $inputs['zone_code'] ?? $package->zone_code;
        $zone = Zone::where('is_deleted',0)->orderByDesc('id')->selectRaw('id,zone_name')->where('zone_code',$zoneCode)->first();
        if(!$zone) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Zone',
            'khInfo' => 'ទីតាំង'
        ]));
        $inputs['zone_name'] = $zone->zone_name;
        $calFee = GeneralSettingService::calculatePackageFee($zoneCode,$price,$package->billed_kg,$package->actual_kg,$payer,$cod,$extraCharge,$user,$taxi_fee,$package->merchant_id);
        if($calFee->error) return $calFee;
        $inputs['delivery_fee'] = $calFee->delivery_fee;
        $inputs['driver_total'] = $calFee->driver_total; //($package->status_id == 19 && $package->cod) ? abs($price - $calFee->driver_total):
        $inputs['merchant_total'] = $calFee->merchant_total;
        if(!$package->method || $package->method == PaymentMethod::COD->value){
            $inputs['orginal_driver_cod_usd'] = $inputs['driver_cod_usd'];
            $inputs['orginal_driver_cod_khr'] = $inputs['driver_cod_khr'];
        }
        $package->update($inputs);
        return DataResponse::JsonResult(null,false ,__('messages.updated',[
            'info' => 'Package'
        ]));
    }

    public function disbursementPaymentValidation(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'cash' => 'nullable|numeric',
            'cash_kh' => 'nullable|numeric',
            'bank_amount' => 'nullable|numeric',
            'bank_amount_kh' => 'nullable|numeric',
            'bank_id' => 'nullable|int',
            'remarks' => 'nullable|string|max:500',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'exchange_rate' => 'required|numeric',
            'packages' => 'nullable'
        ]);
    }

    public function disbursementPaymentValidationV1(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'cash_usd' => 'nullable|numeric',
            'cash_khr' => 'nullable|numeric',
            'bank_amount_usd' => 'nullable|numeric',
            'bank_amount_khr' => 'nullable|numeric',
            'bank_id' => 'nullable|int',
            'remarks' => 'nullable|string|max:500',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'exchange_rate' => 'required|numeric',
            'packages' => 'nullable'
        ]);
    }
    public function disbursementPayment(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::disbursementPaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payeeId = $inputs[$type.'_id'];
        $packageIds = $inputs['packages'] ?? null;
        if(empty($packageIds)) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select package'
        ]));
        $payeeInfo = User::where('is_deleted',0)->whereIn('account_type',['driver','merchant'])->find($payeeId);
        if(!$payeeInfo) return DataResponse::NotFound('Could not find payee information');
        $validPackages = $this->validPackages($packageIds,$payeeId,$type);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = abs($validPackages->total_due_amount);
        if($validPackages->total_due_amount >=0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive payment not disbursement'
        ]));

        // return $validPackages;
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        if($validPayment->error) return $validPayment;
        $breakDownNotes = null;
        if($dueAmount > 0){
            if($cash > 0) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh > 0) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount > 0) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh > 0) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
        $paymentType = 'payment';
        DB::beginTransaction();
        try{
            $disArr = [
                'payee_id' => $payeeId,
                'payee_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'paid_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $dueAmount,
                'receiptionist_uid' => $user->id,
                'failed_with_fee_count' => $validPackages->failed_with_fee_count,
                'cod_amount' => $validPackages->total_cod,
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->delivered_package_count,
                'update_uid' => $user->id,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'type' => $paymentType
            ];

            if($type == 'merchant'){
                $disArr['is_settled'] = 1;
                $disArr['settled_uid'] = $user->id;
                $disArr['approved_uid'] = $user->id;
                $disArr['approved'] = 1;
                $disArr['receiptionis_uid'] = $user->id;
                $disArr['approved_datetime'] = now();
                $disArr['settled_datetime'] = now();
            }
            $createPayment = Disbursement::create($disArr);
            $disbursementId = $createPayment->id;
            self::transactionCodeGenerator('transaction_sequences',$paymentType,'disbursements','trx_code',$user->branch_id,$user->company_id,$disbursementId);
            if($cash > 0 && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh > 0 && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }
            if($bankId){
                if($bankAmount > 0 && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $disbursementId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh > 0 && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $disbursementId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }

            $disbursementPackagesArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'disbursement_id' => $disbursementId,
                'type' => $paymentType,
                'payee_type' => $type,
            ])->toArray();
            DisbursementPackage::insert($disbursementPackagesArr);

            $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payeeId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payeeId,
                'title' => __('messages.info',[
                    'info' => 'Payment Received',
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            // return $packageIds;
            // return Package::whereIn('id',$packageIds)->get();
            DB::commit();

            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    public function disbursementPaymentV1(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::disbursementPaymentValidationV1($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payeeId = $inputs[$type.'_id'];
        $packageIds = $inputs['packages'] ?? null;
        if(empty($packageIds)) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select package'
        ]));
        $payeeInfo = User::where('is_deleted',0)->whereIn('account_type',['driver','merchant'])->find($payeeId);
        if(!$payeeInfo) return DataResponse::NotFound('Could not find payee information');
        $validPackages = $this->validPackagesV1($packageIds,$payeeId,$type);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKHR = $inputs['cash_khr'] ?? 0;
        $cashUSD = $inputs['cash_usd'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmountUSD = $inputs['bank_amount_usd'] ?? 0;
        $bankAmountKHR = $inputs['bank_amount_khr'] ?? 0;
        $dueAmountUsd = abs($validPackages->total_due_amount_usd);
        $dueAmountKhr = abs($validPackages->total_due_amount_khr);
        // $dueAmount = abs($validPackages->total_due_amount);
        // if($validPackages->total_due_amount >=0) return DataResponse::ValidateFail(__('messages.info',[
        //     'info' => 'This case should be receive payment not disbursement'
        // ]));
        $bankName = null;
        if($bankId) {
            $existsBank = Bank::where('is_deleted',0)->find($bankId);
            if(!$existsBank) return DataResponse::NotFound(__('messages.not_found',['info' =>'Bank']));
            $bankName = $existsBank->name;
        }

        if(($validPackages->total_due_amount_usd + $validPackages->total_due_amount_khr) >= 0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive not disbursement'
        ]));
        $validPayment = $this->validPaymentV1($dueAmountUsd,$dueAmountKhr,$cashUSD,$cashKHR,null,$bankAmountUSD,$bankAmountKHR,$exchangeRate);
        if($validPayment->error){
            return $validPayment;
        }
        if(!($validPayment->isValidUSD && $validPayment->isValidKHR)){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Amount in USD must be '.$dueAmountUsd.' & KHR '.$dueAmountKhr
            ]));
        }

        $breakDownNotes = null;
        // if(($dueAmountUsd || $dueAmountKhr) < 0){
            if($cashUSD > 0) $breakDownNotes .= 'Cash: USD '.$cashUSD.'|';
            if($cashKHR > 0) $breakDownNotes .= 'Cash: KHR '.$cashKHR.'|';
            if($bankAmountUSD > 0) $breakDownNotes .= $bankName.': USD '.$bankAmountUSD.'|';
            if($bankAmountKHR > 0) $breakDownNotes .= $bankName.': KHR '.$bankAmountKHR.'|';
        // }
        $breakDownNotes = trim($breakDownNotes, '| ');
        $paymentType = 'payment';
        DB::beginTransaction();
        try{
            $disArr = [
                'payee_id' => $payeeId,
                'payee_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                // 'payable_amount' => $dueAmount,
                // 'paid_amount' => $dueAmount,
                'amount_due_khr' => $dueAmountKhr,
                'amount_due_usd' => $bankAmountUSD,
                'received_amount_khr' => $bankAmountKHR + $cashKHR,
                'received_amount_usd' => $bankAmountUSD + $cashUSD,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                // 'amount' => $dueAmount,
                'receiptionist_uid' => $user->id,
                'failed_with_fee_count' => $validPackages->failed_with_fee_count,
                'cod_amount' => $validPackages->total_cod,
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->delivered_package_count,
                'update_uid' => $user->id,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'type' => $paymentType
            ];

            if($type == 'merchant'){
                $disArr['is_settled'] = 1;
                $disArr['settled_uid'] = $user->id;
                $disArr['approved_uid'] = $user->id;
                $disArr['approved'] = 1;
                $disArr['receiptionis_uid'] = $user->id;
                $disArr['approved_datetime'] = now();
                $disArr['settled_datetime'] = now();
            }
            $createPayment = Disbursement::create($disArr);
            $disbursementId = $createPayment->id;
            self::transactionCodeGenerator('transaction_sequences',$paymentType,'disbursements','trx_code',$user->branch_id,$user->company_id,$disbursementId);

            // USD cash payment
            if ($cashUSD > 0 && $dueAmountUsd > 0) {
                DisbursementDetails::create([
                    'disbursement_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cashUSD,
                    'original_amount' => $cashUSD,
                    'currency_code' => 'USD'
                ]);
            }

            // KHR cash payment
            if ($cashKHR > 0 && $dueAmountKhr > 0) {
                DisbursementDetails::create([
                    'disbursement_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cashKHR,
                    'original_amount' => $cashKHR,
                    'currency_code' => 'KHR'
                ]);
            }

            // Bank payments
            if ($bankId) {
                // USD bank payment
                if ($bankAmountUSD > 0 && $dueAmountUsd > 0) {
                    DisbursementDetails::create([
                        'disbursement_id' => $disbursementId,
                        'method' => $bankName,
                        'amount' => $bankAmountUSD,
                        'original_amount' => $bankAmountUSD,
                        'currency_code' => 'USD'
                    ]);
                }

                // KHR bank payment
                if ($bankAmountKHR > 0 && $dueAmountKhr > 0) {
                    DisbursementDetails::create([
                        'disbursement_id' => $disbursementId,
                        'method' => $bankName,
                        'amount' => $bankAmountKHR,
                        'original_amount' => $bankAmountKHR,
                        'currency_code' => 'KHR'
                    ]);
                }
            }

            // if($cash > 0 && $dueAmount > 0){
            //     DisbursementDetails::create([
            //         'disbursement_id' => $disbursementId,
            //         'method' => 'cash',
            //         'amount' => $cash,
            //         'original_amount' => $cash,
            //         'currency_code' => 'USD'
            //     ]);
            // }
            // if($cashKh > 0 && $dueAmount > 0){
            //     DisbursementDetails::create([
            //         'disbursement_id' => $disbursementId,
            //         'method' => 'cash',
            //         'amount' => $cashKh,
            //         'original_amount' => $validPayment->original_cash_amount_kh,
            //         'currency_code' => 'KHR'
            //     ]);
            // }
            // if($bankId){
            //     if($bankAmount > 0 && $dueAmount > 0){
            //         DisbursementDetails::create([
            //             'disbursement_id' => $disbursementId,
            //             'method' => $validPayment->bank_name,
            //             'amount' => $bankAmount,
            //             'original_amount' => $bankAmount,
            //             'currency_code' => 'USD'
            //         ]);
            //     }

            //     if($bankAmountKh > 0 && $dueAmount > 0){
            //         DisbursementDetails::create([
            //             'disbursement_id' => $disbursementId,
            //             'method' => $validPayment->bank_name,
            //             'amount' => $bankAmountKh,
            //             'original_amount' => $validPayment->original_bank_amount_kh,
            //             'currency_code' => 'KHR'
            //         ]);
            //     }
            // }

            $disbursementPackagesArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'disbursement_id' => $disbursementId,
                'type' => $paymentType,
                'payee_type' => $type,
            ])->toArray();
            DisbursementPackage::insert($disbursementPackagesArr);

            $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payeeId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payeeId,
                'title' => __('messages.info',[
                    'info' => 'Payment Received',
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            // return $packageIds;
            // return Package::whereIn('id',$packageIds)->get();
            DB::commit();
            // Log::info(json_encode($createPayment));

            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment',
                'khInfo' => 'ទូរទាត់'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    public static function calculateCommission($rate, $rateType, $number)
{
    if (!is_numeric($rate) || !is_numeric($number)) {
        return 0;
    }

    if ($rateType === 'percentage') {
        return $rate * $number / 100;
    } else if ($rateType === 'amount') {
        return $rate * $number;
    }

    // Unknown rate type
    return 0;
}


    public function disbursementCommission(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        // Log::info($req->all());
        $validate = self::disbursementPaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payeeId = $inputs[$type.'_id'];
        $payeeInfo = User::where('is_deleted',0)->whereIn('account_type',['driver','merchant'])->find($payeeId);
        if(!$payeeInfo) return DataResponse::NotFound('Could not find payee information');
        $validPackages = $this->validCommissionPackage($payeeId,$type,$startDate,$endDate);
        // return DataResponse::JsonResult($validPackages);
        if($validPackages->error) return $validPackages;
        $packageIds = $validPackages->package_ids;
        $orderIds = $validPackages->order_ids;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = $validPackages->grand_total;
        // Log::info($dueAmount);
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        if($validPayment->error) return $validPayment;
        $breakDownNotes = null;
        if($dueAmount > 0){
            if($cash > 0) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh > 0) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount > 0) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh > 0) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
        DB::beginTransaction();
        try{
            $createPayment = Disbursement::create([
                'payee_id' => $payeeId,
                'type' => 'commission',
                'payee_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $dueAmount,
                'exchange_rate' => $exchangeRate,
                'approved' => true,
                'is_settled' => true,
                'pickup_rate' => $validPackages->pickup_rate,
                'fast_pickup_rate' => $validPackages->fast_pickup_rate,
                'delivery_rate' => $validPackages->fast_delivery_rate,
                'fast_delivery_rate' => $validPackages->fast_delivery_rate,
                'settled_uid' => $user->id,
                'approved_uid' => $user->id,
                'receiptionist_uid' => $user->id,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->total_delivered_package,
                'pickup_package_count' => $validPackages->total_pickup_package,
                'failed_with_fee_count' => $validPackages->total_failed_with_fee_package,
                'update_uid' => $user->id,
                'approved_datetime' => now(),
                'settled_datetime' => now(),
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
            $paymentId = $createPayment->id;
            if($cash && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }
            if($bankId){
                if($bankAmount && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }
            // Package::whereIn('id',$packageIds)->update([
            //     $type.'_commission_id' => $paymentId
            // ]);
            DisbursementPackage::insert(collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'disbursement_id' => $paymentId,
                'type' => 'commission',
                'payee_type' => $type
            ])->toArray());

            Order::whereIn('id',$orderIds)->update([
                $type.'_commission_id' => $paymentId
            ]);
            // return $packageIds;
            // return Package::whereIn('id',$packageIds)->get();
            DB::commit();

            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment'
            ]));
        }catch(Exception $e){
            // Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    public function deleteDisbursementCommission($id,$user,$type){
        $dis = Disbursement::where('type','commission')->where('is_deleted',0)->find($id);
        if(!$dis) return DataResponse::NotFound('');
        $dis->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id,
        ]);
        $pmtKey = $type.'_commission_id';
        // Package::where('is_deleted',0)->where($pmtKey,$id)->update([
        //     $pmtKey => null
        // ]);
        DisbursementPackage::where('disbursement_id',$id)->update([
            'is_deleted' => true,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);
        

        Order::where('is_deleted',0)->where($pmtKey,$id)->update([
            $pmtKey => null
        ]);

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Commission',
            'khInfo' => ''
        ]));

    }



    private function validCommissionPackage($payeeId,$type,$startDate,$endDate){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        // $deliveredCount = 0;
        $pickUpCount = 0;
        // $failedWithFeeCount = 0;
        $obj = (object)[
            'error' => false,
            'total_pickup' => 0,
            'total_delivered' => 0,
            'total_delivery_fee' => 0,
            'total_taxi_fee' => 0,
            'grand_total' => 0,
            'total_package' => 0,
            'total_delivered_package' => 0,
            'total_pickup_count' => 0,
            'package_ids' => [],
            'order_ids' => []
        ];
        // $dc = (object)[
        //     'normal_pickup_commission' => 0,
        //     'normal_delivery_commission' => 0,
        //     'fast_pickup_commission' => 0,
        //     'fast_delivery_commission' => 0
        // ];
        $payeeKey = $type.'_id';
        $qP = Package::from('packages as p')
            ->selectRaw('p.id, p.status_id, p.driver_id,p.delivery_type')
            // ->whereIn('p.status_id', [9, 19])
            ->whereIn('p.status_id', [9])
            ->where('p.is_deleted', 0)
        ->whereNotExists(function ($sub) use($type) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', $type)
                ->where('dp.type','commission')
                ->where('dp.is_deleted', false);
        });
        if($payeeId) {
            $qP->where('p.'.$payeeKey,$payeeId);
        }
        // Log::info($qP->count())

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

        if($payeeId) $qO->where($payeeKey,$payeeId);
        // if($startDate && $endDate){
        //     $startDate = Helper::dateYMD($startDate);
        //     $endDate = Helper::dateYMD($endDate);
        //     $qO->whereRaw('order_datetime::DATE >= ? AND order_datetime::DATE <= ?', [$startDate, $endDate]);
        // }

        $qDc = DriverCommission::query()
            ->where('is_deleted',0)
            ->where('driver_id', $payeeId)
            ->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission,delivery_commission_type,pickup_commission_start_date,delivery_commission_start_date');

        // if($driverId)
        $driverCommissions = $qDc->get();
        $dc = TransactionService::getDriverCommissionInfo($driverCommissions,$payeeId);

        $startDateFromQuery = $startDate ?? null;
        $defaultNormalDeliveryDate = $dc->normal_delivery_commission_start_date;
        // $defaultFastDeliveryDate = $dc->fast_delivery_commission_start_date;
        $defaultNormalPickUpDate = $dc->normal_pickup_commission_start_date;

        $normalDeliveryStartDate = $startDateFromQuery
            ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalDeliveryDate)
            : null;
        // $fastDeliveryStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultFastDeliveryDate)
        //     : null;
        $normalPickUpStartDate = $startDateFromQuery
            ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalPickUpDate)
            : null;


        $endDate = $endDate ? Helper::dateYMD($endDate). ' 23:59:59' : null;

        if($normalDeliveryStartDate && $endDate){
            // $endDate = Helper::dateYMD($endDate);
            // Log::info($normalDeliveryStartDate.' '.$endDate);
            $qP->whereRaw("
                p.status_id = 9 AND p.delivered_datetime >= ? AND p.delivered_datetime <= ?
            ", [$normalDeliveryStartDate, $endDate]);
            // $qP->whereRaw("
            //     (
            //         (p.status_id = 19 AND p.failed_datetime >= ? AND p.failed_datetime <= ?)
            //         OR
            //         (p.status_id = 9 AND p.delivered_datetime >= ? AND p.delivered_datetime <= ?)
            //     )
            // ", [$startDate, $endDate, $startDate, $endDate]);

            // $qO->whereRaw('pickup_datetime >= ? AND pickup_datetime <= ?', [$normalDeliveryStartDate, $endDate]);
            // $qO->whereBetween('pickup_datetime',[$normalPickUpStartDate,$endDate]);
        }
        $packages = $qP->get();
        $orders = $qO->get();
        // Log::info('pkg count'.count($packages));
        // Log::info('order count'.count($orders));
        // $qDc = DriverCommission::where('driver_id',$payeeId)->where('is_deleted',0)->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission_type,delivery_commission,pickup_commission_start_date,delivery_commission_start_date');
        // $driverCommissions = $qDc->get();
        // $dc = TransactionService::getDriverCommissionInfo($driverCommissions,$payeeId);
        // foreach($orders as $order){
            // $pickUpCount += $order->qty;
            // $obj->order_ids[] = $order->id;
        // }
        $pickUpInfo = $this->getPickUpDetails($orders, $payeeId);
        // Log::info(' o => '.$pickUpCount);
        $obj->order_ids = $pickUpInfo->order_ids;
        $pickUpCount = $pickUpInfo->total_package;
        $totalCommissionPkg = 0;
        $normalDeliveredCount = 0;
        $fastDeliveredCount = 0;

        $normalFailedWithFeeCount = 0;
        $fastFailedWithFeeCount = 0;
        foreach($packages as $package){
            // if($package->status_id == 9) {
            //     $deliveredCount += 1;
                $obj->package_ids[] = $package->id;
            // }
            // if($package->status_id == 19) $failedWithFeeCount +=1;
            // if($package->cod) $obj->total_taxi_fee += $package->delivery_fee;
            // if($package->taxi_fee) $obj->total_taxi_fee += $package->taxi_fee;
            if($package->status_id == 9) {
                if($package->delivery_type == 'normal'){
                    $normalDeliveredCount +=1;
                }else if($package->delivery_type == 'fast'){
                    $fastDeliveredCount +=1;
                }
                $totalCommissionPkg +=1;
            }
            if($package->status_id == 19) {
                if($package->delivery_type == 'normal'){
                    $normalFailedWithFeeCount +=1;
                }
                else if($package->delivery_type == 'fast'){
                    $fastFailedWithFeeCount +=1;
                }
                $totalCommissionPkg +=1;
            }
        }
        // Log::info($pickUpCount);
        $obj->total_pickup = $pickUpCount * $dc->normal_pickup_commission;
        $obj->total_delivered = $normalDeliveredCount * $dc->normal_delivery_commission + $fastDeliveredCount * $dc->fast_delivery_commission;
        $obj->total_package = $pickUpCount + $normalDeliveredCount + $fastDeliveredCount + $normalFailedWithFeeCount + $fastFailedWithFeeCount;
        //** Deliverd pkgs */
        $obj->normal_delivered_count= $normalDeliveredCount;
        $obj->fast_delivered_count = $fastDeliveredCount;
        //-----
        $obj->total_delivered_package = $normalDeliveredCount + $fastDeliveredCount;
        $obj->total_pickup_package = $pickUpCount;
        $obj->delivery_rate = $dc->normal_delivery_commission;
        $obj->pickup_rate = $dc->normal_pickup_commission;
        $obj->fast_delivery_rate = $dc->fast_delivery_commission;
        $obj->fast_pickup_rate = $dc->fast_pickup_commission;
        //** FailedWithFee pkgs */
        $obj->normal_failed_with_fee_count = $normalFailedWithFeeCount;
        $obj->fast_failed_with_fee_count = $fastFailedWithFeeCount;
        $obj->total_failed_with_fee_package = $normalFailedWithFeeCount + $fastFailedWithFeeCount;
        //----

        $obj->total_pickup_count = $pickUpCount;

        $obj->grand_total = Helper::getNumber($obj->total_pickup + $obj->total_delivered,2);
        // Log::info(json_encode($obj));
        // Log::info($obj->total_pickup);
        if(empty($obj->package_ids) && empty($obj->order_ids)){
            return DataResponse::NotFound('No package found');
        }
        return $obj;
    }


    public static function getPickUpDetails($orders,$driverId): object{
        $totalPkg = 0;
        $orderIds = [];
        foreach($orders as $order){
            if($order->driver_id == $driverId){
                $totalPkg += $order->qty;
                $orderIds[] = $order->id;
            }
        }
        return (object)[
            'total_package' => $totalPkg,
            'order_ids' => $orderIds
        ];
    }

    public static function getDeliveredDetails($packages,$driverId){
        $totalPkg = 0;
        $failedWithFeeCount = 0;
        $deliveredCount = 0;
        foreach($packages as $pkg){
            if($pkg->driver_id == $driverId){
                $totalPkg += 1;
                if($pkg->status_id == 9) $deliveredCount +=1;
                else if($pkg->status_id == 19) $failedWithFeeCount +=1;
            }
        }

        return (object)[
            'total_package' => $totalPkg,
            'delivered_count' => $deliveredCount,
            'failed_with_fee_count' => $deliveredCount,
        ];
    }

    public static function getMobileUserBalance($req,$user,$targetUser){
        $count = 0;
        $total = 0;
        $operator = [
            'merchant' => -1,
            'driver' => 1,
        ];

        $targeUId = $targetUser.'_id';
        // $pUid = $targetUser.'_payment_id';
        // $dUid = $targetUser.'_disbursement_id';

        $qP = Package::from('packages as p')->where('p.is_deleted',0)
        ->where('p.created_at', '>=', Carbon::now()->subMonths(6))
        ->whereIn('p.status_id',[9,19])
        // ->selectRaw('*')
        ->where($targeUId,$user->id)
        ->whereNotExists(function ($sub) use ($targetUser) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'p.id')
                ->where('pp.payer_type', $targetUser)
                ->where('pp.is_deleted', false);
        })
        ->whereNotExists(function ($sub) use ($targetUser) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', $targetUser)
                ->where('dp.type','payment')
                ->where('dp.is_deleted', false);
        });
        // ->orderBy($pUid,'desc')
        // ->orderBy($dUid,'desc')
        // ->where(function ($q) use($pUid,$dUid) {
        //     $q->whereNull($pUid)
        //     ->whereNull($dUid);
        // });
        if($targetUser == 'merchant'){
            $qP->where(function($query) {
            $query->where('p.status_id', 9)
                    ->whereDate('p.delivered_datetime', Carbon::today());
            })
            ->orWhere(function($query) {
                $query->where('p.status_id', 19)
                    ->whereDate('p.failed_datetime', Carbon::today());
            });
        }
        $packages = $qP->get();
        $opt = $operator[$targetUser] ?? null;
        foreach($packages as $p){
            $price = $p->price;
            $taxiFee = $p->taxi_fee;
            if($p->status_id == 19){
                $price = 0;
                $taxiFee = 0;
            }
            $packageTotal = TransactionService::getPackageTotal($targetUser,$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer);
            $total += $opt * $packageTotal;
        }
        return [
            'count' => $count,
            'total' => Helper::getNumber($total),
        ];
    }


    static function getTrxDetails($rows, $pmtId, $pmtBillings = null)
    {
        foreach ($rows as $row) {
            if ($row->id == $pmtId) {
                $row->breakdown_notes = str_replace(['|', 'USD '], [' & ', '$'], $row->breakdown_notes);
                $row->breakdown_notes = preg_replace('/KHR (\d+)/', '$1៛', $row->breakdown_notes);
                // 🛠 Fix: Collect unique methods
                $methods = [];
                if (isset($pmtBillings[$row->id])) {
                    foreach ($pmtBillings[$row->id] as $billing) {
                        if (!in_array($billing->method, $methods)) {
                            if($billing->method == 'cash') $billing->method = 'Cash';
                            $methods[] = $billing->method;
                        }
                    }
                }

                // 🛠 Concat methods into a string
                $pmtMethod = implode(', ', $methods);

                $row->payment_method = $pmtMethod;
                $row->payment_date = Helper::dateDMY($row->payment_datetime, 'd M Y');
                $row->payment_time = Helper::formatCustomDateTime($row->payment_datetime, 'h:i A');

                return $row;
            }
        }
        return null;
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
        //example ref number => 20240212-B001-00003-random
        $new_code = date('Ymdhis') .'-B'. Helper::formatNumber($branch_id,3).'-'. Helper::formatNumber($next_num, $len);
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

    public function validPaymentV2(
        float $dueAmountUsd,
        float $dueAmountKhr,
        float $cashUSD = 0,
        float $cashKHR = 0,
        float $bankAmountUSD = 0,
        float $bankAmountKHR = 0,
        bool $requireFullPayment = true // the flag
    ) {
        // Total paid amounts
        $totalPaidUSD = $cashUSD + $bankAmountUSD;
        $totalPaidKHR = $cashKHR + $bankAmountKHR;

        // Remaining amounts
        $remainingUSD = max($dueAmountUsd - $totalPaidUSD, 0);
        $remainingKHR = max($dueAmountKhr - $totalPaidKHR, 0);

        $epsilon = 0.0001; // tolerance for float comparison
        $isValidUSD = $requireFullPayment ? (abs($remainingUSD) < $epsilon) : $totalPaidUSD > 0;
        $isValidKHR = $requireFullPayment ? (abs($remainingKHR) < $epsilon) : $totalPaidKHR > 0;

        return (object)[
            'error' => false,
            'totalPaidUSD' => $totalPaidUSD,
            'totalPaidKHR' => $totalPaidKHR,
            'remainingUSD' => $remainingUSD,
            'remainingKHR' => $remainingKHR,
            'isValidUSD' => $isValidUSD,
            'isValidKHR' => $isValidKHR,
            // 'bankId' => $bankId
        ];
    }
}
