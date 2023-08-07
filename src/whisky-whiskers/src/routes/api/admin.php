<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\AuctionController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\AuctionRequestController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\ConsignmentRequestController;

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
    ['prefix' => 'auction-stores'],
    function () {
        $defaultController = AuctionController::class;

        Route::post('/', [$defaultController, 'createAuctionStore']);
    }
);

Route::group(
    ['prefix' => 'auction-requests'],
    function () {
        $defaultController = AuctionRequestController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllAuctionRequests'])->middleware(['pagination']);
                Route::put('/{id}/approve', [$defaultController, 'approveAuctionRequest']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'consignments'],
    function () {
        $defaultController = ConsignmentRequestController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllConsignmentRequests'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getConsignmentRequestDetails']);

                Route::put('/{id}/approve', [$defaultController, 'approveConsignmentRequest']);
            }
        );
    }
);
