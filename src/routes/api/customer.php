<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\OrderController;

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
