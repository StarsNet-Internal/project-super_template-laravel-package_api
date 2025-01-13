<?php

// Default Imports

use Illuminate\Support\Facades\Route;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\AccountController;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\AuthController;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\ProductController;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\ServiceController;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\TestingController;

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
        $defaultController = AuthController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/customer', [$defaultController, 'getCustomerInfo']);
            }
        );
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

Route::group(
    ['prefix' => 'accounts'],
    function () {
        $defaultController = AccountController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/customer-groups', [$defaultController, 'getAllCustomerGroups'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOwnedProducts'])->middleware(['pagination']);
                Route::get('/{product_id}/details', [$defaultController, 'getProductDetails']);
                Route::put('/listing-status', [$defaultController, 'updateListingStatuses']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'services'],
    function () {
        $defaultController = ServiceController::class;

        Route::get('/time/now', [$defaultController, 'checkCurrentTime']);
        Route::get('/timezone/now', [$defaultController, 'checkOtherTimeZone']);
    }
);
