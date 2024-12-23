<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteFileJob;
use App\Models\Delivery;
use App\Services\GeneralSettingService;
use App\Services\Mobile\ReusableService;
use App\Services\UserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Helper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $qFp = Delivery::fromRaw('deliveries as d')->join('delivery_packages as dp','d.id','dp.delivery_id')
        ->join('packages as p','p.id','dp.package_id')->orderByDesc('d.id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('trs.id as status_id,trs.name as status_code,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_address,p.receiver_phone,p.driver_total as total')
        ->whereIn('p.status_id',[9,10,11,19])
        ->where('d.driver_id',$userId);
        if($paymentStatus == 2){
            $qFp->where('pmt.approved',1);
        }

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qFp->whereDate('d.depart_datetime', '>=', $startDate)
            ->whereDate('d.depart_datetime', '<=', $endDate);
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
        $data = [
            'title' => 'History Packages',
            'driver' => $driverInfo,
            'date' => date('d-M-Y',strtotime($startDate)) .' to '. date('d-M-Y',strtotime($endDate)),
            'data' => $groupedPackages
        ];
        // return $data;

        // return $data;
        // return ApiResponse::JsonRaw($data);

        // Load the Blade view and pass data to it
        $pdf = Pdf::loadView('pdf.package_history', $data);

        // Save the PDF to a storage disk (public or a custom disk)
        $fileName = 'history-packages-' . time() . '.pdf';
        $filePath = 'pdfs/' . $fileName;


        Storage::disk('public')->put($filePath, $pdf->output());
        // Generate the URL to the PDF
        $fileUrl = asset('storage/'.$filePath);

        // Return the URL in JSON format
        return ApiResponse::JsonResult($fileUrl);
    }
}
