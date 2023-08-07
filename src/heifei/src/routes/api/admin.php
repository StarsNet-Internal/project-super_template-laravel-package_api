<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\HeiFei\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\HeiFei\App\Http\Controllers\Admin\OrderController;
use StarsNet\Project\HeiFei\App\Http\Controllers\Admin\ProductController;

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
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::post('/', [$defaultController, 'createOrder']);
        Route::get('/all', [$defaultController, 'getAllOrders'])->middleware(['pagination']);
    }
);

Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::post('/', [$defaultController, 'createProduct']);
        Route::get('/all', [$defaultController, 'getAllProducts'])->middleware(['pagination']);
    }
);
