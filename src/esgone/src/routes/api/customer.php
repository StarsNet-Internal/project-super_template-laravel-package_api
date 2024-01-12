<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Esgone\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Esgone\App\Http\Controllers\Customer\CustomerController;
use StarsNet\Project\Esgone\App\Http\Controllers\Customer\OrderManagementController;
use StarsNet\Project\Esgone\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\Esgone\App\Http\Controllers\Customer\ShoppingCartController;


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

Route::group(
    ['prefix' => 'tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// AUTH
Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::post('/register', [$defaultController, 'register']);
    }
);

Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::get('/all', [$defaultController, 'getAllCustomers'])->middleware(['pagination']);
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
                Route::get('/products/ids', [$defaultController, 'getProductsByIDs'])->name('esgone.products.ids')->middleware(['pagination']);
            }
        );

        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                Route::get('/related-products-urls', [$defaultController, 'getRelatedProductsUrls'])->middleware(['pagination']);

                Route::post('/all', [$defaultController, 'getAll']);
            });
        });
    }
);

Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOrdersByStore'])->middleware(['pagination']);
                Route::get('/{order_id}/details', [$defaultController, 'getOrderDetailsAsCustomer']);
            }
        );
    }
);
