<?php

namespace App\Services\Mobile;

use App\Models\Delivery;
use App\Services\GeneralSettingService;
use DataResponse;
use DB;
use Helper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ReusableService
{
    // Your service methods go here
    public static function getHistoryPackages(Request $req,$user=null,$reqSearch=false,$userClass='driver'){
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $isKm = in_array($req->lang,['kh','km']);
        $statusIds = [9,10,11,19];
        if($reqSearch) $statusIds[] = 6;
        if($reqSearch && !$search) return DataResponse::Pagination(new Collection(),$req);
        $qFp = Delivery::fromRaw('deliveries as d')
        ->join('delivery_packages as dp','d.id','dp.delivery_id')
        ->where(function($q){
            $q->where('dp.delay_count',0)->where('dp.is_deleted',0);
        })
        ->join('packages as p','p.id','dp.package_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        // ->leftJoin('payments as pmt','pmt.id','p.merchant_payment_id')
        ->join('tracking_statuses as trs','trs.id','dp.status_id')
        ->selectRaw('p.payer,p.extra_charge,p.price,p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,dp.status_id,trs.name as status_code,d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,p.delivered_datetime,p.arrive_warehouse_datetime,p.returned_datetime')
        ->whereIn('dp.status_id',$statusIds)
        ->orderByRaw('
            CASE
                WHEN dp.status_id = ? THEN 1
                WHEN dp.status_id = ? THEN 2
                WHEN dp.status_id = ? THEN 3
                WHEN dp.status_id = ? THEN 4
                ELSE 7
            END DESC', [9,10,11,19]
        );

        if($paymentStatus == 2){
            $qFp->where('pmt.approved',1);
        }
        if($user && $userClass=='driver'){
            $qFp->where('p.driver_id',$user->id);
        }else if($user && $userClass=='merchant'){
            $qFp->where('p.merchant_id',$user->id);
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            // $qFp->whereRaw('p.failed_datetime::DATE >= ? AND p.failed_datetime::DATE <= ? OR p.delivered_datetime::DATE >= ? AND p.delivered_datetime::DATE <= ? OR p.returned_datetime::DATE >= ? AND p.returned_datetime::DATE <= ?', [
            //     $startDate, $endDate,
            //     $startDate, $endDate,
            //     $startDate, $endDate,
            // ]);
             $qFp->where(function($q) use ($startDate, $endDate) {
                $q->where(function($q) use ($startDate, $endDate) {
                    // For status_id 10 or 19, query only failed_datetime
                    $q->whereRaw('
                        (p.failed_datetime::DATE >= ? AND p.failed_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->whereIn('p.status_id', [10, 19]);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereRaw('
                        (p.delivered_datetime::DATE >= ? AND p.delivered_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('p.status_id', 9);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 6, query only assign_driver_datetime
                    $q->whereRaw('
                        (p.assign_driver_datetime::DATE >= ? AND p.assign_driver_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('p.status_id', 6);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 5, query only arrive_warehouse_datetime
                    $q->whereRaw('
                        (p.arrive_warehouse_datetime::DATE >= ? AND p.arrive_warehouse_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('p.status_id', 5);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 11, query only returned_datetime
                    $q->whereRaw('
                        (p.returned_datetime::DATE >= ? AND p.returned_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('p.status_id', 11);
                });
            });
        }
        // else if($paymentStatus == 1) $qFp->where('pmt.approved',0);
        if($statusId) $qFp->where('p.status_id',$statusId);
        if($search) $qFp->where(function ($q) use ($search){
            $search = str_replace(' ', '', $search);
            $q->where('p.receiver_phone', 'ilike', '%' . $search . '%')
            ->orWhere('m.phone', 'ilike', '%' . $search . '%')
            ->orWhere('p.qr_code', 'ilike', '%' . $search . '%')
            ->orWhere('d.fleet_tracking_number', 'ilike', '%' . $search . '%');
        });
        $fleetPackages = $qFp->get();
        // ->map(function ($f) use($isKm) {
        //     $f->total = (float)$f->driver_total;
        //     $warehouse_datetime = Helper::formatCustomDateTime($f->arrive_warehouse_datetime,'d-M-Y H:i A');
        //     $finished_date = $f->delivered_datetime;
        //     $statusId = $f->status_id;
        //     $f->delivery_fee = (float)$f->delivery_fee;
        //     $f->taxi_fee = (float)$f->taxi_fee;

        //     if($statusId == 6) $finished_date = $f->arrive_warehouse_datetime;
        //     if($statusId == 10) $finished_date = $f->failed_datetime;
        //     if($statusId == 11) $finished_date = $f->returned_datetime;
        //     if($statusId == 19) $finished_date = $f->failed_datetime;
        //     if($isKm){
        //         $f->status_code = GeneralSettingService::$statusCodeTrans[$statusId] ?? '';
        //     }
        //     $f->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
        //     $f->arrive_warehouse_datetime = $warehouse_datetime;
        //     unset($f->failed_datetime,$f->returned_datetime,$f->delivered_datetime);
        //     return $f;

        // });
        foreach($fleetPackages as $f){
            $warehouse_datetime = Helper::formatCustomDateTime($f->arrive_warehouse_datetime,'d-M-Y H:i A');
            $finished_date = $f->delivered_datetime;
            $f->total = $userClass == 'merchant' ? $f->merchant_total:$f->driver_total;
            $statusId = $f->status_id;
            if($statusId == 6) $finished_date = $f->arrive_warehouse_datetime;
            if($statusId == 10) $finished_date = $f->failed_datetime;
            if($statusId == 11) $finished_date = $f->returned_datetime;
            if($statusId == 19) $finished_date = $f->failed_datetime;
            if($isKm){
                $f->status_code = GeneralSettingService::$statusCodeTrans[$statusId] ?? '';
            }
            if($userClass == 'merchant'){
                if($f->payer == 'sender'){
                    $f->delivery_fee = Helper::getNumber($f->delivery_fee + $f->extra_charge);
                }
            }
            $f->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
            $f->arrive_warehouse_datetime = $warehouse_datetime;
            unset($f->failed_datetime,$f->returned_datetime,$f->delivered_datetime);
        }
        return DataResponse::Pagination($fleetPackages,$req);
    }
}
