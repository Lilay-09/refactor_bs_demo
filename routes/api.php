<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GeneralSettingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductGroupController;
use App\Http\Controllers\ProductModelController;
use App\Http\Controllers\ProductTagController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockLocationController;
use App\Http\Controllers\StockManagementController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorTypeController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function(){
    Route::post('login',[AuthController::class,'login']);
});

Route::middleware('jwt')->group(function(){
    Route::prefix('user')->group(function(){
        Route::get('/profile', function (Request $request) {
            return response()->json(['data'=>['user'=>['name'=>'test']]]);
        });
    });
    Route::prefix('company')->group(function(){
        Route::post('update',[CompanyProfileController::class,'update']);
        Route::get('profile',[CompanyProfileController::class,'profile']);
        Route::get('info',[CompanyProfileController::class,'info']);
        // Route::get('branches',[CompanyProfileController::class,'branches']);
    });

    Route::prefix('service')->group(function(){
        Route::post('',[ServiceController::class,'createService']);
        Route::get('',[ServiceController::class,'getServices']);
        Route::get('/{id?}',[ServiceController::class,'getService']);
        Route::put('/{id?}',[ServiceController::class,'updateService']);
        Route::delete('/{id?}',[ServiceController::class,'deleteService']);
    });


    Route::prefix('expense')->group(function(){
        Route::prefix('category')->group(function(){
            Route::post('/',[ExpenseCategoryController::class,'createExpenseCategory']);
            Route::get('/',[ExpenseCategoryController::class,'getExpenseCategories']);
            Route::get('/{id?}',[ExpenseCategoryController::class,'getExpenseCategory']);
            Route::put('/{id?}',[ExpenseCategoryController::class,'updateExpenseCategory']);
            Route::delete('/{id?}',[ExpenseCategoryController::class,'deleteExpenseCategory']);
        });

        Route::post('/',[ExpenseController::class,'createExpense']);
        Route::get('/',[ExpenseController::class,'getExpenses']);
        Route::get('/{id?}',[ExpenseController::class,'getExpense']);
        Route::put('/{id?}',[ExpenseController::class,'updateExpense']);
        Route::delete('/{id?}',[ExpenseController::class,'deleteExpense']);
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
            Route::delete('',[CityController::class,'deleteCity']);
        });
    });

    Route::prefix('vendor')->group(function(){
        Route::prefix('type')->group(function(){
            Route::post('',[VendorTypeController::class,'createVendorType']);
            Route::get('',[VendorTypeController::class,'getVendorTypes']);
            Route::get('/{id?}',[VendorTypeController::class,'getVendorType']);
            Route::put('/{id?}',[VendorTypeController::class,'updateVendorType']);
            Route::delete('/{id?}',[VendorTypeController::class,'deleteVendorType']);
        });
        Route::post('',[VendorController::class,'createVendor']);
        Route::get('',[VendorController::class,'getVendors']);
        Route::get('/{id?}',[VendorController::class,'getVendor']);
        Route::put('/{id?}',[VendorController::class,'updateVendor']);
        Route::delete('/{id?}',[VendorController::class,'deleteVendor']);
    });

    Route::prefix('customer')->group(function(){
        Route::prefix('type')->group(function(){
            Route::post('',[CustomerTypeController::class,'createCustomerType']);
            Route::get('',[CustomerTypeController::class,'getCustomerTypes']);
            Route::get('/{id?}',[CustomerTypeController::class,'getCustomerType']);
            Route::put('/{id?}',[CustomerTypeController::class,'updateCustomerType']);
            Route::delete('/{id?}',[CustomerTypeController::class,'deleteCustomerType']);

        });
        Route::post('',[CustomerController::class,'createCustomer']);
        Route::get('',[CustomerController::class,'getCustomers']);
        Route::get('/{id?}',[CustomerController::class,'getCustomer']);
        Route::put('/{id?}',[CustomerController::class,'updateCustomer']);
        Route::delete('/{id?}',[CustomerController::class,'deleteCustomer']);
    });

    Route::prefix('inventory')->group(function(){
        Route::prefix('purchase')->group(function(){
            Route::prefix('receive')->group(function(){
                Route::post('/{id?}',[StockManagementController::class,'receivePurchaseOrder']);
            });
            Route::put('approve/{id}',[StockManagementController::class,'approvePurchaseOrder']);
            Route::post('',[StockManagementController::class,'createPurchaseOrder']);
            Route::get('',[StockManagementController::class,'getPurchaseOrders']);
            Route::get('/{id?}',[StockManagementController::class,'getPurchaseOrder']);
            Route::put('',[StockManagementController::class,'updatePurchaseOrder']);
        });
        Route::prefix('stock')->group(function(){
            Route::prefix('location')->group(function(){
                Route::post('',[StockLocationController::class,'createStockLocation']);
                Route::get('',[StockLocationController::class,'getStockLocations']);
                Route::get('/{id?}',[StockLocationController::class,'getStockLocation']);
                Route::put('/{id?}',[StockLocationController::class,'updateStockLocation']);
            });

            Route::prefix('item')->group(function(){
                Route::get('',[StockController::class,'getStockItems']);
                Route::get('/{id?}',[StockController::class,'getStockItem']);
                Route::put('price/{sku?}',[StockController::class,'setStockItemPrices']);
            });
        });
    });

    Route::prefix('pos')->group(function(){
        Route::post('',[PosController::class,'AddItems']);
    });

    Route::prefix('product')->group(function(){
        Route::post('/',[ProductController::class,'createProduct']);
        Route::get('/',[ProductController::class,'getProducts']);

        Route::post('variant',[ProductVariantController::class,'createVariant']);
        Route::get('variant',[ProductVariantController::class,'getVariants']);
        Route::put('/variant/{id?}',[ProductVariantController::class,'updateVariant']);
        Route::get('/variant/{id?}',[ProductVariantController::class,'getVariantById']);
        Route::delete('/variant/{id?}',[ProductVariantController::class,'deleteVariant']);
        Route::delete('variant/photo/{id?}',[ProductVariantController::class,'deleteVariantPhoto']);
        Route::get('/variants/{product_id?}',[ProductVariantController::class,'getVariantByProductId']);

        Route::prefix('tag')->group(function(){
            Route::post('',[ProductTagController::class,'createProductTag']);
            Route::get('',[ProductTagController::class,'getProductTags']);
            Route::get('/{id?}',[ProductTagController::class,'productTag']);
            Route::put('/{id?}',[ProductTagController::class,'updateProductTag']);
            Route::delete('/{id?}',[ProductTagController::class,'deleteProductTag']);
        });

        Route::get('/{id?}',[ProductController::class,'getProductById']);
        Route::put('/{id?}',[ProductController::class,'updateProduct']);
        Route::delete('/{id?}',[ProductController::class,'deleteProduct']);


    });


    Route::prefix('category')->group(function(){
        Route::post('/',[CategoryController::class,'createCategory']);
        Route::get('/',[CategoryController::class,'categories']);
        Route::get('/{id?}',[CategoryController::class,'category']);
        Route::put('/{id?}',[CategoryController::class,'updateCategory']);
        Route::delete('/{id?}',[CategoryController::class,'deleteCategory']);
    });


    Route::prefix('brand')->group(function(){
        Route::post('/',[BrandController::class,'createBrand']);
        Route::get('/',[BrandController::class,'brands']);
        Route::get('/{id?}',[BrandController::class,'brand']);
        Route::put('/{id?}',[BrandController::class,'updateBrand']);
        Route::delete('/{id?}',[BrandController::class,'deleteBrand']);
    });

    Route::prefix('model')->group(function(){
        Route::post('/',[ProductModelController::class,'createModel']);
        Route::get('/',[ProductModelController::class,'productModels']);
        Route::get('/{id?}',[ProductModelController::class,'productModel']);
        Route::put('/{id?}',[ProductModelController::class,'updateProductModel']);
        Route::delete('/{id?}',[ProductModelController::class,'deleteModel']);
    });

    Route::prefix('group')->group(function(){
        Route::post('/',[ProductGroupController::class,'createGroup']);
        Route::get('/',[ProductGroupController::class,'productGroups']);
        Route::get('/{id?}',[ProductGroupController::class,'productGroup']);
        Route::put('/{id?}',[ProductGroupController::class,'updateGroup']);
        Route::delete('/{id?}',[ProductGroupController::class,'deleteGroup']);
    });

    Route::prefix('report')->group(function (){
        Route::prefix('expense')->group(function(){
            Route::get('category',[ReportController::class,'getExpenseByCategory']);
            Route::get('monthly',[ReportController::class,'getMonthlyExpense']);
        });
    });



    Route::prefix('setting')->group(function(){
        Route::prefix('option')->group(function(){
            Route::get('products',[GeneralSettingController::class,'getProducts']);
            Route::get('customerTypes',[GeneralSettingController::class,'getCustomerTypes']);
            Route::get('brands',[GeneralSettingController::class,'getBrands']);
            Route::get('banks',[GeneralSettingController::class,'getBanks']);
            Route::get('brand/models/{brand_id?}',[GeneralSettingController::class,'getModelsByBrand']);
            Route::get('expenseCategories',[GeneralSettingController::class,'getExpenseCategories']);
            Route::get('stockLocationTypes',[GeneralSettingController::class,'getStockLocationTypes']);
        });

        Route::prefix('form')->group(function(){
            Route::get('product',[GeneralSettingController::class,'getFormProduct']);
            Route::get('purchase',[GeneralSettingController::class,'formPurchase']);
            Route::get('supplier',[GeneralSettingController::class,'formSupplier']);
            Route::get('pos',[GeneralSettingController::class,'formPOS']);
            Route::get('pos/items',[GeneralSettingController::class,'formPosItems']);
            Route::get('warehouse',[GeneralSettingController::class,'formWarehouse']);
        });
    });

});
