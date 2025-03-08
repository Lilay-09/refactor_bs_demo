<?php

namespace App\Services\Mobile;

use App\Models\Delivery;
use App\Models\PackageAttachment;
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
        $userId = $user?->id;
        $isKm = in_array($req->lang,['kh','km']);
        $statusIds = [9,10,11,19];
        if($reqSearch) $statusIds[] = 6;
        if($reqSearch && !$search) return DataResponse::Pagination(new Collection(),$req);

        $latestPackages = DB::table('delivery_packages as dp1')
        ->selectRaw('DISTINCT ON (dp1.package_id) dp1.*')
        ->orderBy('dp1.package_id')
        ->orderByDesc('dp1.id');

        $qFp = Delivery::query()->fromRaw('deliveries as d')
        ->joinSub($latestPackages, 'dp', 'd.id', 'dp.delivery_id')
        // ->join('delivery_packages as dp','d.id','dp.delivery_id')
        // ->where('dp.delay_count',0)->where('dp.is_deleted',0)->where('dp.has_swap',0)
        ->where([
            ['dp.delay_count', 0],
            ['dp.is_deleted', 0],
            ['dp.has_swap', 0],
        ])
        ->join('packages as p','p.id','dp.package_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        // ->leftJoin('payments as pmt','pmt.id','p.merchant_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('p.payer,p.extra_charge,p.cod,p.price,p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,p.status_id,trs.name as status_code,d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,p.delivered_datetime,p.arrive_warehouse_datetime,p.returned_datetime')
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
        // ->orderByRaw('
        //     CASE
        //         WHEN dp.status_id = ? THEN 1
        //         WHEN dp.status_id = ? THEN 2
        //         WHEN dp.status_id = ? THEN 3
        //         WHEN dp.status_id = ? THEN 4
        //         ELSE 7
        //     END DESC', [9,10,11,19]
        // );

        if($paymentStatus == 2 && $userClass=='driver'){
            $qFp->where('pmt.approved',1);
        }
        if($user && $userClass=='driver'){
            $qFp->where([
            ['p.driver_id', $userId],
            ['d.driver_id', $userId],
            ['dp.driver_id', $userId]
            ]);
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
            $f->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
            $f->arrive_warehouse_datetime = $warehouse_datetime;
            unset($f->failed_datetime,$f->returned_datetime,$f->delivered_datetime);
            return $f;
        };

        return DataResponse::PaginationV1($qFp,$req,null,[],500,$callbackMapper);
    }
}
