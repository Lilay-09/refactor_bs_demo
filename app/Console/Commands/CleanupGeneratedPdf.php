<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CleanupGeneratedPdf extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:pdf-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete PDF files older than a specific time from the storage/pdfs directory';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // Define the path to the 'pdfs' folder in storage
        $directory = 'pdfs'; // 'pdfs' directory on public disk

        // Get all files in the 'pdfs' directory
        $files = Storage::disk('public')->files($directory);

        // Loop through each file and delete it
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
            // $this->info("Deleted file: $file");
        }

        $this->info('All PDF files have been deleted.');
    }
}
