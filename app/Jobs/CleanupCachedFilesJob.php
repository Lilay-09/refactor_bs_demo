<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CleanupCachedFilesJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $directory = 'pdfs'; // Specify the directory where files are stored
        $files = Storage::disk('public')->files($directory);

        foreach ($files as $file) {
            if (Storage::disk('public')->exists($file)) {
                Storage::disk('public')->delete($file); // Delete the file
                // Log or notify if needed
            }
        }
    }

    // Delay job processing for 10 seconds before it runs again
    public function delay($delay)
    {
        return now()->addSeconds(10); // This will ensure the job is delayed for 10 seconds
    }
}
