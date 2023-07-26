<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Commads\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Commads\App\Http\Controllers\Admin\OrderManagementController;
use StarsNet\Project\Commads\App\Http\Controllers\Admin\CustomerController;

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

// CUSTOMER
Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{id}/orders/all', [$defaultController, 'getOrdersAndQuotesByAllStores']);
            }
        );
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOrdersAndQuotesByStore'])->middleware(['pagination']);

                Route::get('/{id}/details', [$defaultController, 'getCustomOrderDetails']);

                Route::post('/{id}/quote', [$defaultController, 'createCustomOrderQuote']);
            }
        );
    }
);
