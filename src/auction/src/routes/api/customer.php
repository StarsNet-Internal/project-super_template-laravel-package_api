<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Auction\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\CreditCardController;
use StarsNet\Project\Auction\App\Http\Controllers\Customer\AuctionRegistrationRequestController;


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
