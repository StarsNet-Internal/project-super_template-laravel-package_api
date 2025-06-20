<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Auction\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Auction\App\Http\Controllers\Admin\ServiceController;

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

Route::group(
    ['prefix' => 'services'],
    function () {
        $defaultController = ServiceController::class;

        Route::post('/payment/callback', [$defaultController, 'paymentCallback']);
        Route::post('/auction-orders/create', [$defaultController, 'createAuctionOrder']);
    }
);
