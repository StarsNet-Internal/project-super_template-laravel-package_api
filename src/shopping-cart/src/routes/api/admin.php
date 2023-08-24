<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\ShoppingCart\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\ShoppingCart\App\Http\Controllers\Admin\OrderManagementController;

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
