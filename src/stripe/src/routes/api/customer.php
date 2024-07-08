<?php

// Default Imports

use Illuminate\Support\Facades\Route;
use StarsNet\Project\Stripe\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\Stripe\App\Http\Controllers\Customer\TestingController;

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
    ['prefix' => 'stores/{store_id}'],
    function () {
        $defaultController = OrderController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/checkout', [$defaultController, 'checkout']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'payments'],
    function () {
        $defaultController = OrderController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/callback', [$defaultController, 'onlinePaymentCallback']);
            }
        );
    }
);
