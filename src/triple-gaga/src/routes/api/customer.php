<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\TenantController;

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
    ['prefix' => 'tenants'],
    function () {
        $defaultController = TenantController::class;

        Route::get('/all', [$defaultController, 'getAllTenants']);
        Route::get('/{account_id}/details', [$defaultController, 'getTenantDetails']);
        Route::get('/{account_id}/categories/hierarchy', [$defaultController, 'getTenantCategoryHierarchy']);
        Route::get('/{account_id}/stores/{store_id}/products', [$defaultController, 'filterTenantProductsByCategories']);
    }
);
