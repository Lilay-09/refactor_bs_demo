<?php

use App\Http\Controllers\V1\AppSettingController;
use App\Http\Controllers\V1\PackageTrailController;
use App\Http\Controllers\V1\PaywayController;
use Illuminate\Support\Facades\Route;
Route::get('redirect-store',[AppSettingController::class,'redirectBarcodeScan']);
Route::get('redirect-store/driverApp',[AppSettingController::class,'redirectStoreDriverMobile']);


Route::middleware(['jwtAuthGen','localize'])->prefix('{role}/v1/{lang}')
->whereIn('role', ['admin', 'driver', 'merchant'])
->group(function(){
    Route::post('payway/khqr/gen-payload',[PaywayController::class,'getABAKHQRPayload']);
    Route::post('payway/khqr/settle/gen-payload',[PaywayController::class,'gernerateSettleABAQrPayload']);
    Route::get('transaction-logs',[PaywayController::class,'getPaylogs']);
});

Route::middleware(['localize'])->prefix('{role}/v1/{lang}')
->whereIn('role', ['admin', 'driver', 'merchant'])
->group(function(){
    Route::post('payway/receiver-callback',[PaywayController::class,'receiverCallbackPayment']);
    Route::post('payway/driver-callback',[PaywayController::class,'driverCallbackPayment']);
    // Route::get('payway/redirect',[PaywayController::class,'streamRedirect']);
    // Route::post('transaction-check',[PaywayController::class,'checkTransaction']);
    Route::post('payway/sent',action: [PaywayController::class,'deeplinkAfterKHQRScan']);
    Route::post('payway/check-transaction',[PaywayController::class,'generateCheckTransaction']);

    Route::prefix('package')->group(function(){
        Route::get('/information-{key}',[PackageTrailController::class,'getPackageInformation']);
    });
});



require __DIR__ . '/mobile/v1.php';
require __DIR__ . '/admin/v1.php';
require __DIR__ . '/admin/v2.php';

