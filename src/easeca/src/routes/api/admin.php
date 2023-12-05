<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\GeneralDeliveryScheduleController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\OnlineStoreManagementController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\CustomerController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

Route::group(
    ['prefix' => '/general-delivery-schedule'],
    function () {
        $defaultController = GeneralDeliveryScheduleController::class;

        Route::get('/details', [$defaultController, 'getGeneralDeliverySchedule']);
        Route::put('/details', [$defaultController, 'updateGeneralDeliverySchedule']);
    }
);

Route::group(
    ['prefix' => '/order-cut-off-schedule'],
    function () {
        $defaultController = GeneralDeliveryScheduleController::class;

        Route::get('/all', [$defaultController, 'getAllOrderCutOffSchedule']);
        Route::put('/details', [$defaultController, 'updateOrderCutOffSchedule']);
        Route::put('/all', [$defaultController, 'updateOrderCutOffSchedules']);
    }
);

// PRODUCT
Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllProducts'])->middleware(['pagination']);
            }
        );
    }
);

// ONLINE_STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        $defaultController = OnlineStoreManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/categories/{category_id}/products/unassign', [$defaultController, 'getCategoryUnassignedProducts'])->middleware(['pagination']);
            }
        );
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
                Route::get('/all', [$defaultController, 'getAllCustomers'])->middleware(['pagination']);
                Route::post('/', [$defaultController, 'createCustomer']);

                Route::get('/{id}/details', [$defaultController, 'getCustomerDetails']);

                Route::put('/approve', [$defaultController, 'approveCustomerAccounts']);
            }
        );
    }
);
