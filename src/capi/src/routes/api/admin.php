<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Capi\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Capi\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\Capi\App\Http\Controllers\Admin\DealController;
use StarsNet\Project\Capi\App\Http\Controllers\Admin\OnlineStoreManagementController;
use StarsNet\Project\Capi\App\Http\Controllers\Admin\OrderManagementController;

Route::group(
    ['prefix' => 'tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
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
                Route::post('/', [$defaultController, 'createProduct']);

                Route::post('/{id}/details', [$defaultController, 'editProductAndDiscountDetails']);
            }
        );
    }
);

// DEAL
Route::group(
    ['prefix' => 'deals'],
    function () {
        $defaultController = DealController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllDeals'])->middleware(['pagination']);
                Route::put('/delete', [$defaultController, 'deleteDeals']);
                Route::put('/recover', [$defaultController, 'recoverDeals']);
                Route::put('/status', [$defaultController, 'updateDealStatus']);
                Route::post('/', [$defaultController, 'createDeal']);

                Route::get('/{id}/details', [$defaultController, 'getDealDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateDealDetails']);
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
                Route::get('/categories/all', [$defaultController, 'getAllCategories'])->middleware(['pagination']);
                Route::put('/categories/delete', [$defaultController, 'deleteCategories']);
                Route::put('/categories/recover', [$defaultController, 'recoverCategories']);
                Route::put('/categories/status', [$defaultController, 'updateCategoryStatus']);
                Route::post('/categories', [$defaultController, 'createCategory']);

                Route::get('/categories/parent', [$defaultController, 'getParentCategoryList'])->middleware(['pagination']);

                Route::get('/categories/{category_id}/details', [$defaultController, 'getCategoryDetails']);
                Route::put('/categories/{category_id}/details', [$defaultController, 'updateCategoryDetails']);

                Route::get('/categories/{category_id}/deals/assign', [$defaultController, 'getCategoryAssignedDeals'])->middleware(['pagination']);
                Route::get('/categories/{category_id}/deals/unassign', [$defaultController, 'getCategoryUnassignedDeals'])->middleware(['pagination']);
                Route::put('/categories/{category_id}/deals/assign', [$defaultController, 'assignDealsToCategory']);
                Route::put('/categories/{category_id}/deals/unassign', [$defaultController, 'unassignDealsFromCategory']);
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

                Route::get('/{id}/details', [$defaultController, 'getOrderDetails']);
            }
        );
    }
);
