<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\App\Http\Controllers\Customer\ProductManagementController;

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

/*
|--------------------------------------------------------------------------
| Development Uses
|--------------------------------------------------------------------------
*/

Route::group(
    ['prefix' => 'tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

/*
|--------------------------------------------------------------------------
| Product related
|--------------------------------------------------------------------------
*/

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {

        // PRODUCT_MANAGEMENT
        Route::group(
            ['prefix' => 'product-management'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::get('/products/filter', [$defaultController, 'filterProductsByCategories'])->middleware(['pagination']);

                Route::get('/products/{product_variant_id}/history', [$defaultController, 'getAuctionHistory'])->middleware(['pagination']);
            }
        );
    }
);
