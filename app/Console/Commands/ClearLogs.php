<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Clear the Laravel log file';

    public function handle()
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->info('Log file cleared.');
        } else {
            $this->warn('Log file does not exist.');
        }

        return Command::SUCCESS;
    }
}
