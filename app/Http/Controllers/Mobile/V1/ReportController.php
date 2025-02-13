<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Package;
use App\Models\TrackingStatus;
use App\Services\GeneralSettingService;
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
        $statusId = $req->status_id;
        $grandTotal = 0;
        $packageInfo = [
            'delivered_count' => 0,
            'failed_count' => 0,
            'failed_with_fee_count' => 0,
            'returned_count' => 0
        ];
        $totalCount = 0;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $qP = Package::where('is_deleted',0)
        ->whereIn('status_id',[9,10,19,11])
        ->where('merchant_id',$user->id)
        ->with(['driver:id,user_name,phone','returnUser:id,user_name,phone'])
        ->selectRaw('id,payer,cod,driver_id,qr_code,extra_charge,delivery_fee,extra_charge,price,status_id,failed_datetime,delivered_datetime,returned_datetime,receiver_phone,receiver_address,remarks,delivery_remarks as notes');
        $qP->orderByRaw('
            CASE
                WHEN status_id = ? THEN 1
                WHEN status_id = ? THEN 2
                WHEN status_id = ? THEN 3
                WHEN status_id = ? THEN 4
                ELSE 7
            END', [9, 19, 11,10]
        )->orderByRaw('DATE(failed_datetime) DESC,DATE(delivered_datetime) DESC');
        if(is_numeric($statusId)) $qP->where('status_id',$statusId);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q) use ($startDate, $endDate) {
                    // For status_id 10 or 19, query only failed_datetime
                    $q->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->whereIn('status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 9);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    // For status_id 11, query only returned_datetime
                    $q->whereBetween('returned_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 11);
                });
            });
        }
        $packages = $qP->get();
        // return $packages;
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
            $item->price = (float) ($item->cod ? $item->price:0);
            $item->groupDate = Helper::dateDMY($finishDate);
            // Return the modified object
            return $item;
        })->groupBy('groupDate')
        ->map(function ($group, $date) use (&$grandTotal,&$totalCount,&$packageInfo,$isKm){
            $group->each(function ($item) use (&$grandTotal,&$totalCount,&$packageInfo,$isKm,&$totalDeliveryFee) {
                $item->driver_name = $item->driver?->user_name;
                $item->driver_phone = $item->driver?->phone;
                if(!$item->driver) {
                    $item->driver_name = $item->returnUser?->user_name;
                    $item->driver_phone = $item->returnUser?->phone;
                }
                $item->finished_date = $item->failed_datetime ? Helper::dateDMY($item->failed_datetime): Helper::dateDMY($item->delivered_datetime);
                $finished_time = $item->failed_datetime ? Helper::formatCustomDateTime($item->failed_datetime,'h:i:s A'):Helper::formatCustomDateTime($item->delivered_datetime,'h:i:s A');
                $item->finished_time = $finished_time;
                $isCal = in_array($item->status_id,[9,19]);
                $item->price = ($item->cod && $item->status_id == 9) ? $item->price:0;
                $item->extra_charge = (float)$item->extra_charge;
                $total = $item->cod ? $item->price : 0;
                if($item->payer == 'sender') {
                    $item->delivery_fee = $isCal ? (float)Helper::getNumber(($item->delivery_fee + $item->extra_charge)) : 0;
                    $total -= $item->delivery_fee + $item->extra_charge + $item->taxi_fee;
                }else $item->delivery_fee = 0;
                $totalDeliveryFee += $item->delivery_fee;
                $item->total = $isCal ? (float)Helper::getNumber($total) : 0;
                if(in_array($item->status_id,[9,19])) {
                    $grandTotal += Helper::getNumber($total,2);
                }
                if($item->status_id == 9){
                    $packageInfo['delivered_count'] += 1;
                }else if($item->status_id == 10){
                    $packageInfo['failed_count'] += 1;
                }else if ($item->status_id == 19){
                    $packageInfo['failed_with_fee_count'] += 1;
                }else if ($item->status_id == 11){
                    $packageInfo['returned_count'] += 1;
                }
                $totalCount += 1;
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
                unset($item->driver,$item->status,$item->groupDate,$item->failed_datetime,$item->delivered_datetime,$item->returned_datetime);
            });
            return [
                'date' => $date,
                'details' => $group->toArray(),
            ];
        })->values();

        $data = [
            'total'=> (string)Helper::getNumber($grandTotal),
            'total_count'=> (string) $totalCount,
            'package_info' => $packageInfo,
            'list' => $groupedPackages
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
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;
        $merchantInfo = GeneralSettingService::getMerchantById($userId);
        $qP = Package::where('is_deleted',0)
        ->whereIn('status_id',[9,10,19,11])
        ->where('merchant_id',$userId)
        ->with(['driver:id,user_name,phone','returnUser:id,user_name,phone'])
        ->selectRaw('id,payer,cod,driver_id,qr_code,extra_charge,delivery_fee,extra_charge,price,status_id,failed_datetime,delivered_datetime,returned_datetime,receiver_phone,receiver_address,remarks');
        $qP->orderByRaw('
            CASE
                WHEN status_id = ? THEN 1
                WHEN status_id = ? THEN 2
                WHEN status_id = ? THEN 3
                WHEN status_id = ? THEN 4
                ELSE 7
            END', [9, 19, 11,10]
        )->orderByRaw('DATE(failed_datetime) DESC,DATE(delivered_datetime) DESC');
        if(is_numeric($statusId)) $qP->where('status_id',$statusId);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q) use ($startDate, $endDate) {
                    // For status_id 10 or 19, query only failed_datetime
                    $q->whereBetween('failed_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->whereIn('status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"])
                    ->where('status_id', 9);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    // For status_id 11, query only returned_datetime
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
            // Return the modified object
            return $item;
        })->groupBy('groupDate')
        ->map(function ($group, $date) use ($isKm){
            $group->each(function ($item) use ($isKm,&$totalDeliveryFee) {
                $item->driver_name = $item->driver?->user_name;
                $item->driver_phone = $item->driver?->phone;
                if(!$item->driver) {
                    $item->driver_name = $item->returnUser?->user_name;
                    $item->driver_phone = $item->returnUser?->phone;
                }
                $item->finished_date = $item->failed_datetime ? Helper::dateDMY($item->failed_datetime): Helper::dateDMY($item->delivered_datetime);
                $finished_time = $item->failed_datetime ? Helper::formatCustomDateTime($item->failed_datetime,'h:i:s A'):Helper::formatCustomDateTime($item->delivered_datetime,'h:i:s A');
                $item->finished_time = $finished_time;
                $isCal = in_array($item->status_id,[9,19]);
                $item->price = $item->cod ? (float)$item->price:0;
                $item->extra_charge = (float)$item->extra_charge;
                $total = ($item->cod && $item->status_id == 9) ? $item->price : 0;
                if($item->payer == 'sender') {
                    $item->delivery_fee = $isCal ? (float)Helper::getNumber(($item->delivery_fee + $item->extra_charge)) : 0;
                    $total -= $item->delivery_fee + $item->extra_charge + $item->taxi_fee;
                }else $item->delivery_fee = 0;
                $totalDeliveryFee += $item->delivery_fee;
                $item->total = $isCal ? (float)Helper::getNumber($total) : 0;
                // $total += $item->total;
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
                unset($item->driver,$item->status,$item->groupDate,$item->failed_datetime,$item->delivered_datetime,$item->returned_datetime);
            });
            return [
                'date' => $date,
                'list' => $group->toArray(),
                'total' => [
                    'grand' => $group->sum('total')
                ],
            ];
        })->values();

        // Example data for the PDF
        if(!isset($groupedPackages[0])) return ApiResponse::NotFound('No data available!');
        $data = [
            'title' => 'Report',
            'merchant' => $merchantInfo,
            'date' => date('d-M-Y',strtotime($startDate)) .' to '. date('d-M-Y',strtotime($endDate)),
            'data' => $groupedPackages
        ];
        $pdf = new Mpdf([
            'default_font' => 'khmeros', // Ensure the font is correctly installed and loaded
            'mode' => 'utf-8',           // Required for Unicode support
            'format' => 'A4',            // Paper size
        ]);

        // Render the Blade template with data
        $html = view('pdf.merchant_report', $data);

        // Write the content to the PDF
        $pdf->WriteHTML($html);

        // Define the file name and path
        $fileName = 'history-packages-' . time() . '.pdf';
        //** stream */
        $pdf->Output($fileName, 'i');

        // $filePath = 'pdfs/' . $fileName;

        // Save the PDF content to a file on the public disk
        // Storage::disk('public')->put($filePath, $pdf->Output($fileName, \Mpdf\Output\Destination::STRING_RETURN));
        // Generate the URL to the PDF
        // $fileUrl = asset('storage/'.$filePath);

        // Return the URL in JSON format
        return ApiResponse::JsonResult(null);
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
