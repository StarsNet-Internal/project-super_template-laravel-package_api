<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\FakerController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\ProfileController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Customer\OrderController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

Route::group(
    ['prefix' => '/faker'],
    function () {
        $defaultController = FakerController::class;

        Route::get('/membershipPointsAndCoin', [$defaultController, 'membershipPointsAndCoin']);
        Route::get('/totalCreditedAndWithdrawal', [$defaultController, 'totalCreditedAndWithdrawal']);
        Route::get('/lineGraph', [$defaultController, 'lineGraph']);
    }
);

// PROFILE
Route::group(
    ['prefix' => 'profiles'],
    function () {
        $defaultController = ProfileController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/trade-registration', [$defaultController, 'getTradeRegistration']);
            Route::post('/trade-registration', [$defaultController, 'createTradeRegistration']);
        });
    }
);

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        // PRODUCT_MANAGEMENT
        Route::group(
            ['prefix' => 'product-management'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::get('/products/filter', [$defaultController, 'filterProductsByCategories'])->middleware(['pagination']);
                Route::post('/products/{product_id}/details', [$defaultController, 'getProductDetailsAndFilteredProductVariants']);
                Route::post('/products/{product_id}/variant-options', [$defaultController, 'getOptionLists']);

                Route::get('/related-products-urls', [$defaultController, 'getRelatedProductsUrls'])->middleware(['pagination']);
                Route::get('/products/ids', [$defaultController, 'getProductsByIDs'])->name('project.products.ids')->middleware(['pagination']);
            }
        );

        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                Route::post('/all', [$defaultController, 'getAll']);
            });
        });

        // CHECKOUT
        Route::group(
            ['prefix' => 'checkouts'],
            function () {
                $defaultController = CheckoutController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::post('/', [$defaultController, 'checkOut']);
                });
            }
        );
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/all', [$defaultController, 'getAllWithTotalQuantity'])->middleware(['pagination']);
            Route::get('/all/offline', [$defaultController, 'getAllOfflineOrdersWithTotalQuantity'])->middleware(['pagination']);
            Route::get('/{order_id}/details', [$defaultController, 'getOrderAndShipmentDetails']);
        });
    }
);
