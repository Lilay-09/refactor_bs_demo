<?php

use App\Http\Controllers\V1\ReportController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt','localize','userAccess:admin','rateLimit'])->prefix('admin/v2/{lang}')->group(function(){
    Route::prefix('report')->group(function(){
        // Route::get('/option/warehouse',[ReportController::class,'optionsWarehouse']);
        // Route::prefix('company')->group(function(){
        //     Route::get('/pickup',[ReportController::class,'getPickupReport']);
        //     Route::get('/pickup/option',[ReportController::class,'getPickupReportOption']);
        //     Route::get('/dailyPackage',[ReportController::class,'getDailyPackageReport']);
        //     Route::get('/dailyPackage/option',[ReportController::class,'getDailyPackageReportOption']);
        //     Route::get('/dailyPackageSummary',[ReportController::class,'getDailyPackageSummaryReport']);
        //     Route::get('/dailyPackageSummary/option',[ReportController::class,'getDailyPackageSummaryReportOption']);
        //     Route::get('/settleStatement',[ReportController::class,'getSettleStatementReport']);
        //     Route::get('/settleStatement/option',[ReportController::class,'getSettleStatementReportOption']);
        //     Route::get('/operationSummary',[ReportController::class,'getOperationSummaryReport']);
        //     Route::get('/reviewAndFeedBack',[ReportController::class,'getReviewAndFeedBackReport']);
        // });
        // Route::prefix('driver')->group(function(){
        //     Route::get('/list/option',[ReportController::class,'formOptionUser']);
        //     Route::get('list',[ReportController::class,'getDriverListReport']);
        //     Route::get('delivery/summary/option',[ReportController::class,'formOptionDriver']);
        //     Route::get('delivery/summary',[ReportController::class,'driverDeliverySummaryReport']);
        //     Route::get('delivery/summary/option',[ReportController::class,'driverDeliverySummaryReportOption']);
        //     Route::get('payment',[ReportController::class,'getDriverPaymentReport']);
        //     Route::get('payment/option',[ReportController::class,'driverDeliverySummaryReportOption']);
        //     Route::get('packageDetail',[ReportController::class,'getPackageDetailReport']);
        //     Route::get('packageDetail/option',[ReportController::class,'driverDeliverySummaryReportOption']);
        //     Route::get('payment/commission',[ReportController::class,'getDriverCommissionPayment']);
        //     Route::get('payment/commission/option',[ReportController::class,'driverDeliverySummaryReportOption']);
        // });
        Route::prefix('merchant')->group(function(){
            // Route::get('list',[ReportController::class,'getMerchantListReport']);
            // Route::get('/list/option',[ReportController::class,'formOptionUser']);
            Route::get('summary',[ReportController::class,'getMerchantSummaryReportV2']);
            Route::get('summary/option',[ReportController::class,'merchantSummaryReportOptionV2']);
            // Route::get('payment',[ReportController::class,'getMerchantPaymentReport']);
            // Route::get('payment/option',[ReportController::class,'getMerchatnPaymentReportOption']);
            // Route::get('owe',[ReportController::class,'getMerchantOweFees']);
        });
    });
});
