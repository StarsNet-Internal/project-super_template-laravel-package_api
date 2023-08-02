<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\BidController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\ConsignmentRequestController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\TestingController;

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
    ['prefix' => 'auctions'],
    function () {
        $defaultController = AuctionController::class;

        Route::get('/all', [$defaultController, 'getAllAuctions'])->middleware(['pagination']);
        Route::get('/{id}/details', [$defaultController, 'getAuctionDetails']);
    }
);

Route::group(
    ['prefix' => 'auction-requests'],
    function () {
        $defaultController = AuctionRequestController::class;

        Route::post('/', [$defaultController, 'createAuctionRequest']);
        Route::get('/all', [$defaultController, 'getAllAuctionRequests'])->middleware(['pagination']);
    }
);

Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::post('/migrate', [$defaultController, 'migrateToRegistered']);
    }
);

Route::group(
    ['prefix' => 'bids'],
    function () {
        $defaultController = BidController::class;

        Route::post('/', [$defaultController, 'createBid']);
        Route::get('/all', [$defaultController, 'getAllBids'])->middleware(['pagination']);
    }
);

Route::group(
    ['prefix' => 'consignments'],
    function () {
        $defaultController = ConsignmentRequestController::class;

        Route::post('/', [$defaultController, 'createConsignmentRequest']);
        Route::get('/all', [$defaultController, 'getAllConsignmentRequests'])->middleware(['pagination']);
    }
);

Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::post('/{order_id}/payment', [$defaultController, 'payPendingOrderByOnlineMethod']);
    }
);


Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::get('/all', [$defaultController, 'getAllProducts'])->middleware(['pagination']);
        Route::get('/{product_id}/details', [$defaultController, 'getProductDetails']);
    }
);
