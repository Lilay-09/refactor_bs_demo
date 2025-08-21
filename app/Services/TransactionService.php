<?php

namespace App\Services;

use ApiResponse;
use App\Enums\PaymentStatus;
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
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use Str;
use function Laravel\Prompts\select;

class TransactionService
{

    public function getDeliveryPackages(Request $req,$type,$user){
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
                'p.receiver_address','p.driver_cod_usd','p.driver_cod_khr','p.other_fee'
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
                    ->whereRaw('p.delivered_datetime >= ? AND p.delivered_datetime <= ?', [$startDateTime, $endDateTime]);
                })
                // Check for status_id = 19, failed_datetime should be within the date range
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('p.status_id', 19)
                    ->whereRaw('p.failed_datetime >= ? AND p.failed_datetime <= ?', [$startDateTime, $endDateTime]);
                });
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
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
                    $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer):0;
                }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->extra_charge : 0;
            }
            $package->fee = Helper::getNumber($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
            return $package;
        };
        return DataResponse::PaginationV1($qP,$req,'',[],1000,$clbMapper);
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
                'p.receiver_address','p.driver_cod_usd','p.driver_cod_khr','p.other_fee'
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
                    ->whereRaw('p.delivered_datetime >= ? AND p.delivered_datetime <= ?', [$startDateTime, $endDateTime]);
                })
                // Check for status_id = 19, failed_datetime should be within the date range
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('p.status_id', 19)
                    ->whereRaw('p.failed_datetime >= ? AND p.failed_datetime <= ?', [$startDateTime, $endDateTime]);
                });
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
            $total = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer);
            $package->{$type.'_total_usd'} = $total['total_usd'];
            $package->{$type.'_total_khr'} = $total['total_khr'];
            // if($type == 'merchant') $package->total = -$total;
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
                    $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer):0;
                }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->extra_charge : 0;
            }
            $package->fee = Helper::getNumber($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
            return $package;
        };
        return DataResponse::PaginationV1($qP,$req,'',[],1000,$clbMapper);
    }



    public function getMerchantDeliveryPackages(Request $req,object $authUser){
        $startDate = $req->startDate;
        $endDate = $req->endDate;

        $select = [
            'merchant_id',
            DB::raw("COUNT(*) as package_count"),
            DB::raw("SUM(price) as total_price"),
            DB::raw("SUM(price_khr) as total_price_khr"),
            DB::raw("SUM(CASE WHEN payer = 'sender' THEN delivery_fee ELSE 0 END) as delivery_fee"),
            DB::raw("SUM(CASE WHEN payer = 'sender' THEN other_fee ELSE 0 END) as other_fee"),
            DB::raw("SUM(CASE WHEN payer = 'sender' THEN taxi_fee ELSE 0 END) as taxi_fee"),
            DB::raw('SUM(driver_cod_khr) as total_driver_cod_khr'),
            DB::raw("array_agg(id) as package_ids"),
            DB::raw('SUM(driver_cod_usd) as total_driver_cod_usd'),
            DB::raw("
                CASE
                    WHEN status_id = 9 THEN DATE(delivered_datetime)
                    WHEN status_id = 19 THEN DATE(failed_datetime)
                END as finish_date
            ")
        ];

        // Base package query
        $qP = Package::query()
            ->where('is_deleted', false)
            ->whereIn('status_id', [9, 19])
            ->with(['merchant:id,username,code', 'merchant.primaryBank'])
            ->select($select)
            ->groupBy('merchant_id', 'finish_date')
            ->orderBy('finish_date', 'desc');

        // Date filter
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

        // Get package IDs from the filtered query
        // $packageIds = $qP->pluck('package_ids')->flatten()->unique()->toArray();
        $packageIds = $qP->pluck('package_ids') // collection of array strings
            ->flatMap(function ($ids) {
                return json_decode(str_replace(['{', '}'], ['[', ']'], $ids), true);
            })
            ->map(fn($id) => (int)$id)
            ->unique()
            ->toArray();
        // return DataResponse::JsonResult($packageIds);

        // Disbursement packages (filtered)
        $disbursementPkg = DisbursementPackage::query()
            ->where('is_deleted', false)
            ->whereIn('package_id', $packageIds)
            ->where('type', 'payment')
            ->whereHas('disbursement', fn($q) => $q->where('is_deleted', false))
            ->with(['disbursement' => fn($q) => $q->select('id','payment_status_id','amount_due_usd','amount_due_khr','received_amount_usd','received_amount_khr')
                ->where('is_deleted', false)
            ])
            // ->pluck('package_id')
            // ->toArray();
            ->get()
            ->keyBy('package_id');
        // return DataResponse::JsonResult($disbursementPkg);

        // Callback for formatting
        $callback = function ($q) use ($disbursementPkg) {
            $q->fees = $q->delivery_fee + $q->other_fee;
            $q->merchant_name = $q->merchant->username;
            $toBePaid = $this->getPackageTotalV1(
                'merchant',
                $q->total_driver_cod_usd,
                $q->total_driver_cod_khr,
                $q->delivery_fee,
                $q->taxi_fee,
                $q->other_fee,
                'payer'
            );
            $q->amount_to_be_paid_usd = $toBePaid['total_usd'];
            $q->amount_to_be_paid_khr = $toBePaid['total_khr'];
            $q->code = $q->merchant->code;

            // Bank info
            if ($bankInfo = $q->merchant->primaryBank) {
                $q->bank_name = $bankInfo->bank_name;
                $q->bank_account_number = $bankInfo->bank_number;
                $q->bank_account_name = $bankInfo->account_name;
            }

            $pIds = collect(json_decode(str_replace(['{', '}'], ['[', ']'], $q->package_ids), true))
            ->map(fn($id) => (int)$id)
            ->unique()
            ->toArray();
            $q->status = 'Unpaid';
            foreach ($pIds as $id) {
                if (isset($disbursementPkg[$id])) {
                    $q->has_payment = true;
                    $q->status = PaymentStatus::tryFrom($disbursementPkg[$id]->disbursement->payment_status_id)->label();
                    break; // no need to check further
                }
            }

            // $q->status = $q->has_payment ? 'Paid / Partial' : 'Unpaid';
            unset($q->merchant);
            return $q;
        };

        return DataResponse::PaginationV1($qP, $req, '', [], 1000, $callback, $select);

        // $startDate = $req->startDate;
        // $endDate = $req->endDate;
        // $select = [
        //     'merchant_id',
        //     DB::raw("COUNT(*) as package_count"),
        //     DB::raw("SUM(price) as total_price"),
        //     DB::raw("SUM(price_khr) as total_price_khr"),
        //     DB::raw("SUM(CASE WHEN payer = 'sender' THEN delivery_fee ELSE 0 END) as delivery_fee"),
        //     DB::raw("SUM(CASE WHEN payer = 'sender' THEN other_fee ELSE 0 END) as other_fee"),
        //     DB::raw("SUM(CASE WHEN payer = 'sender' THEN taxi_fee ELSE 0 END) as taxi_fee"),
        //     DB::raw('SUM(driver_cod_khr) as total_driver_cod_khr'),
        //     DB::raw("array_agg(id) as package_ids"),
        //     DB::raw('SUM(driver_cod_usd) as total_driver_cod_usd'),
        //     DB::raw("
        //         CASE
        //             WHEN status_id = 9 THEN DATE(delivered_datetime)
        //             WHEN status_id = 19 THEN DATE(failed_datetime)
        //         END as finish_date
        //     ")
        // ];


        // $qP = Package::query()
        // ->where('is_deleted', false)
        // ->with(['merchant:id,username,code','merchant.primaryBank'])
        // ->whereIn('status_id', [9, 19])
        // ->select($select)
        // ->groupBy(
        //     'merchant_id',
        //     'finish_date'
        // )
        // ->orderBy('finish_date', 'desc');

        // if ($startDate && $endDate) {
        //     $qP->where(function ($q) use ($startDate, $endDate) {
        //         $startDate = Helper::dateYMD($startDate);
        //         $endDate = Helper::dateYMD($endDate);
        //         $q->where(function ($query) use ($startDate, $endDate) {
        //             $query->where('status_id', 9)
        //                 ->whereDate('delivered_datetime', '>=', $startDate)
        //                 ->whereDate('delivered_datetime', '<=', $endDate);
        //         })->orWhere(function ($query) use ($startDate, $endDate) {
        //             $query->where('status_id', 19)
        //                 ->whereDate('failed_datetime', '>=', $startDate)
        //                 ->whereDate('failed_datetime', '<=', $endDate);
        //         });
        //     });
        // }

        // $packageIds = Package::where('is_deleted', false)
        // ->whereIn('status_id', [9, 19])
        // ->pluck('id')
        // ->toArray();
        // $disbursementPkg = DisbursementPackage::where('is_deleted', false)
        // ->whereIn('package_id', $packageIds)
        // ->where('payee_type', 'payment')
        // ->whereHas('disbursement', function ($q) {
        //     $q->where('is_deleted', false);
        // })
        // ->with([
        //     'disbursement' => function ($q) {
        //         $q->select('id','amount_due_usd','amount_due_khr','received_amount_usd','received_amount_khr')
        //         ->where('is_deleted', false);
        //     }
        // ])
        // ->get()
        // ->keyBy('package_id');

        // $callback = function ($q){
        //     $q->fees = $q->delivery_fee + $q->other_fee;
        //     $q->merchant_name = $q->merchant->username;
        //     $toBePaid = $this->getPackageTotalV1('merchant',$q->total_driver_cod_usd,$q->total_driver_cod_khr,$q->delivery_fee,$q->taxi_fee,$q->other_fee,'payer');
        //     $q->amount_to_be_paid_usd = $toBePaid['total_usd'];
        //     $q->amount_to_be_paid_khr = $toBePaid['total_khr'];
        //     $q->code = $q->merchant->code;
        //     $bankInfo = $q->merchant->primaryBank;
        //     $q->status = 'Unpaid';
        //     if($bankInfo){
        //         $q->bank_name = $bankInfo->bank_name;
        //         $q->bank_account_number = $bankInfo->bank_number;
        //         $q->bank_account_name = $bankInfo->account_name;
        //     }
        //     unset($q->merchant);
        //     return $q;
        // };

        // return DataResponse::PaginationV1($qP,$req,'',[],1000,$callback,$select);
    }

    // public function getDeliveryPackages(Request $req,$type,$user){
    //     $selectKey = $type.'_payment_id,'.$type.'_disbursement_id';
    //     $driverId = $req->driver_id;
    //     $merchantId = $req->merchant_id;
    //     $pmtStatusId = $req->payment_status_id;
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $search = $req->search;
    //     $pmtKey = $type.'_payment_id';
    //     $disKey = $type.'_disbursement_id';
    //     $qP = Package::query()->fromRaw('packages as p')->where('p.company_id',$user->company_id)
    //     ->where('p.is_deleted',0)
    //     ->join('users as d','d.id','p.driver_id')
    //     ->join('tracking_statuses as ts','ts.id','p.status_id')
    //     ->join('users as m','m.id','p.merchant_id')
    //     ->orderByRaw("p.{$pmtKey} IS NULL DESC, p.{$pmtKey}")
    //     ->orderByRaw("p.{$disKey} IS NULL DESC, p.{$disKey}")
    //     // ->whereNull($type.'_payment_id')
    //     // ->whereNull($type.'_payment_id')
    //     // ->leftJoin('payments as dpmt','dpmt.id','p.'.$fkKey) //** if driver paid or unpaid */
    //     // ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
    //     ->whereIn('p.status_id',[9,19]) //* delivered and failed with fee
    //     ->selectRaw('p.extra_charge,p.additional_fee,p.remarks,p.cod,p.price,d.phone as driver_phone,p.taxi_fee,p.payer,p.delivery_fee,p.assign_driver_datetime,p.merchant_total,m.username as merchant_name,m.phone as merchant_phone,d.username as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,ts.name as status_code,p.delivered_datetime,p.failed_datetime,p.zone_code,p.receiver_phone,p.delivery_type,'.$selectKey);
    //     if($type == 'driver'){
    //         $qP->where(function ($q) use($type){
    //             $q->whereNull($type.'_payment_id')->whereNull($type.'_disbursement_id');
    //         });
    //     }
    //     if($driverId || $merchantId){
    //         if($type == 'driver') $qP->where('p.driver_id',$driverId);
    //         else $qP->where('p.merchant_id',$merchantId);
    //     }
    //     if($search){
    //         $qP->where('p.qr_code',$search);
    //     }
    //     if($startDate && $endDate){
    //         $startDate = Helper::dateYMD($startDate);
    //         $endDate = Helper::dateYMD($endDate);
    //         $qP->where(function ($q) use ($startDate, $endDate) {
    //             $startDateTime = $startDate . ' 00:00:00';
    //             $endDateTime = $endDate . ' 23:59:59';

    //             // Check for status_id = 9, delivered_datetime should be within the date range
    //             $q->where(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->where('status_id', 9)
    //                 ->whereRaw('delivered_datetime >= ? AND delivered_datetime <= ?', [$startDateTime, $endDateTime]);
    //             })
    //             // Check for status_id = 19, failed_datetime should be within the date range
    //             ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->where('status_id', 19)
    //                 ->whereRaw('failed_datetime >= ? AND failed_datetime <= ?', [$startDateTime, $endDateTime]);
    //             });
    //         });

    //     }
    //     // Log::error(json_encode($req->all()));
    //     if($type == 'merchant' && $pmtStatusId == 2){
    //         $qP->where(function ($q) {
    //             $q->whereNotNull('p.merchant_payment_id')->orWhereNotNull('p.merchant_disbursement_id');
    //         });
    //     }else if($type == 'merchant' && $pmtStatusId == 1){
    //         $qP->where(function ($q) {
    //             $q->whereNull('p.merchant_payment_id')->whereNull('p.merchant_disbursement_id');
    //         });
    //     }
    //     // $packages = $qP->get();
    //     $statusKey = $type.'_payment_status';
    //     // foreach($packages as $package){

    //     // }
    //     $clbMapper = function($package) use($statusKey,$type){
    //         $cod = $package->cod;
    //         $package->cod = $cod ? 'Yes' : 'No';
    //         $package->{$statusKey} = (!$package->{$type.'_payment_id'} && !$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
    //         $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
    //         $package->delivered_datetime = Helper::formatCustomDateTime($package->assign_driver_datetime);
    //         $package->{$type.'_total'} = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
    //         if($type == 'merchant') $package->total = -self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
    //         if($package->status_id == 19){
    //             if($type == 'merchant'){
    //                 $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
    //                 $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer):0;
    //             }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->extra_charge : 0;
    //         }
    //         $package->fee = Helper::getNumber($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
    //         return $package;
    //     };
    //     return DataResponse::PaginationV1($qP,$req,null,[],1000,$clbMapper);
    // }

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
        $payer,
        $exchangeRateBase = 4000
    ) {
        $fees = $baseFee + $otherFee;

        $totalUsd = max(0, $driverCODUsd);
        $totalKhr = max(0, $driverCODKh);
        // helper closure to deduct in USD first, then KHR
        $deduct = function($amountUsd) use (&$totalUsd, &$totalKhr, $exchangeRateBase) {
            $deductUsd = min($totalUsd, $amountUsd);
            $totalUsd -= $deductUsd;

            $remainingUsd = $amountUsd - $deductUsd;
            if ($remainingUsd > 0) {
                $totalKhr -= $remainingUsd * $exchangeRateBase;
            }
        };

        if ($type === 'driver') {
            // 1. Always deduct taxi fee
            if ($taxiFee > 0) {
                $deduct($taxiFee);
            }

            // 2. Deduct fees if payer is receiver
            if ($payer === 'receiver' && $fees > 0) {
                $deduct($fees);
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
            'remarks' => 'nullable|string|max:500',
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
        $dueAmount = $validPackages->total_due_amount;
        if($dueAmount < 0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive not disbursement'
        ]));
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
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

            $notif = new CloudMessagingService();
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
            $notif->sendNotificationByTopic($notifReq,$user);
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
        $validPayment = $this->validPaymentV1($dueAmountUsd,$dueAmountKhr,$cashUSD,$cashKHR,null,$bankAmountUSD,$bankAmountKHR);
        if(!($validPayment['isValidUSD'] && $validPayment['isValidKHR'])){
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
        if(($dueAmountUsd || $dueAmountKhr) > 0){
            if($cashUSD > 0) $breakDownNotes .= 'Cash: USD '.$cashUSD.'|';
            if($cashKHR > 0) $breakDownNotes .= 'Cash: KHR '.$cashKHR.'|';
            if($bankAmountUSD > 0) $breakDownNotes .= $bankName.': USD '.$bankAmountUSD.'|';
            if($bankAmountKHR > 0) $breakDownNotes .= $bankName.': KHR '.$bankAmountKHR.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
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
                'received_amount_khr' => $dueAmountKhr,
                'received_amount_usd' => $dueAmountUsd,
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
            if ($cashUSD > 0 && $dueAmountUsd > 0) {
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashUSD,
                    'original_amount' => $cashUSD,
                    'currency_code' => 'USD'
                ]);
            }

            // KHR cash payment
            if ($cashKHR > 0 && $dueAmountKhr > 0) {
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
                if ($bankAmountUSD > 0 && $dueAmountUsd > 0) {
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $bankName,
                        'amount' => $bankAmountUSD,
                        'original_amount' => $bankAmountUSD,
                        'currency_code' => 'USD'
                    ]);
                }

                // KHR bank payment
                if ($bankAmountKHR > 0 && $dueAmountKhr > 0) {
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
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            DB::commit();
            // Log::info(json_encode($createPayment));
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

    public function validPaymentV1(
        float $dueAmountUsd,
        float $dueAmountKhr,
        float $cashUSD = 0,
        float $cashKHR = 0,
        ?int $bankId = null,
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

        // Log::info($totalPaidKHR.' --- '.$totalPaidUSD);
        return [
            'totalPaidUSD' => $totalPaidUSD,
            'totalPaidKHR' => $totalPaidKHR,
            'remainingUSD' => $remainingUSD,
            'remainingKHR' => $remainingKHR,
            'isValidUSD' => $isValidUSD,
            'isValidKHR' => $isValidKHR,
            'bankId' => $bankId
        ];
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

    private function receiveOrDisburesementBulkV1Validator(Request $req){
        return validator($req->all(), [
            'currency' => 'required|in:KHR,USD',
            'merchants' => ['required', 'array'],
            'merchants.*.id' => ['required', 'integer', 'exists:users,id'],
            'merchants.*.packages' => ['required', 'array', 'min:1'],
            'merchants.*.packages.*' => ['integer', 'exists:packages,id']
        ]);
    }


    public function receiveOrDisburesementBulkV1(Request $req,$user,$type){
        $validator = $this->receiveOrDisburesementBulkV1Validator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $payingCurrency = $inputs['currency'];
        $merchantIds = collect($inputs['merchants'])->pluck('id')->toArray();
        $packageIds = collect($inputs['merchants'])->pluck('packages')->flatten()->toArray();
        $packages = Package::where('is_deleted',false)
        ->whereIn('status_id',[19,9])
        // ->with(['merchant:id,username,code'])
        ->whereIn('merchant_id',$merchantIds)
        ->whereIn('id',$packageIds)
        ->get();
        $clPkg = clone $packages;
        $packageKeyById = $clPkg->keyBy('id');
        $merchantInfo = $inputs['merchants'];

        $insertDisbursement = [];
        $fullyPaidInfo = [];
        $currencyConflictInfo = [];
        $disbursementPkg = DisbursementPackage::where('is_deleted', false)
            ->whereIn('package_id', $packageIds)
            ->where('payee_type', $type)
            ->whereHas('disbursement', function ($q) {
                $q->where('is_deleted', false);
            })
            ->with([
                'disbursement' => function ($q) {
                    $q->select(
                        'id',
                        'amount_due_usd',
                        'amount_due_khr',
                        'received_amount_usd',
                        'received_amount_khr',
                        'payment_status_id'
                    )->where('is_deleted', false);
                }
            ])
            ->get()
            ->keyBy('package_id');

        foreach ($merchantInfo as $m) {
            $mId = $m['id'];
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
            $hasDisbursement = false;
            $targetDisbursement = null;
            $disbursementId = null;

            $allUSDReceived = true;
            $allKHRReceived = true;

            foreach ($packages as $pkgId) {
                $disbPkg = $disbursementPkg[$pkgId] ?? null;

                if ($disbPkg) {
                    $disbursement = $disbPkg->disbursement;
                    $hasDisbursement = true;
                    $disbursementId = $disbursement->id;
                    $targetDisbursement = $disbPkg->disbursement; // reuse this disbursement
                    $usdDue = $disbursement->amount_due_usd - $disbursement->received_amount_usd;
                    $khrDue = $disbursement->amount_due_khr - $disbursement->received_amount_khr;

                    // Already fully paid
                    if ($usdDue == 0 && $khrDue == 0) {
                        $fullyPaidInfo[] = [
                            'package_id'   => $pkgId,
                            'merchant_id'  => $mId,
                            'merchant_name'=> $validPkg->data['merchant_name'] ?? $mId,
                        ];
                        continue;
                    }else{
                        if ($usdDue - $validPkg->data['total_due_amount_usd'] == 0) $allUSDReceived = true;
                        if ($khrDue - $validPkg->data['total_due_amount_khr'] == 0) $allKHRReceived = true;
                    }
                }else {
                    // No disbursement yet → not fully received
                    if ($payingCurrency === 'USD') {
                        $receivedUSD = $validPkg->data['total_due_amount_usd'];
                    }
                    if ($payingCurrency === 'KHR') {
                        $receivedKHR = $validPkg->data['total_due_amount_khr'];
                    }
                }

                $paidPackageIds[] = $pkgId;
            }
            $paymentStatusId = ($allUSDReceived && $allKHRReceived)
                ? PaymentStatus::DONE->value
                : PaymentStatus::PARTIAL->value;

            if ($hasDisbursement && $targetDisbursement) {
                // ✅ Update existing disbursement instead of inserting
                $updatePmt = [
                    'id' => $disbursementId,
                    'payment_status_id' => $paymentStatusId,
                    'update_uid'        => $user->id,
                    'payment_datetime'  => now(),
                    'remarks'           => $inputs['remarks'] ?? $targetDisbursement->remarks,
                ];

                if ($payingCurrency === 'USD') {
                    $updatePmt['received_amount_usd'] = $validPkg->data['total_due_amount_usd'] ?? 0;
                } elseif ($payingCurrency === 'KHR') {
                    $updatePmt['received_amount_khr'] = $validPkg->data['total_due_amount_khr'] ?? 0;
                }
                // $upSertPmt[] = $updatePmt;
                $targetDisbursement->update($updatePmt);

            }else{
                $paymentStatusId = (
                    $receivedKHR == $validPkg->data['total_due_amount_khr']
                    && $receivedUSD == $validPkg->data['total_due_amount_usd']
                ) ? PaymentStatus::DONE->value : PaymentStatus::PARTIAL->value;
                $insertDisbursement[] = [
                    'payee_id'               => $mId,
                    'payee_type'             => $type,
                    'taxi_fee'               => $validPkg->total_taxi_fee ?? 0,
                    'delivery_fee'           => $validPkg->total_delivery_fee ?? 0,
                    'amount_due_khr'         => $validPkg->data['total_due_amount_khr'] ?? 0,
                    'amount_due_usd'         => $validPkg->data['total_due_amount_usd'] ?? 0,
                    'received_amount_usd'    => $receivedUSD,
                    'received_amount_khr'    => $receivedKHR,
                    'create_uid'             => $user->id,
                    'receiver_uid'           => $user->id,
                    'receiptionist_uid'      => $user->id,
                    'failed_with_fee_count'  => $validPkg->failed_with_fee_count ?? 0,
                    'cod_amount'             => $validPkg->total_cod ?? 0,
                    'remarks'                => $inputs['remarks'] ?? null,
                    'package_count'          => $validPkg->total_package ?? count($paidPackageIds),
                    'delivered_package_count'=> $validPkg->delivered_package_count ?? count($paidPackageIds),
                    'update_uid'             => $user->id,
                    'payment_datetime'       => now(),
                    'breakdown_notes'        => $breakDownNotes ?? null,
                    'company_id'             => $user->company_id,
                    'branch_id'              => $user->branch_id,
                    'payment_status_id'      => $paymentStatusId,
                    'type'                   => 'payment',
                    'package_ids'            => json_encode($paidPackageIds),
                ];
            }
        }

        if (!empty($fullyPaidInfo)) {
            $count = count($fullyPaidInfo);
            // $packages = implode(', ', array_column($fullyPaidInfo, 'package_id'));
            $merchants = implode(', ', array_unique(array_column($fullyPaidInfo, 'merchant_name')));
            return DataResponse::Duplicated("{$count} fully paid package(s) detected for merchant(s): {$merchants}.");
        }

        // Report currency conflicts
        if (!empty($currencyConflictInfo)) {
            $count = count($currencyConflictInfo);
            $packages = implode(', ', array_column($currencyConflictInfo, 'package_id'));
            $merchants = implode(', ', array_unique(array_column($currencyConflictInfo, 'merchant_name')));
            $currency = $currencyConflictInfo[0]['currency'] ?? '';
            return DataResponse::Duplicated("{$count} package(s) already partially paid with {$currency} for merchant(s): {$merchants}. Package IDs: {$packages}");
        }

        try {
            DB::beginTransaction();

            if (!empty($insertDisbursement)) {
                foreach ($insertDisbursement as $disbData) {
                    // Insert into disbursements table (main payment record)
                    $disbursement = Disbursement::create([
                        'payee_id' => $disbData['payee_id'],
                        'payee_type' => $disbData['payee_type'],
                        'taxi_fee' => $disbData['taxi_fee'],
                        'delivery_fee' => $disbData['delivery_fee'],
                        'amount_due_usd' => $disbData['amount_due_usd'],
                        'amount_due_khr' => $disbData['amount_due_khr'],
                        'received_amount_usd' => $disbData['received_amount_usd'],
                        'received_amount_khr' => $disbData['received_amount_khr'],
                        'create_uid' => $disbData['create_uid'],
                        'receiver_uid' => $disbData['receiver_uid'],
                        'receiptionist_uid' => $disbData['receiptionist_uid'],
                        'failed_with_fee_count' => $disbData['failed_with_fee_count'],
                        'cod_amount' => $disbData['cod_amount'],
                        'remarks' => $disbData['remarks'],
                        'package_count' => $disbData['package_count'],
                        'delivered_package_count' => $disbData['delivered_package_count'],
                        'update_uid' => $disbData['update_uid'],
                        'payment_datetime' => $disbData['payment_datetime'],
                        'breakdown_notes' => $disbData['breakdown_notes'],
                        'company_id' => $disbData['company_id'],
                        'branch_id' => $disbData['branch_id'],
                        'payment_status_id' => $disbData['payment_status_id'],
                        'type' => $disbData['type'],
                    ]);

                    // Attach each package to this disbursement
                    $packageIds = json_decode($disbData['package_ids'], true);
                    foreach ($packageIds as $pkgId) {
                        DisbursementPackage::create([
                            'disbursement_id' => $disbursement->id,
                            'package_id' => $pkgId,
                            'type' => 'payment',
                            'payee_type' => $disbData['payee_type'],
                            'is_deleted' => false,
                        ]);
                    }
                    if (!empty($disbData['payments'])) {
                        foreach ($disbData['payments'] as $payment) {
                            DisbursementDetails::create([
                                'disbursement_id' => $disbursement->id,
                                'method' => $payment['method'] ?? 'cash',
                                'amount' => $payment['amount'],
                                'original_amount' => $payment['original_amount'] ?? $payment['amount'],
                                'currency_code' => $payment['currency_code'] ?? $payingCurrency,
                            ]);
                        }

                    }
                }
            }

            DB::commit();
            return DataResponse::JsonResult($insertDisbursement,false,__('messages.saved'));
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

    public static function amountToOneCurrency($currencyCode,$cashUSD,$cashKHR,$bankUSD,$bankKHR,$exchangeRate){
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
            $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer);
            // Log::info('Row Total'.json_encode($rowTotal));
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotalV1($type,$package->cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
                    $rowTotal = $package->payer == 'sender' ? $package->delivery_fee + $package->extra_charge : 0;
                }
                // else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            Log::info(json_encode($rowTotal));
            $obj->total_due_amount_khr += $rowTotal['total_khr'];
            $obj->total_due_amount_usd += $rowTotal['total_usd'];
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
            $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer);
            // Log::info('Row Total'.json_encode($rowTotal));
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotalV1($type,$package->driver_cod_usd,$package->driver_cod_khr,$package->delivery_fee,$package->taxi_fee,$package->other_fee,$package->payer);
                    // $rowTotal = $package->payer == 'sender' ? $package->delivery_fee + $package->extra_charge : 0;
                }
                // else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            // Log::info(json_encode($rowTotal));
            $obj->total_due_amount_khr += $rowTotal['total_khr'];
            $obj->total_due_amount_usd += $rowTotal['total_usd'];
            if($package->cod) $obj->total_cod += $package->price;
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
            $obj->merchant_name = $package->merchant?->username;
            $obj->merchant_code = $package->merchant?->code;
        }
        // if($paymentType == 'disbursement') $obj->total_due_amount = abs($obj->total_due_amount);
        // Log::error(json_encode($obj));
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
            'total_due_amount_khr' => $obj->total_due_amount_khr,
            'total_due_amount_usd' => $obj->total_due_amount_usd,
            'total_amount' => $obj->total_amount,
            'delivered_package_count' => $obj->delivered_package_count,
            'merchant_name' => $obj->merchant_name,
            'merchant_code' => $obj->merchant_code
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
        ->selectRaw('p.trx_code,p.is_settled,p.payment_datetime,p.package_count,ap.username as booked_user,p.payable_amount,p.id as payment_id,d.username as driver_name,p.exchange_rate,ap.username as receiver_name,p.taxi_fee,p.approved,p.breakdown_notes'.$payerOnApprove)
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
            $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $pmt->cash_usd = Helper::displayMoney($cashUSD,'USD');
            $pmt->cash_khr = Helper::displayMoney($cashKHR,'KHR');
            $pmt->bank_usd = Helper::displayMoney($bankUSD,'USD');
            $pmt->bank_khr = Helper::displayMoney($bankKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = (float)Helper::getNumber($totalUSD + $totalKHR_to_USD);
            $pmt->payment_type = 'receive';
            $allPayments[] = $pmt;
        }

        $disbursementDetails = DisbursementDetails::get();
        $disbursements = Disbursement::fromRaw('disbursements as dis')
        ->where('dis.is_deleted',0)
        ->where('dis.type','payment')
        ->where('dis.approved',$isApproved)
        ->join('users as d','d.id','dis.payee_id')
        ->leftJoin('users as ap','ap.id','dis.receiptionist_uid')
        ->where('payee_type',$type)
        ->leftJoin('users as py','py.id','dis.approved_uid')
        ->selectRaw('dis.is_settled,dis.payment_datetime,dis.package_count,ap.username as booked_user,dis.payable_amount,dis.id as payment_id,d.username as driver_name,py.username as payer_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes')
        ->orderByDesc('dis.payment_datetime')
        ->get();
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
            ->selectRaw('p.trx_code,p.is_settled,p.payment_datetime,p.package_count,ap.username as booked_user,p.payable_amount,p.id as payment_id,p.delivery_fee,p.cod_amount,d.username as payer_name,d.username as merchant_name,p.exchange_rate,p.taxi_fee,p.approved,p.breakdown_notes,p.remarks')
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
                $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
                $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
                $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
                $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
                $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
                $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
                $pmt->total = $totalUSD + $totalKHR_to_USD;
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
            ->selectRaw('dis.is_settled,dis.payment_datetime,dis.package_count,dis.cod_amount,dis.delivery_fee,ap.username as booked_user,dis.payable_amount,dis.id as payment_id,d.username as merchant_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes,dis.remarks')
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
                $d->total = $totalUSD + $totalKHR_to_USD;
                $d->payment_type = 'disbursement';
                // $d->delivery_fee = $d->delivery_fee + $d->extra_charge;
                $d->payment_date = Helper::dateDMY($d->payment_datetime);
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
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_disbursement_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
            ]);

            DisbursementPackage::where('disbursement_id',$id)->update([
                'is_deleted' => 1,
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
        $pmtKey = $type.'_payment_id';
        $disKey = $type.'_disbursement_id';
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
            ->select(['p.additional_fee','p.extra_charge','p.payer','p.cod','p.delivery_fee','p.price','p.taxi_fee','p.extra_charge','p.delivered_datetime','p.failed_datetime','d.id as driver_id','d.id','d.username as driver_name','d.code','p.status_id','p.updated_at']);
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
        $totalCod = 0;

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
        })->map(function ($group, $key) use(&$totalPackages,&$totalAmount,&$totalCod) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group

            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            $totalPrice = $group->where('cod',1)->where('status_id','=',9)->sum('price');
            $representative = $group->first();
            $taxiFee = $group->where('status_id','=',9)->sum('taxi_fee');
            $fee = $group->where('payer','receiver')->sum('delivery_fee') + $group->where('payer','receiver')->sum('extra_charge') + $group->sum('additional_fee') - $taxiFee;
            $amount = Helper::getNumber($totalPrice + $fee,2);
            $totalPackages += $packageTotal;
            $totalAmount += $amount;
            $totalCod += $totalPrice;
            // $representative->package_count = $packageTotal; // Add the summed total_package
            unset($representative->groupDate);
            return [
                'finished_date' => $date,
                'driver_id' => $driver_id,
                'driver_name' => $representative->driver_name,
                'amount' => $amount,
                'code' => $representative->code,
                'status_id' => $representative->status_id,
                'package_count' => $packageTotal,
            ];
        })->values();

        return DataResponse::Pagination(collect($groupData),$req,__('messages.Get List'),[
            'total_packages' => $totalPackages,
            'total_cod' => (float)Helper::getNumber($totalCod),
            'total_amount' => (float)Helper::getNumber($totalAmount,2)
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

    public function updateDeliveryPackage(Request $req,$type,$user){
        $id = $req->id;
        $validate = validator($req->all(),[
            'remarks' => 'nullable|string|max:250',
            'cod' => 'required|in:1,0',
            'price' => 'nullable|numeric',
            'price_khr' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'receiver_address' => 'nullable|string|max:100',
            'driver_cod_usd' => 'nullable|numeric',
            'driver_cod_khr' => 'nullable|numeric',
            'receiver_phone' => 'nullable|string',
            'taxi_fee' => 'nullable|numeric|min:0',
            'zone_code' => 'required',
            'extra_charge' => 'nullable|numeric|min:0'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        $hasPayment = $package->hasAnyPayment();
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
        $validPayment = $this->validPaymentV1($dueAmountUsd,$dueAmountKhr,$cashUSD,$cashKHR,null,$bankAmountUSD,$bankAmountKHR);
        if(!($validPayment['isValidUSD'] && $validPayment['isValidKHR'])){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Amount in USD must be '.$dueAmountUsd.' & KHR '.$dueAmountKhr
            ]));
        }

        $breakDownNotes = null;
        if(($dueAmountUsd || $dueAmountKhr) < 0){
            if($cashUSD > 0) $breakDownNotes .= 'Cash: USD '.$cashUSD.'|';
            if($cashKHR > 0) $breakDownNotes .= 'Cash: KHR '.$cashKHR.'|';
            if($bankAmountUSD > 0) $breakDownNotes .= $bankName.': USD '.$bankAmountUSD.'|';
            if($bankAmountKHR > 0) $breakDownNotes .= $bankName.': KHR '.$bankAmountKHR.'|';
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
                // 'payable_amount' => $dueAmount,
                // 'paid_amount' => $dueAmount,
                'amount_due_khr' => $dueAmountKhr,
                'amount_due_usd' => $bankAmountUSD,
                'received_amount_khr' => $dueAmountKhr,
                'received_amount_usd' => $dueAmountUsd,
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
                PaymentDetail::create([
                    'payment_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cashUSD,
                    'original_amount' => $cashUSD,
                    'currency_code' => 'USD'
                ]);
            }

            // KHR cash payment
            if ($cashKHR > 0 && $dueAmountKhr > 0) {
                PaymentDetail::create([
                    'payment_id' => $disbursementId,
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
                    PaymentDetail::create([
                        'payment_id' => $disbursementId,
                        'method' => $bankName,
                        'amount' => $bankAmountUSD,
                        'original_amount' => $bankAmountUSD,
                        'currency_code' => 'USD'
                    ]);
                }

                // KHR bank payment
                if ($bankAmountKHR > 0 && $dueAmountKhr > 0) {
                    PaymentDetail::create([
                        'payment_id' => $disbursementId,
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
            // DB::commit();
            Log::info(json_encode($createPayment));

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
            ->selectRaw('p.id, p.status_id, p.driver_id')
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

        $qO = Order::query()
            ->select('id') // select only needed columns
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
        // $defaultNormalPickUpDate = $dc->normal_pickup_commission_start_date;

        $normalDeliveryStartDate = $startDateFromQuery
            ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalDeliveryDate)
            : null;
        // $fastDeliveryStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultFastDeliveryDate)
        //     : null;
        // $normalPickUpStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalPickUpDate)
        //     : null;


        $endDate = $endDate ? Helper::dateYMD($endDate). ' 23:59:59' : null;

        if($normalDeliveryStartDate && $endDate){
            $endDate = Helper::dateYMD($endDate). ' 23:59:59';
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

            $qO->whereRaw('order_datetime >= ? AND order_datetime <= ?', [$normalDeliveryStartDate, $endDate]);
        }
        $packages = $qP->get();
        $orders = $qO->get();
        // Log::info('pkg count'.count($packages));
        // Log::info('order count'.count($orders));
        $qDc = DriverCommission::where('driver_id',$payeeId)->where('is_deleted',0)->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission_type,delivery_commission,pickup_commission_start_date,delivery_commission_start_date');
        $driverCommissions = $qDc->get();
        $dc = TransactionService::getDriverCommissionInfo($driverCommissions,$payeeId);
        foreach($orders as $order){
            $pickUpCount += $order->qty;
            $obj->order_ids[] = $order->id;
        }

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
        if(empty($obj->package_ids) && empty($obj->order_ids)){
            return DataResponse::NotFound('No package found');
        }
        return $obj;
    }

    public static function getPickUpDetails($orders,$driverId){
        $totalPkg = 0;
        foreach($orders as $order){
            if($order->driver_id == $driverId){
                $totalPkg += $order->qty;
            }
        }
        return (object)[
            'total_package' => $totalPkg,
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
        $new_code = date('Ymd') .'-B'. Helper::formatNumber($branch_id,3).'-'. Helper::formatNumber($next_num, $len).'-'.substr(Str::uuid()->toString(), 0, 5);
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
}
