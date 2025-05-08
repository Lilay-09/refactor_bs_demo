<?php

namespace App\Services\Mobile;

use App\Models\Delivery;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Services\AppSetting;
use App\Services\GeneralSettingService;
use DataResponse;
use DB;
use Helper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Log;

class ReusableService
{
    // Your service methods go here
    public static function getHistoryPackages(Request $req,$user=null,$reqSearch=false,$userClass='driver'){
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $userId = $user?->id;
        $isKm = in_array($req->lang,['kh','km']);
        $statusIds = [9,10,11,19];
        if($reqSearch) $statusIds[] = 6;
        if($reqSearch && !$search) return DataResponse::Pagination(new Collection(),$req);

        $latestPackages = DB::table('delivery_packages as dp1')
        ->selectRaw('DISTINCT ON (dp1.package_id) dp1.*')
        ->orderBy('dp1.package_id')
        ->orderByDesc('dp1.id');

        $driverInfo = '';

        $qFp = Delivery::query()->fromRaw('deliveries as d')
        ->joinSub($latestPackages, 'dp', 'd.id', 'dp.delivery_id')
        // ->join('delivery_packages as dp','d.id','dp.delivery_id')
        // ->where('dp.delay_count',0)->where('dp.is_deleted',0)->where('dp.has_swap',0)
        ->where([
            ['dp.delay_count', 0],
            ['dp.is_deleted', 0],
            ['dp.has_swap', 0],
        ]);
        if($userClass == 'merchant'){
            $qFp->join('users as dv','dv.id','d.driver_id');
            $driverInfo = ',dv.phone as driver_phone';
        }

        $qFp->join('packages as p','p.id','dp.package_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        // ->leftJoin('payments as pmt','pmt.id','p.merchant_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('p.driver_id,p.returned_uid,p.payer,p.extra_charge,p.cod,p.price,p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,p.status_id,trs.name as status_code,d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,p.delivered_datetime,p.arrive_warehouse_datetime,p.returned_datetime'.$driverInfo)
        // ->whereIn('dp.status_id',$statusIds)
        ->where(function ($q) use ($userId,$statusIds,$userClass) {
            $q->whereIn('p.status_id', $statusIds)
            ->orWhere(function ($subQuery) use ($userId,$userClass) {
                $subQuery->where('p.status_id', 11);
                if($userClass == 'driver') $subQuery->where('p.returned_uid', $userId);
            });
        })
        ->orderByRaw('dp.status_id = ? ASC',[6])
        ->orderByRaw('
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime
                WHEN p.status_id = 10 THEN p.failed_datetime
                WHEN p.status_id = 19 THEN p.failed_datetime
                WHEN p.status_id = 11 THEN p.returned_datetime
            END DESC
        ');

        if($paymentStatus == 2 && $userClass=='driver'){
            $qFp->where('pmt.approved',1);
        }
        if($user && $userClass=='driver'){
            $qFp->where(function ($query) use ($userId) {
                $query->where(function ($subQuery) use ($userId) {
                    $subQuery->where('p.status_id', '!=', 11)
                            ->where('p.driver_id', $userId);
                })->orWhere(function ($subQuery) use ($userId) {
                    $subQuery->where('p.status_id', 11)
                            ->where('p.returned_uid', $userId);
                });
            });

        }else if($user && $userClass=='merchant'){
            $qFp->where('p.merchant_id',$userId);
        }

        $qAt = PackageAttachment::where('hidden', 0);

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $startDateTime = $startDate . ' 00:00:00';
            $endDateTime = $endDate . ' 23:59:59';
            $qFp->where(function($q) use ($startDateTime, $endDateTime,$userId) {
                $q->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.failed_datetime', [$startDateTime, $endDateTime])
                        ->whereIn('p.status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.delivered_datetime', [$startDateTime, $endDateTime])
                        ->where('p.status_id', 9);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.assign_driver_datetime', [$startDateTime, $endDateTime])
                        ->where('p.status_id', 6);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.arrive_warehouse_datetime', [$startDateTime, $endDateTime])
                        ->where('p.status_id', 5);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime,$userId) {
                    $q->whereBetween('p.returned_datetime', [$startDateTime, $endDateTime])
                        ->where('p.returned_uid',$userId)
                        ->where('p.status_id', 11);
                });
            });
            $qAt->whereBetween('updated_at', [$startDateTime, $endDateTime]);
        }
        $attachments = $qAt->limit(700)
        ->pluck('package_id')
        ->toArray();
        $attachmentsLookup = array_flip($attachments);
        // else if($paymentStatus == 1) $qFp->where('pmt.approved',0);
        if($statusId) $qFp->where('p.status_id',$statusId);
        if($search) $qFp->where(function ($q) use ($search,$userClass){
            $search = str_replace(' ', '', $search);
            $q->where('p.receiver_phone', 'ilike', '%' . $search . '%')
            ->orWhere('p.qr_code', 'ilike', '%' . $search . '%')
            ->orWhere('d.fleet_tracking_number', 'ilike', '%' . $search . '%');
            if($userClass == 'driver') $q->orWhere('m.phone', 'ilike', '%' . $search . '%');
        });
        // $fleetPackages = $qFp->get();
        $callbackMapper = function ($f) use($isKm,$userClass,$attachmentsLookup){
            $warehouse_datetime = Helper::formatCustomDateTime($f->arrive_warehouse_datetime,'d-M-Y H:i A');
            $finished_date = $f->delivered_datetime;
            $f->total = $userClass == 'merchant' ? ($f->cod ? $f->price:"0"):$f->driver_total;
            $statusId = $f->status_id;

            if($statusId == 6) $finished_date = $f->arrive_warehouse_datetime;
            if($statusId == 10) $finished_date = $f->failed_datetime;
            if($statusId == 11) $finished_date = $f->returned_datetime;
            if($statusId == 19) $finished_date = $f->failed_datetime;
            $f->has_img = isset($attachmentsLookup[$f->package_id]);
            if($isKm){
                $f->status_code = GeneralSettingService::$statusCodeTrans[$statusId] ?? '';
            }
            if($userClass == 'merchant'){
                if($f->payer == 'sender'){
                    $f->delivery_fee = Helper::getNumber($f->delivery_fee + $f->extra_charge);
                }
            }
            // $driverPhone = $statusId == 11 ? : $f->driver_phone;\
            $telegramPhone = $userClass == 'merchant' ? $f->driver_phone : $f->merchant_phone;
            $f->telegram_url = AppSetting::getTelegramLink($userClass,$f->receiver_phone,$telegramPhone);
            // else $f->telegram_url = Helper::generateTelegramLink($f->merchant_phone);
            $f->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
            $f->arrive_warehouse_datetime = $warehouse_datetime;
            unset($f->failed_datetime,$f->returned_datetime,$f->delivered_datetime);
            return $f;
        };
        return DataResponse::PaginationV1($qFp,$req,'',[],500,$callbackMapper);
    }

    public static function getHistoryPackagesV1(Request $req,$user=null,$reqSearch=false,$userClass='driver'){
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $userId = $user?->id;
        $isKm = in_array($req->lang,['kh','km']);
        $statusIds = [9,10,11,19];
        if($reqSearch) $statusIds[] = 6;
        if($reqSearch && !$search) return DataResponse::Pagination(new Collection(),$req);

        $latestPackages = DB::table('delivery_packages as dp1')
        ->selectRaw('DISTINCT ON (dp1.package_id) dp1.*')
        ->orderBy('dp1.package_id')
        ->orderByDesc('dp1.id');

        $driverInfo = '';

        $qFp = Delivery::query()->fromRaw('deliveries as d')
        ->joinSub($latestPackages, 'dp', 'd.id', 'dp.delivery_id')
        // ->join('delivery_packages as dp','d.id','dp.delivery_id')
        // ->where('dp.delay_count',0)->where('dp.is_deleted',0)->where('dp.has_swap',0)
        ->where([
            ['dp.delay_count', 0],
            ['dp.is_deleted', 0],
            ['dp.has_swap', 0],
        ]);
        if($userClass == 'merchant'){
            $qFp->join('users as dv','dv.id','d.driver_id');
            $driverInfo = ',dv.phone as driver_phone';
        }

        $qFp->join('packages as p','p.id','dp.package_id')
        ->join('users as m','m.id','p.merchant_id')
        // ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        // ->leftJoin('payments as pmt','pmt.id','p.merchant_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('p.driver_id,p.returned_uid,p.payer,p.extra_charge,p.cod,p.price,p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,p.status_id,trs.name as status_code,d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,p.delivered_datetime,p.arrive_warehouse_datetime,p.returned_datetime'.$driverInfo)
        // ->whereIn('dp.status_id',$statusIds)

        ->orderByRaw('dp.status_id = ? ASC',[6])
        ->orderByRaw('
            CASE
                WHEN p.status_id = 9 THEN p.delivered_datetime
                WHEN p.status_id = 10 THEN p.failed_datetime
                WHEN p.status_id = 19 THEN p.failed_datetime
                WHEN p.status_id = 11 THEN p.returned_datetime
            END DESC
        ');
        if(!$paymentStatus){
            $qFp->where(function ($q) use ($userId,$statusIds,$userClass) {
                $q->whereIn('p.status_id', $statusIds)
                ->orWhere(function ($subQuery) use ($userId,$userClass) {
                    $subQuery->where('p.status_id', 11);
                    if($userClass == 'driver') $subQuery->where('p.returned_uid', $userId);
                });
            });
        }else{
            $qFp->whereIn('p.status_id',[9,19]);
        }

        if($paymentStatus == 2 && $userClass=='driver'){
            $qFp->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('payment_packages as pp')
                    ->whereColumn('pp.package_id', 'p.id')
                    ->where('pp.payer_type', 'driver')
                    ->where('pp.is_deleted', false);
            })
            ->orWhereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('disbursement_packages as dp')
                    ->whereColumn('dp.package_id', 'p.id')
                    ->where('dp.payee_type', 'driver')
                    ->where('dp.is_deleted', false);
            });
        }else if($paymentStatus == 1 && $userClass == 'driver'){
            $qFp->whereNotExists(function ($sub) {
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
                    ->where('dp.is_deleted', false);
            });
        }
        if($user && $userClass=='driver'){
            $qFp->where(function ($query) use ($userId) {
                $query->where(function ($subQuery) use ($userId) {
                    $subQuery->where('p.status_id', '!=', 11)
                            ->where('p.driver_id', $userId);
                })->orWhere(function ($subQuery) use ($userId) {
                    $subQuery->where('p.status_id', 11)
                            ->where('p.returned_uid', $userId);
                });
            });

        }else if($user && $userClass=='merchant'){
            $qFp->where('p.merchant_id',$userId);
        }

        $qAt = PackageAttachment::where('hidden', 0);

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $startDateTime = $startDate . ' 00:00:00';
            $endDateTime = $endDate . ' 23:59:59';
            $qFp->where(function($q) use ($startDateTime, $endDateTime,$userId) {
                $q->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.failed_datetime', [$startDateTime, $endDateTime])
                        ->whereIn('p.status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.delivered_datetime', [$startDateTime, $endDateTime])
                        ->where('p.status_id', 9);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.assign_driver_datetime', [$startDateTime, $endDateTime])
                        ->where('p.status_id', 6);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereBetween('p.arrive_warehouse_datetime', [$startDateTime, $endDateTime])
                        ->where('p.status_id', 5);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime,$userId) {
                    $q->whereBetween('p.returned_datetime', [$startDateTime, $endDateTime])
                        ->where('p.returned_uid',$userId)
                        ->where('p.status_id', operator: 11);
                });
            });
            $qAt->whereBetween('updated_at', [$startDateTime, $endDateTime]);
        }
        $attachments = $qAt->limit(700)
        ->pluck('package_id')
        ->toArray();
        $attachmentsLookup = array_flip($attachments);
        // else if($paymentStatus == 1) $qFp->where('pmt.approved',0);
        if($statusId) $qFp->where('p.status_id',$statusId);
        if($search) $qFp->where(function ($q) use ($search,$userClass){
            $search = str_replace(' ', '', $search);
            $q->where('p.receiver_phone', 'ilike', '%' . $search . '%')
            ->orWhere('p.qr_code', 'ilike', '%' . $search . '%')
            ->orWhere('d.fleet_tracking_number', 'ilike', '%' . $search . '%');
            if($userClass == 'driver') $q->orWhere('m.phone', 'ilike', '%' . $search . '%');
        });
        // $fleetPackages = $qFp->get();
        $callbackMapper = function ($f) use($isKm,$userClass,$attachmentsLookup){
            $warehouse_datetime = Helper::formatCustomDateTime($f->arrive_warehouse_datetime,'d-M-Y H:i A');
            $finished_date = $f->delivered_datetime;
            $f->total = $userClass == 'merchant' ? ($f->cod ? $f->price:"0"):$f->driver_total;
            $statusId = $f->status_id;

            if($statusId == 6) $finished_date = $f->arrive_warehouse_datetime;
            if($statusId == 10) $finished_date = $f->failed_datetime;
            if($statusId == 11) $finished_date = $f->returned_datetime;
            if($statusId == 19) $finished_date = $f->failed_datetime;
            $f->has_img = isset($attachmentsLookup[$f->package_id]);
            if($isKm){
                $f->status_code = GeneralSettingService::$statusCodeTrans[$statusId] ?? '';
            }
            if($userClass == 'merchant'){
                if($f->payer == 'sender'){
                    $f->delivery_fee = Helper::getNumber($f->delivery_fee + $f->extra_charge);
                }
            }
            // $driverPhone = $statusId == 11 ? : $f->driver_phone;\
            $telegramPhone = $userClass == 'merchant' ? $f->driver_phone : $f->merchant_phone;
            $f->telegram_url = AppSetting::getTelegramLink($userClass,$f->receiver_phone,$telegramPhone);
            // else $f->telegram_url = Helper::generateTelegramLink($f->merchant_phone);
            $f->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
            $f->arrive_warehouse_datetime = $warehouse_datetime;
            unset($f->failed_datetime,$f->returned_datetime,$f->delivered_datetime);
            return $f;
        };
        return DataResponse::PaginationV1($qFp,$req,'',[],500,$callbackMapper);
    }

    public static function getTrackingPackages(Request $req, $user, $statusId)
    {
        $today = now();
        $dateAgo = Helper::getDateDaysAgo(0);
        $lang = $req->lang;

        $statusNames = [
            9 => 'Delivered',
            10 => 'Failed',
            19 => 'Falied With Fee',
            11 => 'Returned'
        ];

        $dateKeys = [
            9 => 'delivered_datetime',
            10 => 'failed_datetime',
            11 => 'returned_datetime',
            19 => 'failed_datetime'
        ];

        $dateField = $dateKeys[$statusId] ?? 'delivered_datetime';

        // Get package IDs with attachments and convert to lookup for fast has_img check
        $attachmentsLookup = PackageAttachment::where('hidden', 0)
            ->whereBetween('updated_at', [$dateAgo, $today])
            ->limit(700)
            ->pluck('package_id')
            ->flip(); // No need for toArray then array_flip

        // Prepare base query
        $packagesQuery = Package::where('merchant_id', $user->id)
            ->with(['driver:id,user_name,phone','activeDeliveryPackage:package_id,id,delivery_id','activeDeliveryPackage.delivery:id,fleet_tracking_number']) // Limit driver fields
            ->where('status_id', $statusId)
            ->where('is_deleted', 0)

            ->select([
                'id', 'merchant_id', 'receiver_phone', 'receiver_address', 'taxi_fee',
                'cod', 'price', 'delivery_fee', 'remarks', 'driver_id',
                'arrive_warehouse_datetime',$dateField
            ]);
        if(!in_array($statusId,[9,19])){
            $packagesQuery->whereBetween($dateField, [$dateAgo, $today]);
        }

        // Format package output
        $callback = function ($package) use ($lang, $attachmentsLookup, $statusNames, $statusId) {
            $driver = $package->driver;

            $package->price = (float) $package->price;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->taxi_fee = (float) $package->taxi_fee;
            $package->delivery_fee = (float) $package->delivery_fee;
            $package->has_img = isset($attachmentsLookup[$package->id]);
            $package->status_code = $lang === 'km'
                ? (GeneralSettingService::$statusCodeTrans[$statusId] ?? '')
                : ($statusNames[$statusId] ?? '');
            $package->tracking_number = $package->activeDeliveryPackage->delivery->fleet_tracking_number;
            // Safely assign driver details
            $package->driver_phone = $driver->phone ?? '';
            $package->driver_name = $driver->user_name ?? '';
            $package->total = $package->cod_fee;
            $package->fee = $package->delivery_fee;

            // Format dates
            // $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
            $package->arrive_warehouse_date = Helper::formatCustomDateTime($package->arrive_warehouse_datetime,'d-M-Y');
            $package->arrive_warehouse_time = Helper::formatCustomDateTime($package->arrive_warehouse_datetime,'h:i A');
            // $package->delivered_datetime = Helper::formatCustomDateTime($package->delivered_datetime);
            if(in_array($statusId,[10,19])){
                $package->failed_date = Helper::formatCustomDateTime($package->failed_datetime,'d-M-Y');
                $package->failed_time = Helper::formatCustomDateTime($package->failed_datetime,'h:i A');
            }else if($statusId == 11){
                $package->returned_date = Helper::formatCustomDateTime($package->returned_datetime,'d-M-Y');
                $package->returned_time = Helper::formatCustomDateTime($package->returned_datetime,'h:i A');
            }else if($statusId == 9){
                $package->delivered_date = Helper::formatCustomDateTime($package->delivered_datetime,'d-M-Y');
                $package->delivered_time = Helper::formatCustomDateTime($package->delivered_datetime,'h:i A');
            }
            // Generate Telegram URL
            $package->telegram_url = AppSetting::getTelegramLink('merchant', $package->receiver_phone, $driver->phone ?? '');

            unset($package->driver,$package->activeDeliveryPackage); // Remove the relation to clean output

            return $package;
        };

        return DataResponse::PaginationV1($packagesQuery, $req, '', [], 200, $callback);
    }


    // public static function getHistoryPackagesV1(Request $req,$user=null,$reqSearch=false,$userClass='driver'){
    //     $paymentStatus = $req->payment_status_id ?? null;
    //     $statusId = $req->status_id ?? null;
    //     $search = $req->search ?? null;
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $userId = $user?->id;
    //     $isKm = in_array($req->lang,['kh','km']);
    //     $statusIds = [9,10,11,19];
    //     if($reqSearch) $statusIds[] = 6;
    //     if($reqSearch && !$search) return DataResponse::Pagination(new Collection(),$req);

    //     // $latestPackages = DB::table('delivery_packages as dp1')
    //     // ->selectRaw('DISTINCT ON (dp1.package_id) dp1.*')
    //     // ->orderBy('dp1.package_id')
    //     // ->orderByDesc('dp1.id');

    //     $driverInfo = '';

    //     $qFp = Package::query()->from('packages as p')
    //     ->join('users as m','m.id','p.merchant_id')
    //     ->where('p.is_deleted',0)
    //     ->join('tracking_statuses as trs','trs.id','p.status_id');
    //     if($userClass == 'merchant'){
    //         $qFp->join('users as dv','dv.id','p.driver_id');
    //         $driverInfo = ',dv.phone as driver_phone';
    //     }

    //     // $qFp->join('packages as p','p.id','dp.package_id')
    //     // ->join('users as m','m.id','p.merchant_id')
    //     // ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
    //     // // ->leftJoin('payments as pmt','pmt.id','p.merchant_payment_id')
    //     // ->join('tracking_statuses as trs','trs.id','p.status_id')
    //     $qFp->selectRaw('p.driver_id,p.returned_uid,p.payer,p.extra_charge,p.cod,p.price,p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,p.status_id,trs.name as status_code,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,p.delivered_datetime,p.arrive_warehouse_datetime,p.returned_datetime'.$driverInfo)
    //     // ->whereIn('dp.status_id',$statusIds)
    //     ->where(function ($q) use ($userId,$statusIds,$userClass) {
    //         $q->whereIn('p.status_id', $statusIds)
    //         ->orWhere(function ($subQuery) use ($userId,$userClass) {
    //             $subQuery->where('p.status_id', 11);
    //             if($userClass == 'driver') $subQuery->where('p.returned_uid', $userId);
    //         });
    //     })
    //     ->orderByRaw('p.status_id = ? ASC',[6])
    //     ->orderByRaw('
    //         CASE
    //             WHEN p.status_id = 9 THEN p.delivered_datetime
    //             WHEN p.status_id = 10 THEN p.failed_datetime
    //             WHEN p.status_id = 19 THEN p.failed_datetime
    //             WHEN p.status_id = 11 THEN p.returned_datetime
    //         END DESC
    //     ');

    //     if($paymentStatus == 2 && $userClass=='driver'){
    //         // $qFp->where('pmt.approved',1);
    //     }
    //     if($user && $userClass=='driver'){
    //         $qFp->where(function ($query) use ($userId) {
    //             $query->where(function ($subQuery) use ($userId) {
    //                 $subQuery->where('p.status_id', '!=', 11)
    //                         ->where('p.driver_id', $userId);
    //             })->orWhere(function ($subQuery) use ($userId) {
    //                 $subQuery->where('p.status_id', 11)
    //                         ->where('p.returned_uid', $userId);
    //             });
    //         });

    //     }else if($user && $userClass=='merchant'){
    //         $qFp->where('p.merchant_id',$userId);
    //     }

    //     $qAt = PackageAttachment::where('hidden', 0);

    //     if($startDate && $endDate){
    //         $startDate = Helper::dateYMD($startDate);
    //         $endDate = Helper::dateYMD($endDate);
    //         $startDateTime = $startDate . ' 00:00:00';
    //         $endDateTime = $endDate . ' 23:59:59';
    //         $qFp->where(function($q) use ($startDateTime, $endDateTime,$userId) {
    //             $q->where(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->whereBetween('p.failed_datetime', [$startDateTime, $endDateTime])
    //                     ->whereIn('p.status_id', [10, 19]);
    //             })
    //             ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->whereBetween('p.delivered_datetime', [$startDateTime, $endDateTime])
    //                     ->where('p.status_id', 9);
    //             })
    //             ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->whereBetween('p.assign_driver_datetime', [$startDateTime, $endDateTime])
    //                     ->where('p.status_id', 6);
    //             })
    //             ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->whereBetween('p.arrive_warehouse_datetime', [$startDateTime, $endDateTime])
    //                     ->where('p.status_id', 5);
    //             })
    //             ->orWhere(function ($q) use ($startDateTime, $endDateTime,$userId) {
    //                 $q->whereBetween('p.returned_datetime', [$startDateTime, $endDateTime])
    //                     ->where('p.returned_uid',$userId)
    //                     ->where('p.status_id', 11);
    //             });
    //         });
    //         $qAt->whereBetween('updated_at', [$startDateTime, $endDateTime]);
    //     }
    //     $attachments = $qAt->limit(700)
    //     ->pluck('package_id')
    //     ->toArray();
    //     $attachmentsLookup = array_flip($attachments);
    //     // else if($paymentStatus == 1) $qFp->where('pmt.approved',0);
    //     if($statusId) $qFp->where('p.status_id',$statusId);
    //     if($search) $qFp->where(function ($q) use ($search,$userClass){
    //         $search = str_replace(' ', '', $search);
    //         $q->where('p.receiver_phone', 'ilike', '%' . $search . '%')
    //         ->orWhere('p.qr_code', 'ilike', '%' . $search . '%');
    //         // ->orWhere('d.fleet_tracking_number', 'ilike', '%' . $search . '%');
    //         if($userClass == 'driver') $q->orWhere('m.phone', 'ilike', '%' . $search . '%');
    //     });
    //     // $fleetPackages = $qFp->get();
    //     $callbackMapper = function ($f) use($isKm,$userClass,$attachmentsLookup){
    //         $warehouse_datetime = Helper::formatCustomDateTime($f->arrive_warehouse_datetime,'d-M-Y H:i A');
    //         $finished_date = $f->delivered_datetime;
    //         $f->total = $userClass == 'merchant' ? ($f->cod ? $f->price:"0"):$f->driver_total;
    //         $statusId = $f->status_id;

    //         if($statusId == 6) $finished_date = $f->arrive_warehouse_datetime;
    //         if($statusId == 10) $finished_date = $f->failed_datetime;
    //         if($statusId == 11) $finished_date = $f->returned_datetime;
    //         if($statusId == 19) $finished_date = $f->failed_datetime;
    //         $f->has_img = isset($attachmentsLookup[$f->package_id]);
    //         if($isKm){
    //             $f->status_code = GeneralSettingService::$statusCodeTrans[$statusId] ?? '';
    //         }
    //         if($userClass == 'merchant'){
    //             if($f->payer == 'sender'){
    //                 $f->delivery_fee = Helper::getNumber($f->delivery_fee + $f->extra_charge);
    //             }
    //         }
    //         // $driverPhone = $statusId == 11 ? : $f->driver_phone;\
    //         $telegramPhone = $userClass == 'merchant' ? $f->driver_phone : $f->merchant_phone;
    //         $f->telegram_url = AppSetting::getTelegramLink($userClass,$f->receiver_phone,$telegramPhone);
    //         // else $f->telegram_url = Helper::generateTelegramLink($f->merchant_phone);
    //         $f->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
    //         $f->arrive_warehouse_datetime = $warehouse_datetime;
    //         unset($f->failed_datetime,$f->returned_datetime,$f->delivered_datetime);
    //         return $f;
    //     };
    //     return DataResponse::PaginationV1($qFp,$req,'',[],500,$callbackMapper);
    // }
}
