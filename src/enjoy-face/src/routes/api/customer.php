<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\OfflineStoreManagementController;

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

// OFFLINE_STORE
Route::group(
    ['prefix' => '/stores'],
    function () {
        $defaultController = OfflineStoreManagementController::class;

        Route::get('/categories/all', [$defaultController, 'getAllStoreCategories'])->middleware(['pagination']);
        Route::get('/filter', [$defaultController, 'filterStoresByCategories'])->middleware(['pagination']);
        Route::get('/{store_id}/details', [$defaultController, 'getStoreDetails']);
        Route::get('/{store_id}/products', [$defaultController, 'getStoreProducts'])->middleware(['pagination']);

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
            }
        );
    }
);

// REVIEW
Route::group(
    ['prefix' => '/reviews'],
    function () {
        $defaultController = OfflineStoreManagementController::class;

        Route::get('/all', [$defaultController, 'getReviews'])->middleware(['pagination']);
    }
);
