<?php

namespace App\Http\Controllers\Exporter;

use App\Exports\Data\DailyPackageFormatter;
use App\Exports\Data\DailyPackageQueryService;
use App\Exports\Reports\DailyPackageList as ReportsDailyPackageList;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel as ExcelService;
use Throwable;

class ReportController extends Controller
{

    public function exportDailyPackages(Request $req, ExcelService $excel)
    {
        ini_set('memory_limit', '1024M');
        $filters = $req->only([
            'search','startDate','endDate','arrive_start_date','arrive_end_date',
            'status','driver_id','merchant_id','branch_id','warehouse_id','pickup_driver_id',
            'price_list_id','zone_code'
        ]);

        $export = new ReportsDailyPackageList(
            new DailyPackageQueryService(),
            new DailyPackageFormatter($req->lang ?? 'en'),
            $filters
        );

        try {
            // Return download response directly
            $response = $excel->download($export, 'daily_packages.xlsx');
            $response->headers->set('X-Custom-Header', 'value');
            return $response;
        } catch (Throwable $e) {
            // Log full exception including stack trace
            Log::error('Export Daily Packages failed: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return response()->json([
                'status' => false,
                'message' => 'Failed to export daily packages. Check logs for details.'
            ], 500);
        }
    }

}
