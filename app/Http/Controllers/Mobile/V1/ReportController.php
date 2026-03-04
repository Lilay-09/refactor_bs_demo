<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
// use App\Models\Delivery;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Models\TrackingStatus;
// use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\PackageTrailServiceImpl;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class ReportController extends Controller
{
    //
    public function merchantDailyPackages(Request $req){
        $isKm = !($req->lang == 'en');
        $user = UserService::getAuthUser();
        // $statusId = $req->status_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = Package::where('is_deleted', 0)
            ->whereIn('status_id', [9, 10, 19, 11])
            ->where('merchant_id', $user->id);

        if ($startDate && $endDate) {
            $startDate = Helper::dateYMD($startDate);
            $endDate   = Helper::dateYMD($endDate);

            $qP->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->whereIn('status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 9);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('returned_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 11);
                });
            });
        }

        // 👇 This gives you counts directly
        $counts = $qP->selectRaw("
            SUM(CASE WHEN status_id = 9 THEN 1 ELSE 0 END) as delivered_count,
            SUM(CASE WHEN status_id = 10 THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN status_id = 19 THEN 1 ELSE 0 END) as failed_with_fee_count,
            SUM(CASE WHEN status_id = 11 THEN 1 ELSE 0 END) as returned_count,
            COUNT(*) as total_count,

            -- total_usd: sum price if cod = true, minus fees if payer = sender
            SUM(
                CASE 
                    WHEN cod = true 
                    THEN price - 
                        (CASE WHEN payer = 'sender' THEN (delivery_fee + extra_charge + taxi_fee) ELSE 0 END) 
                    ELSE 0 
                END
            ) as total_usd,

            -- total_khr: only delivered (status_id = 9), cod = true
            SUM(
                CASE 
                    WHEN cod = true AND status_id = 9 
                    THEN price_khr - 
                        (CASE WHEN payer = 'sender' THEN (delivery_fee + extra_charge + taxi_fee) ELSE 0 END) 
                    ELSE 0 
                END
            ) as total_khr
        ")->first();


        $packageInfo = [
            'delivered_count'       => $counts->delivered_count ?? 0,
            'failed_count'          => $counts->failed_count ?? 0,
            'failed_with_fee_count' => $counts->failed_with_fee_count ?? 0,
            'returned_count'        => $counts->returned_count ?? 0,
        ];

        $totalCount = $counts->total_count ?? 0;

        $data = [
            'total_usd'=> (string)Helper::getNumber($counts->total_usd ?? 0,2),
            'total_khr'=> (string)Helper::getNumber($counts->total_khr ?? 0,2),
            'total_count'=> (string) $totalCount,
            'package_info' => $packageInfo
        ];

        return ApiResponse::JsonResult($data);
    }


    public function merchantDailyPackagesPreview(Request $req)
    {
        $user = UserService::getAuthUser('merchant');
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $userId = $user->id;
        $isKm = $req->lang != 'en';
        $statusId = $req->status_id ?? null;
        $merchantInfo = GeneralSettingService::getMerchantById($userId);
        
        $qP = Package::where('is_deleted',0)
        ->whereIn('status_id',[5,6,9,10,19,11])
        ->where('merchant_id',$userId)
        ->select([
            'id','delivery_remarks','payer','cod','driver_id','qr_code','extra_charge','delivery_fee',
            'extra_charge','price','price_khr','status_id','failed_datetime','delivered_datetime','returned_datetime',
            'receiver_phone','receiver_address','driver_cod_usd','driver_cod_khr','taxi_fee',
            'arrive_warehouse_datetime','assign_driver_datetime'
        ]);
        
        $qP->orderByRaw('
            CASE
                WHEN status_id = ? THEN 1
                WHEN status_id = ? THEN 2
                WHEN status_id = ? THEN 3
                WHEN status_id = ? THEN 4
                ELSE 7
            END', [9, 19, 10, 11]
        );
        
        $qP->orderByRaw('
            CASE
                WHEN status_id = 5 THEN arrive_warehouse_datetime
                WHEN status_id = 6 THEN assign_driver_datetime
                WHEN status_id = 9 THEN delivered_datetime
                WHEN status_id = 10 THEN failed_datetime
                WHEN status_id = 19 THEN failed_datetime
                WHEN status_id = 11 THEN returned_datetime
            END DESC
        ');

        if(is_numeric($statusId)) $qP->where('status_id',$statusId);
        
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->whereIn('status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('arrive_warehouse_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 5);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('assign_driver_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 6);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 9);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('returned_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 11);
                });
            });
        }
        
        $packages = $qP->get();

        $groupedPackages = collect($packages)->map(function ($item) {
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
            return $item;
        })->groupBy('groupDate')
        ->map(function ($group, $date) use ($isKm){
            $totalCodUsd = 0;
            $totalCodKhr = 0;
            $totalFees = 0;
            $totalBaseFees = 0;
            $totalTaxiFees = 0;
            
            $group->each(function ($item) use ($isKm, &$totalCodUsd, &$totalCodKhr, &$totalFees, &$totalBaseFees, &$totalTaxiFees) {
                if(!$item->driver) {
                    $item->driver_name = $item->returnUser?->username;
                    $item->driver_phone = $item->returnUser?->phone;
                }
                
                $item->finished_date = $item->failed_datetime ? Helper::dateDMY($item->failed_datetime): Helper::dateDMY($item->delivered_datetime);
                $finished_time = $item->failed_datetime ? Helper::formatCustomDateTime($item->failed_datetime,'h:i:s A'):Helper::formatCustomDateTime($item->delivered_datetime,'h:i:s A');
                $item->finished_time = $finished_time;
                $isCal = in_array($item->status_id,[9,19]);
                $item->price = $item->cod ? (float)$item->price:0;
                $item->extra_charge = (float)$item->extra_charge;
                
                // Only count fees for unpaid packages (status 9 or 19 that are not approved)
                $isUnpaid = in_array($item->status_id, [9, 19]) && !$item->approved;
                
                $deliveryFees = 0;
                $baseFee = 0;
                $taxiFee = 0;
                
                if($isUnpaid && $item->payer == 'sender') {
                    $baseFee = (float)$item->delivery_fee + (float)$item->extra_charge;
                    $taxiFee = (float)$item->taxi_fee;
                    $deliveryFees = $baseFee + $taxiFee;
                }
                
                $item->fees = (float)$item->delivery_fee + (float)$item->extra_charge + (float)$item->taxi_fee;
                $totalFees += $deliveryFees;
                $totalBaseFees += in_array($item->status_id, [9, 19]) ? $baseFee : 0;
                $totalTaxiFees += in_array($item->status_id, [9, 19]) ? $taxiFee : 0;
                
                $total = ($item->cod && $item->status_id == 9) ? $item->price : 0;
                $item->total = $isCal ? (float)Helper::getNumber($total) : 0;
                
                if($isKm) {
                    $item->status_code = GeneralSettingService::$statusCodeTrans[$item->status_id] ?? '';
                    $item->payer = GeneralSettingService::$payerTrans[$item->payer] ?? '';
                    if(in_array($item->status_id,[9,19])){
                        $item->payment_status = GeneralSettingService::$pmtStatusTrans['unpaid'];
                        if($item->approved) $item->payment_status = GeneralSettingService::$pmtStatusTrans['paid'];
                    }else{
                        $item->payment_status = GeneralSettingService::$pmtStatusTrans['pending'];
                    }
                } else {
                    $item->payment_status = 'Pending';
                    if(in_array($item->status_id,[9,19])){
                        $item->payment_status = 'Unpaid';
                        if($item->approved) $item->payment_status = 'Paid';
                    }
                    $item->status_code = $item->status->name;
                }
                
                // Only count COD for unpaid packages
                $codUsd = $isUnpaid ? ($item->driver_cod_usd ?? 0) : 0;
                $codKhr = $isUnpaid ? ($item->driver_cod_khr ?? 0) : 0;
                
                $totalCodUsd += $codUsd;
                $totalCodKhr += $codKhr;
                
                $item->cod_usd = $item->price ?? 0;
                $item->cod_khr = $item->price_khr ?? 0;
                
                $rowTotal = PackageTrailServiceImpl::deductRowAmountBase($codUsd, $codKhr, $deliveryFees);
                $item->total_usd = $rowTotal['amount_usd'] > 0 ? $rowTotal['amount_usd'] : 0;
                $item->total_khr = $rowTotal['amount_khr'] > 0 ? $rowTotal['amount_khr'] : 0;
                $item->receiver_address = preg_replace('/\x{17D2}$/u', '', $item->receiver_address);
                
                unset($item->driver,$item->status,$item->groupDate,$item->failed_datetime,$item->delivered_datetime,$item->returned_datetime);
            });

            // Calculate owe fees using the same logic as getMerchantOweFees
            $sumUsd = $totalCodUsd;
            $sumKhr = $totalCodKhr;
            $totalFeeAmount = $totalBaseFees + $totalTaxiFees;
            
            // Owe USD calculation
            $oweUsd = round(
                max(
                    $totalFeeAmount - $sumUsd - min($sumKhr/4000, $totalFeeAmount - $sumUsd),
                    0
                ),
                2
            );
            
            // Owe KHR calculation
            $oweKhr = round(
                max(
                    $totalFeeAmount * 4000 - ($sumUsd * 4000 + $sumKhr),
                    0
                ),
                0
            );
            
            // Amount calculation (what merchant receives)
            $amount = round($sumUsd + $sumKhr/4000 - $totalFeeAmount, 2);
            
            $eachGrand = PackageTrailServiceImpl::deductRowAmountBase($totalCodUsd, $totalCodKhr, $totalFees);
            
            return [
                'date' => $date,
                'list' => $group->toArray(),
                'total' => [
                    'cod_usd' => $totalCodUsd,
                    'cod_khr' => $totalCodKhr,
                    'usd' => $eachGrand['amount_usd'] ?? 0,
                    'khr' => $eachGrand['amount_khr'] ?? 0,
                    'fees' => $totalFees,
                    'base_fees' => $totalBaseFees,
                    'taxi_fees' => $totalTaxiFees,
                    'total_fee' => $totalFeeAmount,
                    'owe_usd' => $oweUsd,
                    'owe_khr' => $oweKhr,
                    'amount' => $amount,
                ],
            ];
        })->values();

        if(!isset($groupedPackages[0])) return ApiResponse::NotFound('No data available!');
        
        $data = [
            'title' => 'Report',
            'merchant' => $merchantInfo,
            'date' => date('d-M-Y',strtotime($startDate)) .' to '. date('d-M-Y',strtotime($endDate)),
            'data' => $groupedPackages
        ];
        
        $pdf = new Mpdf([
            'default_font' => 'khmeros',
            'mode' => 'utf-8',
            'format' => 'A4',
        ]);

        $html = view('pdf.merchant_reportv2', $data);
        $pdf->WriteHTML($html);
        $fileName = 'report-' . time() . '.pdf';
        $pdf->Output($fileName, 'i');
        exit;
    }
    

    //** Options */
    public function merchantDailyPackagesOption(Request $request)
    {
        $isKm = $request->lang != 'en';
        $statuses = TrackingStatus::whereIn('id', [9, 10, 11, 19])
            ->selectRaw('id, name')
            ->get()
            ->map(function ($status) use ($isKm) {
                return [
                    'id' => $status->id,
                    'name' => $isKm ? (GeneralSettingService::$statusCodeTrans[$status->id] ?? '') : $status->name
                ];
            })
            ->toArray(); // Convert to array for array_unshift()
        // Add translated "All" option at the beginning
        array_unshift($statuses, ['id' => 0, 'name' => __('messages.all')]);
        return ApiResponse::JsonResult($statuses);
    }


    

}
