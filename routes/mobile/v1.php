<?php
use App\Http\Controllers\Mobile\Driver\V1\AuthController;
use App\Http\Controllers\Mobile\Driver\V1\HomeScreenController;
use Illuminate\Support\Facades\Route;



Route::prefix('driver/v1/auth')->group(function(){
    Route::post('login',[AuthController::class,'login']);
    Route::middleware('jwtDriver')->group(function(){
        Route::get('profile',[AuthController::class,'getProfile']);
    });
});
Route::middleware(['jwtDriver','localize'])->prefix('driver/v1/{lang}')->group(function(){
    Route::prefix('home')->group(function(){
        Route::get('availableOrders',[HomeScreenController::class,'getAvailableOrders']);
        Route::get('accepted/pickup',[HomeScreenController::class,'getAcceptedPickup']);
        Route::get('accepted/delivery',[HomeScreenController::class,'getDeliveryItem']);
        Route::put('accepted/pickup/{order_id}',[HomeScreenController::class,'updateAcceptedOrder']);
        Route::post('acceptOrder/{order_id}',[HomeScreenController::class,'acceptOrder']);
        Route::get('option/status',[HomeScreenController::class,'getOptionsStatus']);
    });
});
















// BEGIN => Merchant
Route::prefix('merchant/v1/auth')->group(function(){
    // Route::post('login',[AuthController::class,'login']);
});
Route::middleware(['jwt','localize'])->prefix('admin/v1/{lang}')->group(function(){

});
