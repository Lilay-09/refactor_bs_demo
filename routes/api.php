<?php

use App\Http\Controllers\V1\AppSettingController;
use Illuminate\Support\Facades\Route;
Route::get('redirect-store',[AppSettingController::class,'redirectBarcodeScan']);
require __DIR__ . '/mobile/v1.php';
require __DIR__ . '/admin/v1.php';

