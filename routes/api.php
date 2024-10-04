<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\ExhangeRateController;
use App\Http\Controllers\PickUpCenterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function(){
    Route::post('login',[AuthController::class,'login']);
});

Route::middleware('jwt')->prefix('admin/v1/{lang}')->group(function(){
    Route::prefix('management')->group(function(){
        Route::get('/user', [UserController::class,'getUsers']);
        Route::get('user/profile',[UserController::class,'getProfile']);
        Route::post('/user',[UserManagementController::class,'createUser']);
        Route::get('/user/{id?}', [UserController::class,'getUser']);
        Route::put('/user/{id?}', [UserManagementController::class,'updateUser']);
        Route::put('/user/set-lock/{id?}', [UserManagementController::class,'setLockUser']);
        Route::put('/user/change-password/{id?}', [UserManagementController::class,'userChangePassword']);
        Route::prefix('role')->group(function(){
            Route::post('/', [UserManagementController::class,'createRole']);
            Route::get('/', [UserManagementController::class,'getRoles']);
            Route::get('/{id?}', [UserManagementController::class,'getRole']);
            Route::put('/{id?}', [UserManagementController::class,'updateRole']);
            Route::delete('/{id?}', [UserManagementController::class,'deleteRole']);
        });
    });
    Route::prefix('company')->group(function(){
        Route::put('',[CompanyProfileController::class,'update']);
        Route::get('',[CompanyProfileController::class,'getCompanyProfile']);
    });

    Route::prefix('xrate')->group(function(){
        Route::post('',[ExhangeRateController::class,'create']);
        Route::get('',[ExhangeRateController::class,'getXRates']);
        Route::get('{id?}',[ExhangeRateController::class,'getXRate']);
        Route::put('{id?}',[ExhangeRateController::class,'update']);
        Route::delete('{id?}',[ExhangeRateController::class,'delete']);
        Route::put('void/{id?}',[ExhangeRateController::class,'void']);

    });

    Route::prefix('order')->group(function (){
        Route::post('/quick',[PickUpCenterController::class,'createQuickOrder']);
        Route::get('',[PickUpCenterController::class,'getOrders']);
    });



    Route::prefix('location')->group(function(){
        Route::prefix('country')->group(function(){
            Route::post('',[CountryController::class,'createCountry']);
            Route::get('',[CountryController::class,'countries']);
            Route::get('/cities',[CountryController::class,'getCities']);
            Route::get('/{id?}',[CountryController::class,'country']);
            Route::put('/{id??}',[CountryController::class,'updateCountry']);
            Route::delete('',[CountryController::class,'deleteCountry']);
        });

        Route::prefix('city')->group(function(){
            Route::post('',[CityController::class,'createCity']);
            Route::get('',[CityController::class,'cities']);
            Route::get('/districts',[CityController::class,'getDistricts']);
            Route::get('/{id?}',[CityController::class,'city']);
            Route::put('/{id?}',[CityController::class,'updateCity']);
            Route::delete('/{id}',[CityController::class,'deleteCity']);
        });

        Route::prefix('district')->group(function(){
            Route::post('',[DistrictController::class,'createDistrict']);
            Route::get('',[DistrictController::class,'getDistricts']);
            Route::get('/{id?}',[DistrictController::class,'district']);
            Route::put('/{id?}',[DistrictController::class,'updateDistrict']);
            Route::delete('/{id}',[DistrictController::class,'voidDistrict']);
        });
    });


    Route::prefix('report')->group(function (){
        Route::prefix('expense')->group(function(){
            Route::get('category',[ReportController::class,'getExpenseByCategory']);
            Route::get('monthly',[ReportController::class,'getMonthlyExpense']);
        });

        Route::prefix('sale')->group(function(){
            Route::get('product',[ReportController::class,'SaleProduct']);
        });
    });



    Route::prefix('setting')->group(function(){
        Route::prefix('option')->group(function(){

        });

        Route::prefix('form')->group(function(){

        });
    });
});
