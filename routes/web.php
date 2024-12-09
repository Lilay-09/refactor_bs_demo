<?php

use App\Http\Controllers\V1\AppSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppSettingController::class,'redirectCompanyWebsite']);

Route::get('/pdf', function(){
    return view('pdf.package_history');
});
