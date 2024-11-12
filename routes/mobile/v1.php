<?php
use App\Http\Controllers\Mobile\Driver\V1\AuthController;
use App\Http\Controllers\Mobile\Driver\V1\HistoryController;
use App\Http\Controllers\Mobile\Merchant\V1\AuthController as AuthMerchantController;
use App\Http\Controllers\Mobile\Driver\V1\HomeScreenController;
use App\Http\Controllers\Mobile\Merchant\V1\HomeController;
use App\Http\Controllers\Mobile\V1\GeneralSettingController;
use Illuminate\Support\Facades\Route;

//BEGIN::Driver

Route::prefix('driver/v1/{lang}/auth')->middleware('localize')->group(function(){
    Route::post('login',[AuthController::class,'login']);
    Route::middleware('jwtDriver')->group(function(){
        Route::get('profile',[AuthController::class,'getProfile']);
        Route::post('profile',[AuthController::class,'updateProfile']);
    });
});
Route::middleware(['jwtDriver','localize'])->prefix('driver/v1/{lang}')->group(function(){
    Route::post('subscribe',[AuthController::class,'subscribeTopics']);
    Route::get('termConditions',[HomeScreenController::class,'getTermConditions']);
    Route::prefix('home')->group(function(){
        Route::get('availableOrders',[HomeScreenController::class,'getAvailableOrders']);
        Route::get('accepted/pickup',[HomeScreenController::class,'getAcceptedPickup']);
        Route::get('accepted/delivery',[HomeScreenController::class,'getDelivery']);
        Route::get('accepted/delivery/{order_id}/package',[HomeScreenController::class,'getDeliveryItem']);
        Route::post('accepted/pickup/{order_id}',[HomeScreenController::class,'updateAcceptedOrder']);
        Route::post('acceptOrder/{order_id}',[HomeScreenController::class,'acceptOrder']);
        Route::get('option/status',[HomeScreenController::class,'getOptionsStatus']);
        Route::post('accepted/delivery/package/{package_id}',[HomeScreenController::class,'submitDeliveryPackage']);
    });

    Route::get('history',[HistoryController::class,'getHistoryPackages']);

    Route::prefix('setting')->group(function (){
        Route::prefix('option')->group(function (){
            Route::get('failRemark',[GeneralSettingController::class,'getOptionsDriverFailRemarks']);
        });

        Route::prefix('form')->group(function (){
            Route::get('history',[GeneralSettingController::class,'getFormOptionsHistory']);
        });
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
    Route::prefix('home')->group(function(){
        Route::post('booking',[HomeController::class,'createBooking']);
    });


    Route::prefix('setting')->group(function (){
        // Route::prefix('option')->group(function (){
        //     Route::get('failRemark',[GeneralSettingController::class,'getOptionsDriverFailRemarks']);
        // });
        Route::prefix('form')->group(function (){
            Route::get('booking',[GeneralSettingController::class,'getMerchantFormBooking']);
        });
    });
});


//END::Merchant



// Route::middleware(['jwtMerchant','localize'])->prefix('merchant/v1/{lang}')->group(function(){

// });







