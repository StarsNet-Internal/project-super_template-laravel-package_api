<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AccountController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\ConsignmentRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\ServiceController;

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
    ['prefix' => 'accounts'],
    function () {
        $defaultController = AccountController::class;
        Route::put('/{account_id}/verification', [$defaultController, 'updateAccountVerification']);
        // Route::group(
        //     ['middleware' => 'auth:api'],
        //     function () use ($defaultController) {
        //         Route::put('/{account_id}/verification', [$defaultController, 'updateAccountVerification']);
        //     }
        // );
    }
);

Route::group(
    ['prefix' => 'auctions'],
    function () {
        $defaultController = AuctionController::class;
        Route::put('/statuses', [$defaultController, 'updateAuctionStatuses']);
        Route::get('/{store_id}/archive', [$defaultController, 'archiveAllAuctionLots']);
        Route::get('/{store_id}/orders/create', [$defaultController, 'generateAuctionOrders']);


        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{store_id}/auction-lots/unpaid', [$defaultController, 'getAllUnpaidAuctionLots'])->middleware(['pagination']);
                Route::put('/{store_id}/auction-lots/return', [$defaultController, 'returnAuctionLotToOriginalCustomer']);
            }
        );
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
                Route::put('/{id}/edit', [$defaultController, 'updateAuctionRequests']);
                Route::put('/{id}/approve', [$defaultController, 'approveAuctionRequest']);
                Route::put('/{id}/auction-lots/edit', [$defaultController, 'updateAuctionLotDetailsByAuctionRequest']);
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
                Route::put('/{customer_id}/bids/{bid_id}/hide', [$defaultController, 'hideBid']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'services'],
    function () {
        $defaultController = ServiceController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::put('/stores/archive', [$defaultController, 'archiveStores']);
            }
        );
    }
);
