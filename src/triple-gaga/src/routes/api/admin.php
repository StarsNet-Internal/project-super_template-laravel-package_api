<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\RefillInventoryRequestController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\OrderManagementController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Admin\StaffManagementController;

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
