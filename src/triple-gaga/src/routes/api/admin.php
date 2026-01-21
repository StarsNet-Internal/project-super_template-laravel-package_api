<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\UserController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\OrderManagementController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\RefillInventoryRequestController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\StaffManagementController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\LoginRecordController;

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
    ['prefix' => 'users'],
    function () {
        Route::put('/password', [UserController::class, 'updateUserPassword']);
    }
);

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

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{id}/details', [$defaultController, 'getCustomerDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'staff'],
    function () {
        $defaultController = StaffManagementController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::put('/tenants/{account_id}/details', [$defaultController, 'updateTenantDetails']);

            Route::get('/orders/all', [$defaultController, 'getOrdersByAllStores'])->middleware(['pagination']);
        });
    }
);

Route::group(
    ['prefix' => 'refills'],
    function () {
        $defaultController = RefillInventoryRequestController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/', [$defaultController, 'createRefillInventoryRequest']);
                Route::get('/all', [$defaultController, 'getRefillInventoryRequests'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getRefillInventoryRequestDetails']);
                Route::put('/{id}/approve', [$defaultController, 'approveRefillInventoryRequest']);
                Route::put('/{id}/delete', [$defaultController, 'deleteRefillInventoryRequest']);
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
                Route::post('/', [$defaultController, 'createProduct']);
                Route::get('/all', [$defaultController, 'getTenantProducts'])->middleware(['pagination']);
                Route::get('/variants/all', [$defaultController, 'getTenantProductVariants'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::put('/{id}/tracking-number', [$defaultController, 'updateTrackingNumber']);
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
                Route::get('/accounts/all', [LoginRecordController::class, 'getAllAccounts'])->middleware(['pagination']);
                Route::get('/all', [LoginRecordController::class, 'getAll'])->middleware(['pagination']);
                Route::put('/{id}/details', [LoginRecordController::class, 'updateLoginRecord']);
                Route::post('/', [LoginRecordController::class, 'createLoginRecord']);
            }
        );
    }
);
