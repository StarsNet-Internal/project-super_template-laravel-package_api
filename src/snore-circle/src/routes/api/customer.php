<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\SnoreCircle\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\SnoreCircle\App\Http\Controllers\Customer\OrderController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        // PRODUCT_MANAGEMENT
        Route::group(
            ['prefix' => 'product-management'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::get('/products/reviews', [$defaultController, 'getAllReviews'])->middleware(['pagination']);
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
            Route::get('/{order_id}/details', [$defaultController, 'getOrderDetails']);
        });
    }
);
