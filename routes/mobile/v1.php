<?php
use App\Http\Controllers\Mobile\Driver\V1\AuthController;
use App\Http\Controllers\Mobile\Driver\V1\HistoryController;
use App\Http\Controllers\Mobile\Driver\V1\SearchController;
use App\Http\Controllers\Mobile\Driver\V1\TransactionController;
use App\Http\Controllers\Mobile\Merchant\V1\TransactionController as MerchantTransactionController;
use App\Http\Controllers\Mobile\Merchant\V1\AuthController as AuthMerchantController;
use App\Http\Controllers\Mobile\Merchant\V1\HistoryController as MerchantHistoryController;
use App\Http\Controllers\Mobile\Driver\V1\HomeScreenController;
use App\Http\Controllers\Mobile\Merchant\V1\HomeController;
use App\Http\Controllers\Mobile\V1\GeneralSettingController;
use App\Models\TrackingStatus;
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
    Route::post('notification/subscribe',[AuthController::class,'subscribeTopics']);
    Route::post('notification/unsubscribe',[AuthController::class,'unsubscribeTopics']);
    Route::get('termConditions',[HomeScreenController::class,'getTermConditions']);
    Route::get('notification',[HomeScreenController::class,'getNotifications']);
    Route::put('notification/read/{id?}',[HomeScreenController::class,'readNotification']);
    Route::prefix('transaction')->group(function(){
        Route::get('',[TransactionController::class,'getTransactionSummary']);
        Route::get('commission/report',[TransactionController::class,'getCommissionReport']);
        Route::get('commission/trx',[TransactionController::class,'getCommissionTrx']);
    });

    Route::prefix('home')->group(function(){
        Route::get('balance',[HomeScreenController::class,'getDriverBalance']);
        Route::post('booking',[HomeScreenController::class,'booking']);
        Route::get('availableOrders',[HomeScreenController::class,'getAvailableOrders']);
        Route::get('accepted/pickup',[HomeScreenController::class,'getAcceptedPickup']);
        Route::get('accepted/pickup/{order_id}',[HomeScreenController::class,'getOneAcceptedPickup']);
        Route::get('accepted/delivery',[HomeScreenController::class,'getDelivery']);
        Route::get('accepted/delivery/{order_id}/package',[HomeScreenController::class,'getDeliveryItems']);
        Route::put('accepted/delivery/{order_id}/package/{package_ref}/contact',[HomeScreenController::class,'markPackageContact']);
        Route::post('accepted/pickup/{order_id}',[HomeScreenController::class,'updateAcceptedOrder']);
        Route::post('acceptOrder/{order_id}',[HomeScreenController::class,'acceptOrder']);
        Route::get('option/status',[HomeScreenController::class,'getOptionsStatus']);
        Route::post('acceptedOrder/{order_id}/cancel',[HomeScreenController::class,'cancelOrder']);
        Route::post('acceptedOrder/{order_id}/drop',[HomeScreenController::class,'dropOrderAtWarehouse']);
        Route::post('accepted/delivery/package/{package_id}',[HomeScreenController::class,'submitDeliveryPackage']);
    });


    Route::get('history',[HistoryController::class,'getHistoryPackages']);
    Route::get('history/pdf',[HistoryController::class,'getHistoryPdf']);

    Route::get('search/fleet/package',[SearchController::class,'getTripPackages']);
    Route::get('scan/package/{item_ref}',[GeneralSettingController::class,'scanPackage']);
    Route::post('scan/package/{item_ref}',[GeneralSettingController::class,'scanPackageChooseAction']);
    Route::post('scan/package/{item_ref}/swap',[GeneralSettingController::class,'confirmOrCancelSwapPackage']);

    Route::prefix('setting')->group(function (){
        Route::prefix('option')->group(function (){
            Route::get('failRemark',[GeneralSettingController::class,'getOptionsDriverFailRemarks']);
            Route::get('zone/{zone_id}/price',[GeneralSettingController::class,'getZonePrice']);
            Route::get('zone',[GeneralSettingController::class,'getOptionsZone']);
        });

        Route::prefix('form')->group(function (){
            Route::get('history',[GeneralSettingController::class,'getFormOptionsHistory']);
            Route::get('booking',[GeneralSettingController::class,'getFormBooking']);
        });
    });
});

//END::Driver



//BEGIN::Merchant

Route::prefix('merchant/v1/{lang}/auth')->middleware('localize')->group(function(){
    Route::post('login',[AuthMerchantController::class,'login']);
    Route::post('registration',[AuthMerchantController::class,'merchantRegistration']);
    Route::post('verifyOtp',[AuthMerchantController::class,'verifyOtp']);
    Route::post('registration/password',[AuthMerchantController::class,'registrationPassword']);
    Route::middleware('jwtMerchant')->group(function(){
        Route::post('resetPassword',[AuthMerchantController::class,'resetPassword']);
        Route::get('profile',[AuthMerchantController::class,'getProfile']);
        Route::post('profile',[AuthMerchantController::class,'updateProfile']);
        Route::post('registration/forgetPassword',[AuthMerchantController::class,'forgetPassword']);
    });
});

Route::middleware(['jwtMerchant','localize'])->prefix('merchant/v1/{lang}')->group(function(){
    Route::post('notification/subscribe',[AuthMerchantController::class,'subscribeTopics']);
    Route::get('termConditions',[HomeController::class,'getTermConditions']);
    Route::get('connectWithUs',[HomeController::class,'getConnectWithUs']);
    Route::post('feedback',[HomeController::class,'feedBack']);
    Route::get('bankAccount',[HomeController::class,'getBankAccount']);
    Route::post('bankAccount',[HomeController::class,'saveBankAccount']);
    Route::delete('bankAccount/{id}',[HomeController::class,'deleteBankAccount']);
    Route::get('notification',[HomeController::class,'getNotifications']);
    Route::put('notification/read/{id?}',[HomeController::class,'readNotification']);
    Route::get('history/packages',[MerchantHistoryController::class,'getAllHistories']);
    Route::get('transaction',[MerchantTransactionController::class,'getTransaction']);
    Route::prefix('home')->group(function(){
        Route::get('',[HomeController::class,'getHomeScreen']);
        Route::post('booking',[HomeController::class,'createBooking']);
        Route::get('promotion',[HomeController::class,'getPromotions']);
        Route::get('find/package/{phone?}',[HomeController::class,'findPackage']);
        Route::prefix('tracking')->group(function(){
            Route::get('pending',[HomeController::class,'getPendingOrders']);
            Route::get('pick',[HomeController::class,'getPickOrders']);
            Route::get('delivery',[HomeController::class,'getOnDeliveryPackages']);
            Route::get('success',[HomeController::class,'getSuccessPackages']);
            Route::get('fail',[HomeController::class,'getFailPackages']);
            Route::get('return',[HomeController::class,'getReturnPackages']);
            Route::get('activity',[HomeController::class,'trackingActivitySummary']);
        });
    });

    Route::prefix('setting')->group(function (){
        Route::prefix('option')->group(function (){
            Route::get('zone/{zone_id}/price',[HomeController::class,'getZonePrice']);
            Route::get('zone',[HomeController::class,'getOptionsZone']);
        });
        Route::prefix('form')->group(function (){
            Route::get('booking',[GeneralSettingController::class,'getMerchantFormBooking']);
        });
    });
});


//END::Merchant



// Route::middleware(['jwtMerchant','localize'])->prefix('merchant/v1/{lang}')->group(function(){

// });







