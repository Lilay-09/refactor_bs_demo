<?php

namespace App\Jobs;

use App\Exceptions\BadRequestExcept;
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

    public function handle()
    {
        try {
            // Step 1: Job started
            // Cache::store('redis')->put("export:progress:$this->exportId", 10);

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

            // Step 3: Generate Excel synchronously in memory
            $excelData = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

            // Step 4: Write Excel to disk
            file_put_contents($fullPath, $excelData);
            Cache::store('redis')->put("export:ready:$this->exportId", true);

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
