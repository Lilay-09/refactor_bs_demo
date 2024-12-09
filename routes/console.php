<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


// Route::console(function (Schedule $schedule) {
//     $schedule->call(function () {
        // $startTime = now();
        // $endTime = $startTime->addMinutes(1);  // Run for 1 minute as an example

        // while (now()->lt($endTime)) {
        //     Log::info("Task executed at " . now()); // Your task code goes here

        //     sleep(2);  // Wait for 2 seconds
        // }
//     })->everyMinute();  // Still run every minute, but inside it, it will loop every 2 seconds
// });
