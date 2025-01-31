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
                $item->extra_charge = (float)$item->extra_charge;
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
                unset($item->status,$item->groupDate,$item->failed_datetime,$item->delivered_datetime,$item->returned_datetime);
            });
            return [
                'date' => $date,
                'details' => $group->toArray(),
            ];
        })->values();

        return ApiResponse::JsonResult($groupedPackages);
    }


    public function merchantDailyPackagesPreview(Request $req)
    {
        $user = UserService::getAuthUser('merchant');
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $userId = $user->id;
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;

        $qFp = Delivery::fromRaw('deliveries as d')->join('delivery_packages as dp','d.id','dp.delivery_id')
        ->join('packages as p','p.id','dp.package_id')->orderByDesc('d.id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('trs.id as status_id,trs.name as status_code,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_address,p.receiver_phone,p.driver_total as total')
        ->whereIn('p.status_id',[9,10,11,19])
        ->where('d.merchant',$userId);

        if($paymentStatus == 2){
            $qFp->where('pmt.approved',1);
        }

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qFp->where(function($q) use ($startDate, $endDate) {
                $q->where(function($q) use ($startDate, $endDate) {
                    // For status_id 10 or 19, query only failed_datetime
                    $q->whereRaw('
                        (p.failed_datetime::DATE >= ? AND p.failed_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('p.status_id',19);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereRaw('
                        (p.delivered_datetime::DATE >= ? AND p.delivered_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('p.status_id', 9);
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
        if($search) $qFp->where('p.receiver_phone', 'ilike', '%' . $search . '%')
        ->orWhere('m.phone', 'ilike', '%' . $search . '%')
        ->orWhere('d.fleet_tracking_number', 'ilike', '%' . $search . '%');
        $fleetPackages = $qFp->get();
        $driverInfo = GeneralSettingService::getDriverById($userId);
        $groupedPackages = collect($fleetPackages)->map(function ($pkg) {
            $pkg->groupKey = $pkg->fleet_tracking_number;
            return $pkg;
        })
        ->groupBy('groupKey')
        ->map(function ($group, $fleetNumber) {
            $group->each(function ($item) use ($group) {
                unset($item->delivery_id,$item->fleet_tracking_number,$item->groupKey);
            });
            return [
                'fleet_number' => $fleetNumber,
                'details' => $group,
                'total' => [
                    'grand' => $group->sum('total')
                ],
            ];
        })->values();

        // Example data for the PDF
        if(!isset($groupedPackages[0])) return ApiResponse::NotFound('No data available!');
        $data = [
            'title' => 'Report',
            'driver' => $driverInfo,
            'date' => date('d-M-Y',strtotime($startDate)) .' to '. date('d-M-Y',strtotime($endDate)),
            'data' => $groupedPackages
        ];
        $pdf = new Mpdf([
            'default_font' => 'khmeros', // Ensure the font is correctly installed and loaded
            'mode' => 'utf-8',           // Required for Unicode support
            'format' => 'A4',            // Paper size
        ]);

        // Render the Blade template with data
        $html = view('pdf.package_history', $data);

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
    public function merchantDailyPackagesOption(){
        $statuses = TrackingStatus::whereIn('id',[9,10,11,19])->get();
    }
}
