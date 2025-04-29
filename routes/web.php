<?php

use App\Http\Controllers\V1\AppSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppSettingController::class,'redirectCompanyWebsite']);
Route::middleware(['web'])->group(function () {
    Route::get('/clear-logs', function () {
        $logFile = storage_path('logs/laravel.log');

        if (file_exists($logFile)) {
            file_put_contents($logFile, ''); // Clear the log
            return response()->json(['message' => 'Laravel log file cleared.']);
        }

        return response()->json(['message' => 'Log file not found.'], 404);
    });

    Route::get('/clear-configCache', function () {
        Artisan::call('config:clear');

        return response()->json(['message' => 'Config cache cleared.']);
    });

    Route::get('/pdf', function(){
        return view('pdf.package_history');
    });


    Route::get('/privacy', function(){
        return view('privacy');
    });

    // Route::get('/privacy/personal/collect', function(){
    //     return view('personal_collect');
    // });

    Route::get('/privacy/personal/collect', function(){
        return view('personal_collect');
    });

    Route::get('/privacy/personal/purpose', function(){
        return view('disclose');
    });

    Route::get('/privacy/personal/use', function(){
        return view('use_personal_data');
    });

    Route::get('/privacy/personal/share', function(){
        return view('personal_share');
    });

});

