<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\ExhangeRateController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GeneralSettingController;
use App\Http\Controllers\InternalController;
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
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorTypeController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function(){
    Route::post('login',[AuthController::class,'login']);
});

Route::middleware('jwt')->group(function(){
    Route::prefix('internal')->group(function(){
        Route::post('movementType',[InternalController::class,'createMovementType']);
    });

    Route::prefix('management')->group(function(){
        Route::get('/user', [UserController::class,'getUsers']);
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
    Route::prefix('tax')->group(function(){
        Route::post('',[TaxController::class,'create']);
        Route::get('',[TaxController::class,'getTaxes']);
        Route::get('{id?}',[TaxController::class,'getTax']);
        Route::put('{id?}',[TaxController::class,'update']);
        Route::delete('{id?}',[TaxController::class,'delete']);
        Route::put('void/{id?}',[TaxController::class,'void']);
        Route::put('unvoid/{id?}',[TaxController::class,'unVoide']);

    });

    Route::prefix('bank')->group(function(){
        Route::post('',[BankController::class,'create']);
        Route::get('',[BankController::class,'getBanks']);
        Route::get('{id?}',[BankController::class,'getBank']);
        Route::put('{id?}',[BankController::class,'update']);
        Route::delete('{id?}',[BankController::class,'delete']);
        Route::put('void/{id?}',[BankController::class,'void']);
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
            Route::prefix(prefix: 'location')->group(function(){
                Route::post('',[StockLocationController::class,'createStockLocation']);
                Route::get('',[StockLocationController::class,'getStockLocations']);
                Route::get('/{id?}',[StockLocationController::class,'getStockLocation']);
                Route::put('/{id?}',[StockLocationController::class,'updateStockLocation']);
                Route::get('warehouse/items/{id?}',[StockLocationController::class,'getStockItemByWarehouse']);
            });

            Route::prefix('transfer')->group(function(){
                Route::post('',[StockManagementController::class,'stockTransform']);
                Route::get('',[StockManagementController::class,'getTransferList']);
            });
            Route::prefix('missing')->group(function(){
                Route::post('',[StockManagementController::class,'createStockMissingItem']);
                Route::get('',[StockManagementController::class,'getStockMissingItem']);
                Route::get('/{id}',[StockManagementController::class,'getOneStockMissingItem']);
                Route::put('/{id}',[StockManagementController::class,'updateStockMissingItem']);

                Route::put('approve/item/{id}',[StockManagementController::class,'approveMissingItemById']);
                Route::put('approve/item/list/{id}',[StockManagementController::class,'approveListMissingItems']);
                Route::put('approve/list',[StockManagementController::class,'approveAllMissingStock']);
                Route::put('approve/{id}',[StockManagementController::class,'approveAllMissingItems']);


                Route::delete('void/list',[StockManagementController::class,'voidAllMissingStock']);
                Route::delete('void/{id}',[StockManagementController::class,'voidParentAndRelatedMissingItems']);
                Route::delete('void/item/list/{id}',[StockManagementController::class,'voidMissingStockByCheckItem']);
                Route::delete('void/item/{id}',[StockManagementController::class,'voidMissingStockByItem']);

                Route::get('unapprove/count',[StockManagementController::class,'countUnapproveOnMissingStock']);
            });

            Route::prefix('takeOut')->group(function(){
                Route::post('',[StockManagementController::class,'createTakeOutStock']);
                Route::get('',[StockManagementController::class,'getStockMissingItem']);
                Route::get('/{id}',[StockManagementController::class,'getOneStockMissingItem']);
                Route::put('/{id}',[StockManagementController::class,'updateStockMissingItem']);
                Route::put('approve/item/{id}',[StockManagementController::class,'approveMissingItemById']);
                Route::put('approve/list/{id}',[StockManagementController::class,'approveListMissingItems']);
                Route::put('approve/all/{id}',[StockManagementController::class,'approveAllMissingItems']);
                Route::delete('void/all/{id}',[StockManagementController::class,'voidAllMissingStock']);
                Route::delete('void/list/{id}',[StockManagementController::class,'voidByCheckItem']);
                Route::delete('void/item/{id}',[StockManagementController::class,'voidByItem']);
            });

            // Route::prefix('takeOut')->group(function(){
            //     Route::post('',[StockManagementController::class,'createTakeOutStock']);
            //     Route::get('',[StockManagementController::class,'getTakeOutStock']);
            //     Route::get('/{id}',[StockManagementController::class,'getOneTakeOutStock']);
            //     Route::put('/{id}',[StockManagementController::class,'updateTakeOutStock']);
            //     Route::put('approve/{id}',[StockManagementController::class,'approveTakeOutStock']);
            //     Route::delete('void/{id}',[StockManagementController::class,'voidTakeOutStock']);
            // });


            Route::prefix('item')->group(function(){
                Route::get('',[StockController::class,'getStockItems']);
                Route::get('/{id?}',[StockController::class,'getStockItem']);
                Route::put('price/{id?}',[StockController::class,'setStockItemPrices']);
            });
        });
    });

    Route::prefix('pos')->group(function(){
        Route::post('',[PosController::class,'AddItems']);
    });

    Route::prefix('product')->group(function(){
        Route::post('/',[ProductController::class,'createProduct']);
        Route::get('/',[ProductController::class,'getProducts']);
        Route::put('/void/{id?}',[ProductController::class,'voidProduct']);
        Route::put('/unvoid/{id?}',[ProductController::class,'unVoidProduct']);

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

        Route::prefix('sale')->group(function(){
            Route::get('product',[ReportController::class,'SaleProduct']);
        });
    });



    Route::prefix('setting')->group(function(){
        Route::prefix('option')->group(function(){
            Route::get('products',[GeneralSettingController::class,'getProducts']);
            Route::get('stock/sku',[GeneralSettingController::class,'getStockSku']);
            Route::get('customerTypes',[GeneralSettingController::class,'getCustomerTypes']);
            Route::get('brands',[GeneralSettingController::class,'getBrands']);
            Route::get('warehouses',[GeneralSettingController::class,'getWarehouses']);
            Route::get('banks',[GeneralSettingController::class,'getBanks']);
            Route::get('brand/models/{brand_id?}',[GeneralSettingController::class,'getModelsByBrand']);
            Route::get('expenseCategories',[GeneralSettingController::class,'getExpenseCategories']);
            Route::get('stockLocationTypes',[GeneralSettingController::class,'getStockLocationTypes']);
        });

        Route::prefix('form')->group(function(){
            Route::get('product',[GeneralSettingController::class,'getFormProduct']);
            Route::get('adjustment',[GeneralSettingController::class,'getFormAdjustment']);
            Route::get('user',[GeneralSettingController::class,'getFormUser']);
            Route::get('purchase',[GeneralSettingController::class,'formPurchase']);
            Route::get('transfer',[GeneralSettingController::class,'formTransfer']);
            Route::get('receive',[GeneralSettingController::class,'formReceive']);
            Route::get('supplier',[GeneralSettingController::class,'formSupplier']);
            Route::get('pos',[GeneralSettingController::class,'formPOS']);
            Route::get('pos/items',[GeneralSettingController::class,'formPosItems']);
            Route::get('warehouse',[GeneralSettingController::class,'formWarehouse']);
            Route::get('stock/filter',[GeneralSettingController::class,'getFromStockFilter']);
        });
    });
});
