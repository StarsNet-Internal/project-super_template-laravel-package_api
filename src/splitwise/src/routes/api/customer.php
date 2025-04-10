<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Splitwise\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Splitwise\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\Splitwise\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\Splitwise\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\Splitwise\App\Http\Controllers\Customer\ProfileController;

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

// AUTH
Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/user', [$defaultController, 'getAuthUserInfo']);
            }
        );
    }
);

// PROFILE
Route::group(
    ['prefix' => 'profiles'],
    function () {
        $defaultController = ProfileController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/membership/statistics', [$defaultController, 'getOrderStatistics']);

            Route::get('/membership/status', [$defaultController, 'getMembershipStatus']);

            Route::post('/membership/credit', [$defaultController, 'addCreditToAccount']);
        });
    }
);

/*
|--------------------------------------------------------------------------
| Product related
|--------------------------------------------------------------------------
*/
// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
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

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/all', [$defaultController, 'getAll'])->middleware(['pagination']);

            Route::put('/{order_id}/upload', [$defaultController, 'uploadPaymentProofAsCustomer']);
        });
    }
);
