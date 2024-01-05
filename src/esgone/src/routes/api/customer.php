<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Esgone\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Esgone\App\Http\Controllers\Customer\CustomerController;
use StarsNet\Project\Esgone\App\Http\Controllers\Customer\OrderManagementController;
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

Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::get('/all', [$defaultController, 'getAllCustomers'])->middleware(['pagination']);
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
            }
        );
    }
);

// SHOPPING_CART
Route::group(
    ['prefix' => '/stores/{store_id}/shopping-cart'],
    function () {
        $defaultController = ShoppingCartController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/all', [$defaultController, 'getAll']);
        });
    }
);
