<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Green360\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Green360\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\Green360\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\Green360\App\Http\Controllers\Customer\OrderController;


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

        Route::post('/login', [$defaultController, 'login']);
    }
);

Route::group(
    ['prefix' => 'stores/{store_id}'],
    function () {
        $defaultController = CheckoutController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/checkouts', [$defaultController, 'checkout']);
            }
        );
    }
);

// STRIPE
Route::group(
    ['prefix' => 'payments'],
    function () {
        $defaultController = OrderController::class;
        Route::post('/callback', [$defaultController, 'onlinePaymentCallback']);
    }
);
