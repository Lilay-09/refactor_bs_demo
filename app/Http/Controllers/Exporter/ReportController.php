<?php

namespace App\Http\Controllers\Exporter;

use ApiResponse;
use App\Exceptions\BadRequestExcept;
use App\Exports\Data\DailyPackageFormatter;
use App\Exports\Data\DailyPackageQueryService;
use App\Exports\Reports\DailyPackageList as ReportsDailyPackageList;
use App\Http\Controllers\Controller;
use App\Jobs\ExportDailyPackagesJob;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel as ExcelService;
use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{

    public function exportDailyPackages(Request $req, ExcelService $excel)
    {
        // ini_set('memory_limit', '2048M');
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
            if ($e instanceof BadRequestExcept) { // or your custom BadRequest class
                return ApiResponse::Error($e->getMessage());
            }

            // For all other exceptions, log full stack trace
            // Log::error('Export Daily Packages failed: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());

            return ApiResponse::Error("Failed to export daily packages. Check logs for details.");
        }
    }
    // public function exportDailyPackages(Request $req, ExcelService $excel)
    // {
    //     $filters = $req->only([
    //         'search','startDate','endDate','arrive_start_date','arrive_end_date',
    //         'status','driver_id','merchant_id','branch_id','warehouse_id',
    //         'pickup_driver_id','price_list_id','zone_code'
    //     ]);

    //     $export = new ReportsDailyPackageList(
    //         new DailyPackageQueryService(),
    //         new DailyPackageFormatter($req->lang ?? 'en'),
    //         $filters
    //     );

    //     try {
    //         // 1️⃣ Ensure tmp folder exists (absolute path)
    //         $tmpFolder = storage_path('app/tmp');
    //         if (!is_dir($tmpFolder) && !mkdir($tmpFolder, 0775, true) && !is_dir($tmpFolder)) {
    //             throw new \RuntimeException("Failed to create tmp folder at: $tmpFolder");
    //         }

    //         // 2️⃣ Generate unique file name
    //         $fileName = 'daily_packages_' . now()->format('Ymd_His') . '.xlsx';
    //         $fullPath = $tmpFolder . '/' . $fileName;

    //         // 3️⃣ Generate Excel content in memory, then write to disk
    //         $excelContent = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
    //         if (file_put_contents($fullPath, $excelContent) === false) {
    //             throw new \RuntimeException("Failed to write Excel file at: $fullPath");
    //         }

    //         if (!file_exists($fullPath)) {
    //             throw new \RuntimeException("Excel file not found after writing: $fullPath");
    //         }

    //         // 4️⃣ Stream the file to client
    //         return response()->streamDownload(function () use ($fullPath) {
    //             $stream = fopen($fullPath, 'rb');
    //             fpassthru($stream);
    //             fclose($stream);
    //             // Optional: delete after sending
    //             // unlink($fullPath);
    //         }, $fileName, [
    //             'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //         ]);

    //     } catch (BadRequestExcept $e) {
    //         return ApiResponse::Error($e->getMessage());
    //     } catch (\Throwable $e) {
    //         Log::error('Export Daily Packages failed', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return ApiResponse::Error('Failed to export daily packages. Please try again later.');
    //     }
    // }



    public function startDailyPackagesExport(Request $req)
    {
        $filters = $req->only([
            'search','startDate','endDate','arrive_start_date','arrive_end_date',
            'status','driver_id','merchant_id','branch_id','warehouse_id',
            'pickup_driver_id','price_list_id','zone_code'
        ]);

        $exportId = (string) Str::uuid();

        Cache::put("export:progress:$exportId", 0);
        Cache::put("export:ready:$exportId", false);
        $queueName = config('queue_job_names.'.config('app.env').'.exports');
        ExportDailyPackagesJob::dispatch(
            exportId: $exportId,
            filters: $filters,
            lang: $req->lang ?? 'en',
            userId: $req->user()->id
        )->onQueue($queueName);
        Log::info("After Queue Start ID: $exportId");
        return ApiResponse::JsonResult([
            'export_id' => $exportId
        ]);
    }

    public function exportProgress(Request $req)
    {
        $exportId = $req->id;

        return ApiResponse::JsonResult([
            'progress' => Cache::store('redis')->get("export:progress:$exportId", 0),
            'ready'    => Cache::store('redis')->get("export:ready:$exportId", false),
        ]);
    }


    public function downloadExport(Request $req)
    {
        $exportId = $req->id;
        $path = storage_path("app/exports/daily_packages_$exportId.xlsx");
        if (!file_exists($path)) {
            return ApiResponse::Error('File not ready');
        }

        return response()
            ->download($path)
            ->deleteFileAfterSend(true);
    }

}
