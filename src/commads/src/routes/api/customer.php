<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Commads\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Commads\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\Commads\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\Commads\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\Commads\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\Commads\App\Http\Controllers\Customer\ShoppingCartController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
|--------------------------------------------------------------------------
| Development Uses
|--------------------------------------------------------------------------
*/

Route::group(
    ['prefix' => 'tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// AUTHENTICATION
Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/migrate', [$defaultController, 'migrateToTyped']);
            }
        );
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

                Route::get('/related-products-urls', [$defaultController, 'getRelatedProductsUrls'])->middleware(['pagination']);
                Route::get('/products/ids', [$defaultController, 'getProductsByIDs'])->name('commads.products.ids')->middleware(['pagination']);
            }
        );

        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                Route::get('/related-products-urls', [$defaultController, 'getRelatedProductsUrls'])->middleware(['pagination']);

                Route::post('/add-to-cart', [$defaultController, 'addQuotedItemsToCart']);
            });
        });

        // CHECKOUT
        Route::group(
            ['prefix' => 'checkouts'],
            function () {
                $defaultController = CheckoutController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::post('/', [$defaultController, 'checkOutQuote']);
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

        Route::get('/{order_id}/details/guest', [$defaultController, 'getOrderAndQuoteDetailsAsGuest']);

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/all', [$defaultController, 'getAllWithQuoteDetails'])->middleware(['pagination']);
            Route::get('/{order_id}/details', [$defaultController, 'getOrderAndQuoteDetailsAsCustomer']);

            Route::put('/{order_id}/upload', [$defaultController, 'uploadCustomOrderImage']);
        });
    }
);
