<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\AuctionController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\AuctionLotController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\AuctionRequestController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\BidController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\ConsignmentRequestController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\ProductController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer\WishlistController;
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
        Route::get('/{auction_id}/details', [$defaultController, 'getAuctionDetails']);
    }
);

Route::group(
    ['prefix' => 'auction-requests'],
    function () {
        $defaultController = AuctionRequestController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/', [$defaultController, 'createAuctionRequest']);
                Route::get('/all', [$defaultController, 'getAllAuctionRequests'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'auction-lots'],
    function () {
        $defaultController = AuctionLotController::class;

        Route::get('/{auction_lot_id}/bids', [$defaultController, 'getBiddingHistory'])->middleware(['pagination']);

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{auction_lot_id}/details', [$defaultController, 'getAuctionLotDetails']);
                Route::get('/owned/all', [$defaultController, 'getAllOwnedAuctionLots'])->middleware(['pagination']);
                Route::get('/participated/all', [$defaultController, 'getAllParticipatedAuctionLots'])->middleware(['pagination']);
                Route::post('/{auction_lot_id}/bids', [$defaultController, 'createMaximumBid']);
            }
        );
    }
);


Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/migrate', [$defaultController, 'migrateToRegistered']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'bids'],
    function () {
        $defaultController = BidController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                // Route::post('/', [$defaultController, 'createBid']);
                Route::get('/all', [$defaultController, 'getAllBids'])->middleware(['pagination']);
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
                Route::post('/', [$defaultController, 'createConsignmentRequest']);
                Route::get('/all', [$defaultController, 'getAllConsignmentRequests'])->middleware(['pagination']);
                Route::get('/{consignment_request_id}/details', [$defaultController, 'getConsignmentRequestDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/{order_id}/payment', [$defaultController, 'payPendingOrderByOnlineMethod']);
            }
        );
    }
);


Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOwnedProducts'])->middleware(['pagination']);
                Route::get('/{product_id}/details', [$defaultController, 'getProductDetails']);
            }
        );
    }
);

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {

        // PRODUCT_MANAGEMENT
        Route::group(
            ['prefix' => 'product-management'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::get('/products/filter', [$defaultController, 'filterAuctionProductsByCategories'])->middleware(['pagination']);
                });
                Route::get('/related-products-urls', [$defaultController, 'getRelatedAuctionProductsUrls'])->middleware(['pagination']);
                Route::get('/products/ids', [$defaultController, 'getAuctionProductsByIDs'])->name('whiskywhiskers.products.ids')->middleware(['pagination']);
            }
        );

        // WISHLIST
        Route::group(
            ['prefix' => 'wishlist'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::get('/all', [$defaultController, 'getAllWishlistAuctionLots'])->middleware(['pagination']);
                });
            }
        );

        // Shopping Cart
        Route::group(
            ['prefix' => 'shopping-cart'],
            function () {
                $defaultController = ShoppingCartController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::post('/auction/all', [$defaultController, 'getAllAuctionCartItems']);
                    Route::post('/auction/checkout', [$defaultController, 'getAllAuctionCartItems']);
                });
            }
        );
    }
);
