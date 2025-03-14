<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\GeneralSettingService;
use App\Services\Mobile\ReusableService;
use App\Services\UserService;
use DB;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Log;
use Mpdf\Mpdf;

class HistoryController extends Controller
{
    //
    public function getHistoryPackages(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::flex(ReusableService::getHistoryPackages($req,$user));
    }

    // public function getHistoryPdf(Request $req){
    //     return AppSetting::generatePDF($req);
    // }

    public function getHistoryPdf(Request $req)
    {
        $user = UserService::getAuthUser('driver');
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $userId = $user->id;
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;
        $statuses = [9, 10, 19];
        if($statusId) $statuses = [$statusId];
        $latestPackages = DB::table('delivery_packages as dp1')
        ->selectRaw('DISTINCT ON (dp1.package_id) dp1.*')
        ->orderBy('dp1.package_id')
        ->orderByDesc('dp1.id');

        $qFp = Delivery::fromRaw('deliveries as d')
        ->joinSub($latestPackages, 'dp', 'd.id', 'dp.delivery_id')
        ->join('packages as p', 'p.id', 'dp.package_id')
        ->join('users as m','m.id','p.merchant_id')
        // ->where('dp.delay_count',0)->where('dp.is_deleted',0)
        // ->where('dp.has_swap',0)
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('p.returned_uid,p.driver_id,p.qr_code,p.status_id,trs.name as status_code,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.returned_datetime,p.failed_datetime,p.receiver_name,p.delivered_datetime,p.receiver_address,p.receiver_phone,p.driver_total as total')
        ->where(function ($q) use ($userId,$statuses) {
            $q->whereIn('p.status_id', $statuses)
            ->orWhere(function ($subQuery) use ($userId) {
                $subQuery->where('p.status_id', 11)
                ->where('p.returned_uid', $userId);
            });
        })
        // ->whereIn('p.status_id',[9,10,19])
        // ->orWhere(function ($q) use ($userId) {
        //     $q->where('p.status_id', 11)->where('p.returned_uid', $userId);
        // })
        ->where([
            ['dp.delay_count', 0],
            ['dp.is_deleted', 0],
            ['dp.has_swap', 0],
            ['p.driver_id', $userId],
            ['d.driver_id', $userId],
            ['dp.driver_id', $userId]
        ])
        // ->where('p.driver_id',$userId)
        // ->where('d.driver_id',$userId)
        // ->where('dp.driver_id',$userId)
        ->orderByRaw("CASE WHEN p.status_id = 11 THEN d.id END DESC, d.id DESC");

        if($paymentStatus == 2){
            $qFp->where('pmt.approved',1);
        }

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $startDateTime = $startDate . ' 00:00:00';
            $endDateTime = $endDate . ' 23:59:59';
            $qFp->whereBetween('d.depart_datetime',[$startDateTime,$endDateTime])
            ->orWhere(function($q) use ($startDateTime, $endDateTime,$userId) {
                $q->where(function ($q) use ($startDateTime, $endDateTime,$userId) {
                    $q->whereBetween('p.failed_datetime', [$startDateTime, $endDateTime])
                        ->where('p.driver_id',$userId)
                        ->whereIn('p.status_id', [10, 19]);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime,$userId) {
                    $q->whereBetween('p.delivered_datetime', [$startDateTime, $endDateTime])
                        ->where('p.driver_id',$userId)
                        ->where('p.status_id', 9);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime,$userId) {
                    $q->whereBetween('p.returned_datetime', [$startDateTime, $endDateTime])
                        ->where('p.returned_uid',$userId)
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
        $totalDeliveredCount = 0;
        $failedWithFeeCount = 0;
        $grandTotal = 0;
        $driverInfo = GeneralSettingService::getDriverById($userId);
        $groupedPackages = collect($fleetPackages)->map(function ($pkg) use(&$totalDeliveredCount,&$failedWithFeeCount) {
            $pkg->groupKey = $pkg->fleet_tracking_number;
            if($pkg->status_id == 9) $totalDeliveredCount +=1;
            if($pkg->status_id == 19) $failedWithFeeCount +=1;
            return $pkg;
        })

        ->groupBy('groupKey')
        ->map(function ($group, $fleetNumber) use(&$grandTotal) {
            $group->each(function ($item) {
                // if($item->status_id == 9 || $item->status_id == 19){
                //     $grandTotal = $item->total;
                // }
                unset($item->delivery_id,$item->fleet_tracking_number,$item->groupKey);
            });
            $rowGrand = $group->whereIn('status_id',[9,19])->sum('total');
            $grandTotal += $rowGrand;
            return [
                'fleet_number' => $fleetNumber,
                'details' => $group,
                'total' => [
                    'grand' => $rowGrand//$group->whereIn('status_id',[9,19])->sum('total')
                ],
            ];
        })->values();

        // Log::error(count($groupedPackages));

        // return $groupedPackages;
        // Example data for the PDF
        if(!isset($groupedPackages[0])) return ApiResponse::NotFound('No data available!');
        $data = [
            'title' => 'History Packages',
            'driver' => $driverInfo,
            'deliveredCount' => $totalDeliveredCount,
            'failedWithFeeCount' => $failedWithFeeCount,
            'grandTotal' => Helper::getNumber($grandTotal),
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
        // $pdf->Output($fileName, 'i');

        $filePath = 'pdfs/' . $fileName;

        // Save the PDF content to a file on the public disk
        Storage::disk('public')->put($filePath, $pdf->Output($fileName, \Mpdf\Output\Destination::STRING_RETURN));
        // Generate the URL to the PDF
        $fileUrl = asset('storage/'.$filePath);

        // Return the URL in JSON format
        return ApiResponse::JsonResult($fileUrl);
    }
}
