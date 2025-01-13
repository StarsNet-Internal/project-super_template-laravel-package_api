<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\Videocom\App\Http\Controllers\Admin\AccountController;
use StarsNet\Project\Videocom\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Videocom\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\Videocom\App\Http\Controllers\Admin\CustomerGroupController;
use StarsNet\Project\Videocom\App\Http\Controllers\Admin\ServiceController;

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
    ['prefix' => 'accounts'],
    function () {
        $defaultController = AccountController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::put('/{id}/details', [$defaultController, 'updateAccountDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'customer-groups'],
    function () {
        $defaultController = CustomerGroupController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{id}/customers/assign', [$defaultController, 'getCustomerGroupAssignedCustomers'])->middleware(['pagination']);
                Route::get('/{id}/customers/unassign', [$defaultController, 'getCustomerGroupUnassignedCustomers'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllCustomers'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getCustomerDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'services'],
    function () {
        $defaultController = ServiceController::class;

        Route::post('/payment/callback', [$defaultController, 'paymentCallback']);
    }
);
