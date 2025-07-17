<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\GeneralDeliveryScheduleController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\ProductReviewController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\OnlineStoreManagementController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\OfflineStoreManagementController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\OrderManagementController;
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

                Route::post('/copy', [$defaultController, 'copyProducts']);
            }
        );
    }
);

// PRODUCT_REVIEW
Route::group(
    ['prefix' => 'reviews'],
    function () {
        $defaultController = ProductReviewController::class;

        Route::get('/{id}/details', [$defaultController, 'getReviewDetails']);
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

// OFFLINE_STORE
Route::group(
    ['prefix' => '/stores'],
    function () {
        $defaultController = OfflineStoreManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/offline/all', [$defaultController, 'getAllOfflineStores'])->middleware(['pagination']);
                Route::put('/delete', [$defaultController, 'deleteStores']);
            }
        );
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOrdersByStore'])->middleware(['pagination']);

                Route::put('/{id}/address', [$defaultController, 'updateDeliveryAddress']);
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
                Route::put('/delete', [$defaultController, 'deleteCustomers']);
                Route::post('/', [$defaultController, 'createCustomer']);

                Route::get('/{id}/details', [$defaultController, 'getCustomerDetails']);

                Route::put('/approve', [$defaultController, 'approveCustomerAccounts']);
                Route::put('/{id}/store', [$defaultController, 'updateAssignedStore']);
            }
        );
    }
);
