<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Auction\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\ConsignmentRequestController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\CreditCardController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\AuctionRegistrationRequestController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\SiteMapController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\ReferralCodeController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\AuthenticationController;

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


Route::group(
    ['prefix' => 'auth'],
    function () {
        Route::group(
            ['middleware' => 'auth:api'],
            function () {
                Route::post('/migrate', [AuthenticationController::class, 'migrateToRegistered']);
            }
        );
    }
);


Route::group(
    ['prefix' => 'auction-registration-requests'],
    function () {
        $defaultController = AuctionRegistrationRequestController::class;

        Route::put('/{auction_registration_request_id}/details', [$defaultController, 'updateAuctionRegistrationRequest'])->middleware(['auth:api']);
    }
);


Route::group(
    ['prefix' => 'credit-cards'],
    function () {
        $defaultController = CreditCardController::class;

        Route::post('/bind', [$defaultController, 'bindCard'])->middleware(['auth:api']);
        Route::get('/validate', [$defaultController, 'validateCard'])->middleware(['auth:api']);
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
            }
        );
    }
);

Route::group(
    ['prefix' => 'referral-codes'],
    function () {
        Route::group(
            ['middleware' => 'auth:api'],
            function () {
                Route::get('/details', [ReferralCodeController::class, 'getReferralCodeDetails']);
                Route::get('/histories/all', [ReferralCodeController::class, 'getAllReferralCodeHistories'])->middleware(['pagination']);
            }
        );
    }
);

Route::group(
    ['prefix' => 'sitemap'],
    function () {
        $defaultController = SiteMapController::class;

        Route::get('/auctions/all', [$defaultController, 'getAllAuctions'])->middleware(['pagination']);
        Route::get('/auctions/{store_id}/products/all', [$defaultController, 'filterAuctionProductsByCategories'])->middleware(['pagination']);
        Route::get('/auction-lots/{auction_lot_id}/details', [$defaultController, 'getAuctionLotDetails']);
    }
);
