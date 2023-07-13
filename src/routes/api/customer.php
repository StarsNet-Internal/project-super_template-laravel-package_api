<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\App\Http\Controllers\Customer\ShoppingCartController;

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

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
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

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/{order_id}/details', [$defaultController, 'getOrderAndQuoteDetailsAsCustomer']);

            Route::put('/{order_id}/upload', [$defaultController, 'uploadCustomOrderImage']);
        });
    }
);
