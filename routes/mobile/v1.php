<?php
use App\Http\Controllers\Mobile\Driver\V1\AuthController;
use App\Http\Controllers\Mobile\Merchant\V1\AuthController as AuthMerchantController;
use App\Http\Controllers\Mobile\Driver\V1\HomeScreenController;
use Illuminate\Support\Facades\Route;



//BEGIN::Driver

Route::prefix('driver/v1/{lang}/auth')->middleware('localize')->group(function(){
    Route::post('login',[AuthController::class,'login']);
    Route::middleware('jwtDriver')->group(function(){
        Route::get('profile',[AuthController::class,'getProfile']);
    });
});
Route::middleware(['jwtDriver','localize'])->prefix('driver/v1/{lang}')->group(function(){
    Route::prefix('home')->group(function(){
        Route::get('availableOrders',[HomeScreenController::class,'getAvailableOrders']);
        Route::get('accepted/pickup',[HomeScreenController::class,'getAcceptedPickup']);
        Route::get('accepted/delivery',[HomeScreenController::class,'getDelivery']);
        Route::get('accepted/delivery/{order_id}/package',[HomeScreenController::class,'getDeliveryItem']);
        Route::put('accepted/pickup/{order_id}',[HomeScreenController::class,'updateAcceptedOrder']);
        Route::post('acceptOrder/{order_id}',[HomeScreenController::class,'acceptOrder']);
        Route::get('option/status',[HomeScreenController::class,'getOptionsStatus']);
    });
});

//END::Driver



//BEGIN::Merchant

Route::prefix('merchant/v1/{lang}/auth')->middleware('localize')->group(function(){
    Route::post('login',[AuthMerchantController::class,'login']);
    Route::post('registration',[AuthMerchantController::class,'merchantRegistration']);
    Route::post('verifyOtp',[AuthMerchantController::class,'verifyOtp']);
    Route::middleware('jwtMerchant')->group(function(){
        Route::get('profile',[AuthMerchantController::class,'getProfile']);
    });
});

Route::middleware(['jwtMerchant','localize'])->prefix('merchant/v1/{lang}')->group(function(){

});


//END::Merchant













// BEGIN => Merchant
Route::prefix('merchant/v1/auth')->group(function(){
    // Route::post('login',[AuthController::class,'login']);
});
Route::middleware(['jwt','localize'])->prefix('admin/v1/{lang}')->group(function(){

});
