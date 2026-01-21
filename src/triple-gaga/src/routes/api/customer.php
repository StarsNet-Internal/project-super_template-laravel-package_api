<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\LoginRecordController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\TenantController;

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

Route::group(
    ['prefix' => 'tenants'],
    function () {
        $defaultController = TenantController::class;

        Route::get('/all', [$defaultController, 'getAllTenants'])->middleware(['pagination']);
        Route::get('/{account_id}/details', [$defaultController, 'getTenantDetails']);
        Route::get('/{account_id}/categories/hierarchy', [$defaultController, 'getTenantCategoryHierarchy'])->middleware(['pagination']);
        Route::get('/{account_id}/stores/{store_id}/products', [$defaultController, 'filterTenantProductsByCategories'])->middleware(['pagination']);
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


Route::group(
    ['prefix' => 'login-records'],
    function () {
        Route::group(
            ['middleware' => 'auth:api'],
            function () {
                Route::post('/', [LoginRecordController::class, 'createLoginRecord']);
            }
        );
    }
);
