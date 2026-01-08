<?php

namespace App\Jobs;

use App\Exports\Data\DailyPackageFormatter;
use App\Exports\Data\DailyPackageQueryService;
use App\Exports\Reports\DailyPackageList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExportDailyPackagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $exportId,
        public array $filters,
        public string $lang,
        public int $userId
    ) {}

    // public function handle()
    // {
    //     try {
    //         // ini_set('memory_limit', '4096M');

    //         // Step 1: Job started
    //         Cache::store('redis')->put("export:progress:$this->exportId", 10);

    //         // Step 2: Prepare export object
    //         $export = new DailyPackageList(
    //             new DailyPackageQueryService(),
    //             new DailyPackageFormatter($this->lang),
    //             $this->filters
    //         );
    //         Cache::store('redis')->put("export:progress:$this->exportId", 40);

    //         // Step 3: Ensure folder exists
    //         $exportFolder = storage_path('app/exports');
    //         if (!is_dir($exportFolder)) mkdir($exportFolder, 0775, true);
    //         Cache::store('redis')->put("export:progress:$this->exportId", 50);

    //         // Step 4: Generate Excel in memory (this is the heavy step!)
    //         $excelData = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
    //         Cache::store('redis')->put("export:progress:$this->exportId", 80);

    //         // Step 5: Save Excel to disk
    //         $fileName = "daily_packages_{$this->exportId}.xlsx";
    //         $fullPath = $exportFolder . '/' . $fileName;
    //         file_put_contents($fullPath, $excelData);
    //         Cache::store('redis')->put("export:progress:$this->exportId", 90);

    //         // Step 6: Mark export as ready
    //         Cache::store('redis')->put("export:progress:$this->exportId", 100);
    //         Cache::store('redis')->put("export:ready:$this->exportId", true);

    //         Log::info("Export completed: $fullPath");

    //     } catch (\Throwable $e) {
    //         // Mark as failed
    //         Cache::store('redis')->put("export:progress:$this->exportId", -1);
    //         Cache::store('redis')->put("export:error:$this->exportId", $e->getMessage());
    //         Cache::store('redis')->put("export:ready:$this->exportId", false);

    //         Log::error("Export job failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

    //         $this->failed($e);
    //     }
    // }

    public function handle()
    {
        try {
            // Step 1: Job started
            Cache::store('redis')->put("export:progress:$this->exportId", 10);

            $exportFolder = storage_path('app/exports');
            if (!is_dir($exportFolder)) mkdir($exportFolder, 0775, true);

            $fileName = "daily_packages_{$this->exportId}.xlsx";
            $fullPath = $exportFolder . '/' . $fileName;

            // Step 2: Prepare export object
            $export = new DailyPackageList(
                new DailyPackageQueryService(),
                new DailyPackageFormatter($this->lang),
                $this->filters,
                $this->exportId
            );

            // Cache::store('redis')->put("export:progress:$this->exportId", 30);

            // Step 3: Generate Excel synchronously in memory
            $excelData = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

            // Cache::store('redis')->put("export:progress:$this->exportId", 80);

            // Step 4: Write Excel to disk
            file_put_contents($fullPath, $excelData);

            // Cache::store('redis')->put("export:progress:$this->exportId", 100);
            Cache::store('redis')->put("export:ready:$this->exportId", true);

            Log::info("Export completed: $fullPath");

        } catch (\Throwable $e) {
            Cache::store('redis')->put("export:progress:$this->exportId", -1);
            Cache::store('redis')->put("export:error:$this->exportId", $e->getMessage());
            Cache::store('redis')->put("export:ready:$this->exportId", false);

            Log::error("Export job failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $this->failed($e);
        }
    }

    public function failed(\Throwable $e)
    {
        Cache::put("export:progress:$this->exportId", -1);
        Cache::put("export:error:$this->exportId", $e->getMessage());
    }
}
