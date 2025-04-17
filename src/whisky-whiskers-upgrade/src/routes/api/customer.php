<?php

// Default Imports

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use StarsNet\Project\WhiskyWhiskersUpgrade\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\WhiskyWhiskersUpgrade\App\Http\Controllers\Customer\PaymentController;
use StarsNet\Project\WhiskyWhiskersUpgrade\App\Http\Controllers\Customer\TestingController;

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
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/migrate', [$defaultController, 'migrateToRegistered']);
            }
        );
    }
);

// STRIPE
Route::group(
    ['prefix' => 'payments'],
    function () {
        $defaultController = PaymentController::class;
        Route::post('/callback', [$defaultController, 'onlinePaymentCallback']);
    }
);
