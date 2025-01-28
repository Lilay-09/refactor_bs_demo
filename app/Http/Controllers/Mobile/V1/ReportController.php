<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\TrackingStatus;
use App\Services\GeneralSettingService;
use Helper;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    //
    public function merchantDailyPackages(Request $req){
        $isKm = !($req->lang == 'en');
        $qP = Package::where('is_deleted',0)
        ->whereIn('status_id',[9,10,19,11])
        ->selectRaw('id,qr_code,extra_charge,price,status_id,failed_datetime,delivered_datetime,returned_datetime');
        $qP->orderByRaw('
            CASE
                WHEN status_id = ? THEN 1
                WHEN status_id = ? THEN 2
                WHEN status_id = ? THEN 3
                WHEN status_id = ? THEN 4
                ELSE 7
            END', [9, 19, 11,10]
        )->orderByRaw('DATE(failed_datetime) DESC,DATE(delivered_datetime) DESC');
        $packages = $qP->get();
        // return $packages;
        $groupedPackages = collect($packages)->map(function ($item) use (&$grand) {
            $finishDate = $item->failed_datetime;
            if ($item->status_id == 9) $finishDate = $item->delivered_datetime;
            if ($item->status_id == 5) $finishDate = $item->arrive_warehouse_datetime;
            if ($item->status_id == 6) {
                $finishDate = $item->assign_driver_datetime;
                $item->failed_datetime = '';
            }
            if ($item->status_id == 10 || $item->status_id == 19) $finishDate = $item->failed_datetime;
            if ($item->status_id == 11) $finishDate = $item->returned_datetime;

            $item->groupDate = Helper::dateDMY($finishDate);
            // Return the modified object
            return $item;
        })->groupBy('groupDate')
        ->map(function ($group, $date) use (&$grand,$isKm){
            $group->each(function ($item) use (&$grand,$isKm,&$totalDeliveryFee) {
                $item->finished_date = $item->failed_datetime ? Helper::dateDMY($item->failed_datetime): Helper::dateDMY($item->delivered_datetime);
                $finished_time = $item->failed_datetime ? Helper::formatCustomDateTime($item->failed_datetime,'h:i:s A'):Helper::formatCustomDateTime($item->delivered_datetime,'h:i:s A');
                $item->finished_time = $finished_time;
                $isCal = in_array($item->status_id,[9,19]);
                $item->price = $item->cod ? $item->price:0;
                $total = $item->cod ? $item->price : 0;
                if($item->payer == 'sender') {
                    $item->delivery_fee = $isCal ? ($item->delivery_fee + $item->extra_charge) : 0;
                    $total -= $item->delivery_fee + $item->extra_charge + $item->taxi_fee;
                }else $item->delivery_fee = 0;
                $totalDeliveryFee += $item->delivery_fee;
                $item->total = $isCal ? $total : 0;
                if(in_array($item->status_id,[9,19])) $grand += Helper::getNumber($total,2);
                if($isKm) {
                    $item->status_code = GeneralSettingService::$statusCodeTrans[$item->status_id] ?? '';
                    $item->payer = GeneralSettingService::$payerTrans[$item->payer] ?? '';
                    if(in_array($item->status_id,[9,19])){
                        $item->payment_status = GeneralSettingService::$pmtStatusTrans['unpaid'];
                        if($item->approved) $item->payment_status = GeneralSettingService::$pmtStatusTrans['paid'];
                    }else{
                        $item->payment_status = GeneralSettingService::$pmtStatusTrans['pending'];
                    }
                }
                else {
                    $item->payment_status = 'Pending';
                    if(in_array($item->status_id,[9,19])){
                        $item->payment_status = 'Unpaid';
                        if($item->approved) $item->payment_status = 'Paid';
                    }
                    $item->status_code = $item->status->name;
                }
                unset($item->status,$item->groupDate);
            });
            return [
                'date' => $date,
                'details' => $group->toArray(),
            ];
        })->values();

        return ApiResponse::JsonResult($groupedPackages);
    }

    //** Options */
    public function merchantDailyPackagesOption(){
        $statuses = TrackingStatus::whereIn('id',[9,10,11,19])->get();
    }
}
