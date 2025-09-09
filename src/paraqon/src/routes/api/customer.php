<?php

// Default Imports

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AccountController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuctionController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuctionLotController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuctionRegistrationRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuctionRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuthController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\BidController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\ConsignmentRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\DepositController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\DocumentController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\PaymentController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\ProductController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\ServiceController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\WatchlistItemController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\NotificationController;

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

        Route::post('/cart', [$defaultController, 'cart']);
        Route::get('/health-check', [$defaultController, 'healthCheck']);
        Route::get('/callback', [$defaultController, 'callbackTest']);
    }
);

Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/customer', [$defaultController, 'getCustomerInfo']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'auctions'],
    function () {
        $defaultController = AuctionController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllAuctions'])->middleware(['pagination']);
                Route::get('/{auction_id}/paddles/all', [$defaultController, 'getAllPaddles'])->middleware(['pagination']);
                Route::get('/{auction_id}/details', [$defaultController, 'getAuctionDetails']);
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
        Route::get('/{auction_lot_id}/bids/all', [$defaultController, 'getAllAuctionLotBids'])->middleware(['pagination']);

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{auction_lot_id}/details', [$defaultController, 'getAuctionLotDetails']);

                Route::get('/owned/all', [$defaultController, 'getAllOwnedAuctionLots'])->middleware(['pagination']);
                Route::get('/participated/all', [$defaultController, 'getAllParticipatedAuctionLots'])->middleware(['pagination']);
                Route::post('/{auction_lot_id}/bids', [$defaultController, 'createMaximumBid']);
                Route::put('/{auction_lot_id}/bid-requests', [$defaultController, 'requestForBidPermissions']);

                Route::post('/{auction_lot_id}/live-bids', [$defaultController, 'createLiveBid']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::post('/login', [$defaultController, 'login']);
        Route::post('/2fa-login', [$defaultController, 'twoFactorAuthenticationlogin']);

        Route::post('/change-phone', [$defaultController, 'changePhone']);

        Route::post('/forget-password', [$defaultController, 'forgetPassword']);
        Route::post('/reset-password', [$defaultController, 'resetPassword']);

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/update-password', [$defaultController, 'updatePassword']);
                Route::post('/migrate', [$defaultController, 'migrateToRegistered']);

                Route::get('/change-email-request', [$defaultController, 'changeEmailRequest']);
                Route::get('/change-phone-request', [$defaultController, 'changePhoneRequest']);

                Route::get('/user', [$defaultController, 'getAuthUserInfo']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'accounts'],
    function () {
        $defaultController = AccountController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::put('/verification', [$defaultController, 'updateAccountVerification']);
                Route::get('/customer-groups', [$defaultController, 'getAllCustomerGroups'])->middleware(['pagination']);
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
                Route::get('/all', [$defaultController, 'getAllBids']);
                Route::put('/{id}/cancel', [$defaultController, 'cancelBid']);
                Route::put('/{id}/cancel-live', [$defaultController, 'cancelLiveBid']);
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
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOwnedProducts'])->middleware(['pagination']);
                Route::get('/{product_id}/details', [$defaultController, 'getProductDetails']);
                Route::put('/listing-status', [$defaultController, 'updateListingStatuses']);
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
                    Route::get('/products/filter/v2', [$defaultController, 'filterAuctionProductsByCategoriesV2'])->middleware(['pagination']);
                    Route::get('/related-products-urls', [$defaultController, 'getRelatedAuctionProductsUrls'])->middleware(['pagination']);
                    Route::get('/products/ids', [$defaultController, 'getAuctionProductsByIDs'])->name('paraqon.products.ids')->middleware(['pagination']);
                    Route::get('/products/{product_id}/details', [$defaultController, 'getProductDetails']);
                });
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
                    Route::post('/auction/checkout', [$defaultController, 'checkoutAuctionStore']);
                    Route::post('/main-store/all', [$defaultController, 'getAllMainStoreCartItems']);
                    Route::post('/main-store/checkout', [$defaultController, 'checkOutMainStore']);
                    Route::post('/checkout', [$defaultController, 'checkOut']);
                });
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
                Route::get('/stores/{store_id}/all', [$defaultController, 'getOrdersByStoreID'])->middleware(['pagination']);
                Route::get('/all/offline', [$defaultController, 'getAllOfflineOrders'])->middleware(['pagination']);
                Route::put('/{order_id}/upload', [$defaultController, 'uploadPaymentProofAsCustomer']);
                Route::post('/{order_id}/payment', [$defaultController, 'payPendingOrderByOnlineMethod']);
                Route::put('/{order_id}/cancel', [$defaultController, 'cancelOrderPayment']);
                Route::put('/{order_id}/details', [$defaultController, 'updateOrderDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'payments'],
    function () {
        $defaultController = PaymentController::class;

        Route::post('/callback', [$defaultController, 'onlinePaymentCallback']);
    }
);

Route::group(
    ['prefix' => 'watchlist'],
    function () {
        $defaultController = WatchlistItemController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/add-to-watchlist', [$defaultController, 'addAndRemoveItem']);
                Route::get('/stores', [$defaultController, 'getWatchedStores'])->middleware(['pagination']);
                Route::get('/auction-lots', [$defaultController, 'getWatchedAuctionLots'])->middleware(['pagination']);
                Route::get('/compare', [$defaultController, 'getCompareItems'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'auction-registrations'],
    function () {
        $defaultController = AuctionRegistrationRequestController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/register', [$defaultController, 'registerAuction']);
                Route::get('/all', [$defaultController, 'getAllRegisteredAuctions'])->middleware(['pagination']);
                Route::post('/{auction_registration_request_id}/register', [$defaultController, 'registerAuctionAgain']);
                Route::post('/{id}/deposit', [$defaultController, 'createDeposit']);
                Route::put('/{auction_registration_request_id}/archive', [$defaultController, 'archiveAuctionRegistrationRequest']);
                Route::get('/details', [$defaultController, 'getRegisteredAuctionDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'deposits'],
    function () {
        $defaultController = DepositController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllDeposits'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getDepositDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateDepositDetails']);
                Route::put('/{id}/cancel', [$defaultController, 'cancelDeposit']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'documents'],
    function () {
        $defaultController = DocumentController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/', [$defaultController, 'createDocument']);
                Route::get('/all', [$defaultController, 'getAllDocuments'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getDocumentDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateDocumentDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'notifications'],
    function () {
        $defaultController = NotificationController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllNotifications'])->middleware(['pagination']);
                Route::put('/read', [$defaultController, 'markNotificationsAsRead']);
                Route::put('/{id}/delete', [$defaultController, 'deleteNotification']);
            }
        );
    }
);


Route::group(
    ['prefix' => 'services'],
    function () {
        $defaultController = ServiceController::class;

        Route::get('/time/now', [$defaultController, 'checkCurrentTime']);
        Route::get('/timezone/now', [$defaultController, 'checkOtherTimeZone']);
    }
);
