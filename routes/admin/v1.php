<?php


use App\Http\Controllers\ReportController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CityController;
use App\Http\Controllers\V1\CommuneController;
use App\Http\Controllers\V1\CompanyProfileController;
use App\Http\Controllers\V1\CountryController;
use App\Http\Controllers\V1\DistrictController;
use App\Http\Controllers\V1\ExhangeRateController;
use App\Http\Controllers\V1\GeneralSettingController;
use App\Http\Controllers\V1\PickUpCenterController;
use App\Http\Controllers\V1\PriceListController;
use App\Http\Controllers\V1\ProductTypeController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\UserManagementController;
use App\Http\Controllers\V1\ZoneController;
use Illuminate\Support\Facades\Route;


Route::prefix('admin/v1/auth')->group(function(){
    Route::post('login',[AuthController::class,'login']);
});

Route::middleware(['jwt','localize'])->prefix('admin/v1/{lang}')->group(function(){
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
        Route::post('',[PickUpCenterController::class,'createQuickOrder']);
        Route::get('',[PickUpCenterController::class,'getOrders']);
        Route::put('{order_id}/driver/{driver_id?}',[PickUpCenterController::class,'assignDriver']);
        Route::post('{order_id}/package',[PickUpCenterController::class,'addPackage']);
        // Route::put('')
    });


    Route::prefix('zone')->group(function(){
        Route::post('',[ZoneController::class,'createZone']);
        Route::get('',[ZoneController::class,'getZones']);
        Route::get('/{id}',[ZoneController::class,'getOneZone']);
        Route::put('/{id}',[ZoneController::class,'updateZone']);
        Route::delete('/{id}',[ZoneController::class,'deleteZone']);
    });

    Route::prefix('pricelist')->group(function(){
        Route::post('',[PriceListController::class,'createPriceList']);
        Route::get('',[PriceListController::class,'getPriceList']);
        Route::get('/{id}',[PriceListController::class,'getOnePriceList']);
        Route::put('/{id}',[PriceListController::class,'updatePriceList']);
        Route::delete('/{id}',[PriceListController::class,'deletePriceList']);
    });

    Route::prefix('productType')->group(function(){
        Route::post('',[ProductTypeController::class,'createProductType']);
        Route::get('',[ProductTypeController::class,'getProductTypes']);
        Route::get('/{id}',[ProductTypeController::class,'getOneProductType']);
        Route::put('/{id}',[ProductTypeController::class,'updateProductType']);
        Route::delete('/{id}',[ProductTypeController::class,'deleteProductType']);
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

        Route::prefix('commune')->group(function(){
            Route::post('',[CommuneController::class,'createCommune']);
            Route::get('',[CommuneController::class,'getCommunes']);
            Route::get('/{id?}',[CommuneController::class,'getOneCommune']);
            Route::put('/{id?}',[CommuneController::class,'updateCommune']);
            Route::delete('/{id}',[CommuneController::class,'voidCommune']);
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
            Route::get('zone',[GeneralSettingController::class,'getOptionsZone']);
            Route::get('zone/price/{zone_id}',[GeneralSettingController::class,'getPriceByZone']);
            Route::get('country',[GeneralSettingController::class,'getOptionsCountry']);
            Route::get('country/city/{country_id}',[GeneralSettingController::class,'getOptionsCityByCountry']);
            Route::get('city/district/{city_id}',[GeneralSettingController::class,'getOptionsDistrictByCity']);
            Route::get('district/commune/{district_id}',[GeneralSettingController::class,'getOptionsCommuneByDistrict']);
        });

        Route::prefix('form')->group(function(){
            Route::get('pricelist',[GeneralSettingController::class,'getFormPriceList']);
            Route::get('quickOrder',[GeneralSettingController::class,'getFormOrder']);
            Route::get('package',[GeneralSettingController::class,'getFormPackage']);
        });
    });
});
