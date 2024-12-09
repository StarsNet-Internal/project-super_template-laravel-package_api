<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AccountController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionLotController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionRegistrationRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\ConsignmentRequestController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\CustomerGroupController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\DepositController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\DocumentController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\OrderController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\SeederController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\ServiceController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\ShoppingCartController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\LiveBiddingEventController;

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
        Route::get('/order', [$defaultController, 'createOrder']);
    }
);

Route::group(
    ['prefix' => 'seeder'],
    function () {
        $defaultController = SeederController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
        Route::get('/from-store-to-orders', [$defaultController, 'fromStoreToOrders']);
        Route::get('/stores/{store_id}/generate-orders', [$defaultController, 'generateAuctionOrders']);
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

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::put('/{id}/verification', [$defaultController, 'updateAccountVerification']);
                Route::put('/{id}/details', [$defaultController, 'updateAccountDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'auctions'],
    function () {
        $defaultController = AuctionController::class;

        Route::put('/statuses', [$defaultController, 'updateAuctionStatuses']);
        Route::get('/{store_id}/archive', [$defaultController, 'archiveAllAuctionLots']);
        Route::get('/{store_id}/orders/create', [$defaultController, 'generateAuctionOrders']);

        Route::get('/{store_id}/registered-users', [$defaultController, 'getAllRegisteredUsers'])->middleware(['pagination']);
        Route::put('/{store_id}/registered-users/{customer_id}/remove', [$defaultController, 'removeRegisteredUser']);
        Route::put('/{store_id}/registered-users/{customer_id}/add', [$defaultController, 'addRegisteredUser']);

        Route::get('/{store_id}/categories/all', [$defaultController, 'getAllCategories'])->middleware(['pagination']);
        Route::get('/{store_id}/registration-records', [$defaultController, 'getAllAuctionRegistrationRecords'])->middleware(['pagination']);
        Route::put('/products/{product_id}/categories/assign', [$defaultController, 'syncCategoriesToProduct']);
        Route::get('/all', [$defaultController, 'getAllAuctions'])->middleware(['pagination']);

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
    ['prefix' => 'auction-lots'],
    function () {
        $defaultController = AuctionLotController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/', [$defaultController, 'createAuctionLot']);
                Route::get('/all', [$defaultController, 'getAllAuctionLots'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getAuctionLotDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateAuctionLotDetails']);
                Route::put('/delete', [$defaultController, 'deleteAuctionLots']);
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
                // Route::post('/{id}/deposit', [$defaultController, 'createDeposit']);
                Route::put('/{auction_registration_request_id}/archive', [$defaultController, 'archiveAuctionRegistrationRequest']);
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
                Route::post('/', [$defaultController, 'createConsignmentRequest']);
                Route::get('/{id}/details', [$defaultController, 'getConsignmentRequestDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateConsignmentRequestDetails']);
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
                Route::get('/all', [$defaultController, 'getAllCustomers'])->middleware(['pagination']);
                Route::get('/{id}/details', [$defaultController, 'getCustomerDetails']);
                Route::get('/{customer_id}/products/all', [$defaultController, 'getAllOwnedProducts'])->middleware(['pagination']);
                Route::get('/{customer_id}/auction-lots/all', [$defaultController, 'getAllOwnedAuctionLots'])->middleware(['pagination']);
                Route::get('/{customer_id}/bids/all', [$defaultController, 'getAllBids'])->middleware(['pagination']);
                Route::put('/{customer_id}/bids/{bid_id}/hide', [$defaultController, 'hideBid']);
            }
        );
    }
);

// CUSTOMER_GROUP
Route::group(
    ['prefix' => 'customer-groups'],
    function () {
        $defaultController = CustomerGroupController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{id}/customers/assign', [$defaultController, 'getCustomerGroupAssignedCustomers'])->middleware(['pagination']);
                Route::get('/{id}/customers/unassign', [$defaultController, 'getCustomerGroupUnassignedCustomers'])->middleware(['pagination']);
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
    ['prefix' => 'services'],
    function () {
        $defaultController = ServiceController::class;

        Route::put('/auctions/statuses', [$defaultController, 'updateAuctionStatuses']);
        Route::put('/auction-lots/statuses', [$defaultController, 'updateAuctionLotStatuses']);
        Route::put('/auction-lots/{auction_lot_id}/extend', [$defaultController, 'extendAuctionLotEndDateTime']);

        Route::post('/payment/callback', [$defaultController, 'paymentCallback']);
        Route::get('/auctions/{store_id}/orders/create', [$defaultController, 'generateAuctionOrdersAndRefundDeposits']);

        Route::post('/deposits/return', [$defaultController, 'returnDeposit']);
        Route::post('/orders/paid', [$defaultController, 'confirmOrderPaid']);

        Route::get('/auctions/{store_id}/state', [$defaultController, 'getAuctionCurrentState']);

        // Route::group(
        //     ['middleware' => 'auth:api'],
        //     function () use ($defaultController) {}
        // );
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
                Route::put('/{id}/approve', [$defaultController, 'approveDeposit']);
                Route::put('/{id}/cancel', [$defaultController, 'cancelDeposit']);
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
                Route::get('/all', [$defaultController, 'getAllAuctionOrders'])->middleware(['pagination']);
                Route::put('/{order_id}/details', [$defaultController, 'updateOrderDetails']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'shopping-cart'],
    function () {
        $defaultController = ShoppingCartController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getShoppingCartItems'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'live-bidding'],
    function () {
        $defaultController = LiveBiddingEventController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/{store_id}/events', [$defaultController, 'createEvent']);
            }
        );
    }
);
