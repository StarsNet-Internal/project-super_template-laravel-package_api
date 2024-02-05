<?php

// Default Imports
use Illuminate\Support\Facades\Route;
// use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\AuctionController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\AuctionRequestController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\ConsignmentRequestController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin\CustomerController;

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

// Route::group(
//     ['prefix' => 'stores'],
//     function () {
//         $defaultController = AuctionController::class;

//         Route::post('/', [$defaultController, 'createAuctionStore']);
//     }
// );

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

Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{customer_id}/products/all', [$defaultController, 'getAllOwnedProducts'])->middleware(['pagination']);
                Route::get('/{customer_id}/auction-lots/all', [$defaultController, 'getAllOwnedAuctionLots'])->middleware(['pagination']);
                Route::get('/{customer_id}/bids/all', [$defaultController, 'getAllBids'])->middleware(['pagination']);
            }
        );
    }
);
