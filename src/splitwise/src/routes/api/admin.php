<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Splitwise\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Splitwise\App\Http\Controllers\Admin\ShoppingCartController;
use StarsNet\Project\Splitwise\App\Http\Controllers\Admin\CustomerController;

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

// SHOPPING_CART
Route::group(
    ['prefix' => '/stores/{store_id}/shopping-cart'],
    function () {
        $defaultController = ShoppingCartController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/checkout', [$defaultController, 'checkOut']);
        });
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
                Route::put('/delete', [$defaultController, 'deleteCustomers']);

                Route::get('/membership/balance', [$defaultController, 'getMembershipPointBalance'])->middleware(['pagination']);

                Route::post('/membership/credit', [$defaultController, 'addCreditToAccount']);
            }
        );
    }
);
