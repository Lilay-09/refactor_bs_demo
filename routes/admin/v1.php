<?php


use App\Http\Controllers\ReportController;
use App\Http\Controllers\V1\AppSettingController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\BankController;
use App\Http\Controllers\V1\BrandImageController;
use App\Http\Controllers\V1\CityController;
use App\Http\Controllers\V1\CloudMessagingController;
use App\Http\Controllers\V1\CommuneController;
use App\Http\Controllers\V1\CompanyProfileController;
use App\Http\Controllers\V1\CompletedPackageController;
use App\Http\Controllers\V1\CountryController;
use App\Http\Controllers\V1\DefaultRemarkController;
use App\Http\Controllers\V1\DistrictController;
use App\Http\Controllers\V1\DriverManagementController;
use App\Http\Controllers\V1\DriverTransactionController;
use App\Http\Controllers\V1\ExchangeRateController;
use App\Http\Controllers\V1\FleetManagementController;
use App\Http\Controllers\V1\GeneralSettingController;
use App\Http\Controllers\V1\MerchantManagementController;
use App\Http\Controllers\V1\MerchantTransactionController;
use App\Http\Controllers\V1\PackageTrailController;
use App\Http\Controllers\V1\PickUpCenterController;
use App\Http\Controllers\V1\PriceListController;
use App\Http\Controllers\V1\PriceListNameController;
use App\Http\Controllers\V1\ProductTypeController;
use App\Http\Controllers\V1\PromotionController;
use App\Http\Controllers\V1\SocialMediaController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\UserManagementController;
use App\Http\Controllers\V1\VehicleTypeController;
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

    Route::prefix('nofication')->group(function(){
        Route::post('token',[CloudMessagingController::class,'sendNoficationViaToken']);
        Route::post('topic',[CloudMessagingController::class,'sendNoficationViaTopic']);
        Route::post('topic/subscribe',[CloudMessagingController::class,'subscribeToTopic']);
    });

    Route::prefix('driver')->group(function(){
        Route::post('',[DriverManagementController::class,'createDriver']);
        Route::get('',[DriverManagementController::class,'getDrivers']);
        Route::get('/{id}',[DriverManagementController::class,'getOneDriver']);
        Route::put('/{id}',[DriverManagementController::class,'updateDriver']);

        Route::prefix('{id}/commission')->group(function(){
            Route::get('',[DriverManagementController::class,'getDriverCommissions']);
            Route::put('',[DriverManagementController::class,'saveDriverCommission']);
        });

        Route::post('/{id}/account',[DriverManagementController::class,'createDriverAccount']);

        //** Driver Transaction Module */
        Route::prefix('transaction')->group(function(){
            Route::get('delivery/package',[DriverTransactionController::class,'getDeliveryPackages']);
            Route::put('delivery/package/{id}',[DriverTransactionController::class,'updateDeliveryPackage']);
            Route::post('delivery/receivePayment',[DriverTransactionController::class,'receivePackagesPayment']);
            Route::get('payment',[DriverTransactionController::class,'getPayments']);
            Route::put('payment',[DriverTransactionController::class,'approvePayments']);
            Route::get('settle/payment',[DriverTransactionController::class,'getApprovedPayments']);
            Route::put('settle/payment',[DriverTransactionController::class,'settleApprovedPayments']);
            Route::delete('payment/{id}',[DriverTransactionController::class,'deletePayment']);
            Route::get('balance',[DriverTransactionController::class,'getDriverBalance']);
        });
        //** Driver Commission Module */
        Route::prefix('commission')->group(function(){
            Route::get('package',[DriverTransactionController::class,'getDeliveryPackages']);
        });
    });

    Route::prefix('merchant')->group(function(){
        Route::post('',[MerchantManagementController::class,'createMerchant']);
        Route::get('',[MerchantManagementController::class,'getMerchants']);
        Route::get('/{id}',[MerchantManagementController::class,'getOneMerchant']);
        Route::put('/{id}',[MerchantManagementController::class,'updateMerchant']);

        Route::prefix('transaction')->group(function(){
            Route::get('delivery/package',[MerchantTransactionController::class,'getDeliveryPackages']);
            Route::put('delivery/package/{id}',[MerchantTransactionController::class,'updateDeliveryPackage']);
            Route::post('delivery/receivePayment',[MerchantTransactionController::class,'receivePackagesPayment']);
        });
    });

    Route::prefix('company')->group(function(){
        Route::put('',[CompanyProfileController::class,'update']);
        Route::get('',[CompanyProfileController::class,'getCompanyProfile']);
    });

    Route::prefix('xrate')->group(function(){
        Route::post('',[ExchangeRateController::class,'create']);
        Route::get('',[ExchangeRateController::class,'getXRates']);
        Route::get('{id}',[ExchangeRateController::class,'getXRate']);
        Route::put('{id}',[ExchangeRateController::class,'update']);
        Route::delete('{id}',[ExchangeRateController::class,'delete']);
        // Route::put('void/{id?}',[ExhangeRateController::class,'void']);

    });

    //** Begin PickUp Center */

    Route::prefix('order')->group(function (){
        Route::post('',[PickUpCenterController::class,'createQuickOrder']);
        Route::get('/{id}',[PickUpCenterController::class,'getOneOrder']);
        Route::put('/{id}',[PickUpCenterController::class,'updateQuickOrder']);
        Route::put('{id}/status',[PickUpCenterController::class,'setOrderStatus']);
        Route::delete('/{id}',[PickUpCenterController::class,'deleteOrder']);
        Route::get('',[PickUpCenterController::class,'getOrders']);
        Route::put('{order_id}/driver/{driver_id?}',[PickUpCenterController::class,'assignDriver']);
        Route::get('{order_id}/packages',[PickUpCenterController::class,'getPackagesByOrderId']);
        Route::post('{order_id}/package',[PickUpCenterController::class,'addPackage']);
        Route::get('package/{id}',[PickUpCenterController::class,'getOnePackageById']);
        Route::put('{id}/arrive',[PickUpCenterController::class,'arriveWarehouse']);
        Route::put('{order_id}/package/{id}',[PickUpCenterController::class,'updatePackage']);
        Route::delete('{order_id}/package/{id}',[PickUpCenterController::class,'deletePackage']);
    });

    Route::prefix('package')->group(function(){
        Route::get('',[PackageTrailController::class,'getPackages']);
        Route::put('{id}',[PackageTrailController::class,'updatePackage']);
        Route::put('{id}/driver/{driver_id}',[PackageTrailController::class,'assignDriver']);
        Route::delete('{id}',[PackageTrailController::class,'deletePackage']);
        Route::put('/{id}/return',[PackageTrailController::class,'returnPackage']);
    });

    //** End Pickup Center */

    //** Begin Fleet Management */
    Route::prefix('trip')->group(function(){
        Route::get('',[FleetManagementController::class,'getTrips']);
        Route::get('{trip_id}/package',[FleetManagementController::class,'getTripPackages']);
        Route::put('{trip_id}/package/status',[FleetManagementController::class,'setPackageStatus']);
    });
    //** End Fleet Management */

    Route::prefix('finished')->group(function(){
        Route::get('package',[CompletedPackageController::class,'getFinishedPackages']);
        Route::put('package/{id}',[CompletedPackageController::class,'updatePackage']);
    });

    Route::prefix('bank')->group(function(){
        Route::post('',[BankController::class,'createBank']);
        Route::get('/',[BankController::class,'getBanks']);
        Route::get('{id}',[BankController::class,'getOneBank']);
        Route::put('/{id}',[BankController::class,'updateBank']);
        Route::delete('',[BankController::class,'deleteBank']);
    });


    Route::prefix('zone')->group(function(){
        Route::post('',[ZoneController::class,'createZone']);
        Route::get('',[ZoneController::class,'getZones']);
        Route::get('/{id}',[ZoneController::class,'getOneZone']);
        Route::put('/{id}',[ZoneController::class,'updateZone']);
        Route::delete('/{id}',[ZoneController::class,'deleteZone']);
    });

    Route::prefix('priceList')->group(function(){
        Route::get('zone',[PriceListController::class,'getPriceZones']);
        Route::post('',[PriceListController::class,'createPriceList']);
        // Route::get('',[PriceListController::class,'getPriceList']);
        Route::put('assign',[PriceListController::class,'assignZoneToPriceList']);
        Route::get('/{id}',[PriceListController::class,'getOnePriceList']);
        Route::put('/{id}',[PriceListController::class,'updatePriceList']);
        Route::delete('/{id}',[PriceListController::class,'deletePriceList']);

        Route::prefix('name')->group(function(){
            Route::post('',[PriceListNameController::class,'createPriceListName']);
            Route::get('{id}',[PriceListNameController::class,'getOnePriceListName']);
            Route::put('{id}',[PriceListNameController::class,'updatePriceListName']);
        });
    });

    Route::prefix('productType')->group(function(){
        Route::post('',[ProductTypeController::class,'createProductType']);
        Route::get('',[ProductTypeController::class,'getProductTypes']);
        Route::get('/{id}',[ProductTypeController::class,'getOneProductType']);
        Route::put('/{id}',[ProductTypeController::class,'updateProductType']);
        Route::delete('/{id}',[ProductTypeController::class,'deleteProductType']);
    });

    Route::prefix('vehicleType')->group(function(){
        Route::post('',[VehicleTypeController::class,'createVehicleType']);
        Route::get('',[VehicleTypeController::class,'getVehicleTypes']);
        Route::get('/{id}',[VehicleTypeController::class,'getOneVehicleType']);
        Route::put('/{id}',[VehicleTypeController::class,'updateVehicleType']);
        Route::delete('/{id}',[VehicleTypeController::class,'deleteVehicleType']);
    });




    Route::prefix('location')->group(function(){
        Route::prefix('country')->group(function(){
            Route::post('',[CountryController::class,'createCountry']);
            Route::get('',[CountryController::class,'countries']);
            Route::get('/{id}/city',[CountryController::class,'getCitiesByCountry']);
            Route::get('/{id}/district',[CountryController::class,'getDistrictsByCountry']);
            Route::get('/{id}/commune',[CountryController::class,'getCommunesByCountry']);
            Route::get('/{id}',[CountryController::class,'country']);
            Route::put('/{id}',[CountryController::class,'updateCountry']);
            Route::delete('/{id}',[CountryController::class,'deleteCountry']);
        });

        Route::prefix('city')->group(function(){
            Route::post('',[CityController::class,'createCity']);
            Route::get('',[CityController::class,'cities']);
            Route::get('{id}/district',[CityController::class,'getDistrictsByCity']);
            Route::get('/{id}',[CityController::class,'city']);
            Route::put('/{id}',[CityController::class,'updateCity']);
            Route::delete('/{id}',[CityController::class,'deleteCity']);
        });

        Route::prefix('district')->group(function(){
            Route::post('',[DistrictController::class,'createDistrict']);
            Route::get('',[DistrictController::class,'getDistricts']);
            Route::get('{id}/commune',[DistrictController::class,'getCommunesByDistrict']);
            Route::get('/{id}',[DistrictController::class,'district']);
            Route::put('/{id}',[DistrictController::class,'updateDistrict']);
            Route::delete('/{id}',[DistrictController::class,'deleteDistrict']);
        });

        Route::prefix('commune')->group(function(){
            Route::post('',[CommuneController::class,'createCommune']);
            Route::get('',[CommuneController::class,'getCommunes']);
            Route::get('/{id}',[CommuneController::class,'getOneCommune']);
            Route::put('/{id}',[CommuneController::class,'updateCommune']);
            Route::delete('/{id}',[CommuneController::class,'deleteCommune']);
        });
    });

    Route::prefix('brandImage')->group(function(){
        Route::post('',[BrandImageController::class,'createBrandImage']);
        Route::get('',[BrandImageController::class,'getBrandImages']);
        Route::get('/{id}',[BrandImageController::class,'getOneBrandImage']);
        Route::put('/{id}',[BrandImageController::class,'updateBrandImage']);
        Route::delete('/{id}',[BrandImageController::class,'deleteBrandImage']);
    });

    Route::prefix('remark')->group(function(){
        Route::post('',[DefaultRemarkController::class,'createDefaultRemark']);
        Route::get('',[DefaultRemarkController::class,'getDefaultRemarks']);
        Route::get('/{id}',[DefaultRemarkController::class,'getOneDefaultRemark']);
        Route::put('/{id}',[DefaultRemarkController::class,'updateDefaultRemark']);
        Route::delete('/{id}',[DefaultRemarkController::class,'deleteDefaultRemark']);
    });

    Route::prefix('socialMedia')->group(function(){
        Route::post('',[SocialMediaController::class,'createSocialMedia']);
        Route::get('',[SocialMediaController::class,'getSocialMedias']);
        Route::get('/{id}',[SocialMediaController::class,'getOneSocialMedia']);
        Route::put('/{id}',[SocialMediaController::class,'updateSocialMedia']);
        Route::delete('/{id}',[SocialMediaController::class,'deleteSocialMedia']);
    });

    Route::prefix('promotion')->group(function(){
        Route::post('',[PromotionController::class,'createPromotion']);
        Route::get('',[PromotionController::class,'getPromotions']);
        Route::get('/{id}',[PromotionController::class,'getOnePromotion']);
        Route::put('/{id}',[PromotionController::class,'updatePromotion']);
        Route::delete('/{id}',[PromotionController::class,'deletePromotion']);
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

    Route::put('termCondition',[AppSettingController::class,'saveTermCondition']);
    Route::put('privacyStatement',[AppSettingController::class,'savePrivacyStatement']);
    Route::get('privacyStatement/{channel}',[AppSettingController::class,'getPrivacyStatement']);
    Route::get('termCondition/{channel}',[AppSettingController::class,'getTermCondition']);


    Route::prefix('setting')->group(function(){
        Route::prefix('option')->group(function(){
            Route::get('channel',[GeneralSettingController::class,'getOptionsChannel']);
            Route::get('zone',[GeneralSettingController::class,'getOptionsZone']);
            Route::get('pickup/status',[GeneralSettingController::class,'getOptionsPickupStatus']);
            Route::get('driver',[GeneralSettingController::class,'getOptionsDriver']);
            Route::get('zone/price/{zone_id}',[GeneralSettingController::class,'getPriceByZone']);
            Route::get('country',[GeneralSettingController::class,'getOptionsCountry']);
            Route::get('city',[GeneralSettingController::class,'getOptionsCity']);
            Route::get('district',[GeneralSettingController::class,'getOptionsDistrict']);
            Route::get('commune',[GeneralSettingController::class,'getOptionsCommune']);
            Route::get('currencyPair',[GeneralSettingController::class,'getOptionsCurrencyPair']);
            Route::get('country/city/{country_id}',[GeneralSettingController::class,'getOptionsCityByCountry']);
            Route::get('city/district/{city_id}',[GeneralSettingController::class,'getOptionsDistrictByCity']);
            Route::get('district/commune/{district_id}',[GeneralSettingController::class,'getOptionsCommuneByDistrict']);
            Route::get('priceList/name',[GeneralSettingController::class,'getOptionsPriceListName']);
        });

        Route::prefix('form')->group(function(){
            Route::get('pricelist',[GeneralSettingController::class,'getFormPriceList']);
            Route::get('quickOrder',[GeneralSettingController::class,'getFormOrder']);
            Route::get('package',[GeneralSettingController::class,'getFormPackage']);
            Route::get('order/status',[GeneralSettingController::class,'getFormSetOrderStatus']);
            Route::get('packageTrail',[GeneralSettingController::class,'getFormPackageTrail']);
            Route::get('fleet',[GeneralSettingController::class,'getFormFleet']);
            Route::get('promotion',[GeneralSettingController::class,'getFormPromotion']);
            Route::get('remark',[GeneralSettingController::class,'getFormRemark']);
        });
    });
});
