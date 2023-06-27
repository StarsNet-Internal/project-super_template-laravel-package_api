<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\App\Http\Controllers\Customer\OrderController;

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

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/{order_id}/reviews', [$defaultController, 'createProductReviews']);
        });
    }
);
