<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\OfflineStoreManagementController;

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

// PRODUCT
Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllProducts'])->middleware(['pagination']);

                Route::put('/reviews/status', [$defaultController, 'updateReviewStatus']);
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
                Route::get('/categories/all', [$defaultController, 'getAllStoreCategories'])->middleware(['pagination']);
                Route::post('/categories', [$defaultController, 'createStoreCategory']);

                Route::put('/categories/{category_id}/stores/assign', [$defaultController, 'assignStoresToCategory']);
                Route::put('/categories/{category_id}/stores/unassign', [$defaultController, 'unassignStoresFromCategory']);
            }
        );
    }
);
