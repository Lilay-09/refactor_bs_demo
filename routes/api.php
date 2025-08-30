<?php

use App\Http\Controllers\V1\AppSettingController;
use Illuminate\Support\Facades\Route;
Route::get('redirect-store',[AppSettingController::class,'redirectBarcodeScan']);
Route::get('redirect-store/driverApp',[AppSettingController::class,'redirectStoreDriverMobile']);
require __DIR__ . '/mobile/v1.php';
require __DIR__ . '/admin/v1.php';
require __DIR__ . '/admin/v2.php';

