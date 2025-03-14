<?php

use App\Http\Controllers\V1\AppSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppSettingController::class,'redirectCompanyWebsite']);

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
