<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\App\Http\Controllers\Customer\ProfileController;
use StarsNet\Project\App\Http\Controllers\Customer\DealManagementController;
use StarsNet\Project\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\App\Http\Controllers\Customer\PaymentController;
use StarsNet\Project\App\Http\Controllers\Customer\LinkController;

class CustomerProjectRouteName
{
    const PROFILE = 'profiles';

    const DEAL_MANAGEMENT = 'product-management';
    const SHOPPING_CART = 'shopping-cart';
    const ORDER = 'orders';

    const CHECKOUT = 'checkouts';

    const PAYMENT = 'payments';

    const LINK = 'links';
}

Route::group(
    ['prefix' => 'tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// PROFILE
Route::group(
    ['prefix' => CustomerProjectRouteName::PROFILE],
    function () {
        $defaultController = ProfileController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/credit/status', [$defaultController, 'getCreditStatus']);
            Route::get('/credit/balance', [$defaultController, 'getCreditBalance'])->middleware(['pagination']);
            Route::get('/credit/transactions', [$defaultController, 'getCreditTransactions'])->middleware(['pagination']);
        });
    }
);

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {

        // DEAL_MANAGEMENT
        Route::group(
            ['prefix' => CustomerProjectRouteName::DEAL_MANAGEMENT],
            function () {
                $defaultController = DealManagementController::class;

                Route::get('/categories/all', [$defaultController, 'getAllDealCategories'])->middleware(['pagination']);
                Route::get('/categories/all/hierachy', [$defaultController, 'getAllDealCategoryHierarchy'])->middleware(['pagination']);
                Route::get('/products/filter', [$defaultController, 'filterDealsByCategories'])->middleware(['pagination']);
                Route::get('/products/{deal_id}/details', [$defaultController, 'getDealDetails']);
                Route::get('/products/{product_id}/reviews', [$defaultController, 'getDealReviews'])->middleware(['pagination']);

                Route::get('/related-products-urls', [$defaultController, 'getRelatedDealsUrls'])->middleware(['pagination']);
                Route::get('/products/ids', [$defaultController, 'getDealsByIDs'])->name('deals.ids')->middleware(['pagination']);

                Route::post('/deals/{deal_id}/groups', [$defaultController, 'createGroup']);

                Route::get('/time', [$defaultController, 'getCurrentServerTime']);
            }
        );

        // SHOPPING_CART
        Route::group(
            ['prefix' => CustomerProjectRouteName::SHOPPING_CART],
            function () {
                $defaultController = ShoppingCartController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::get('/related-products-urls', [$defaultController, 'getRelatedDealsUrls'])->middleware(['pagination']);

                    Route::post('/add-to-cart', [$defaultController, 'addToCartByDealGroup']);

                    Route::post('/all', [$defaultController, 'getAll']);

                    Route::delete('/clear-cart', [$defaultController, 'clearCart']);
                });
            }
        );

        // CHECKOUT
        Route::group(
            ['prefix' => CustomerProjectRouteName::CHECKOUT],
            function () {
                $defaultController = CheckoutController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::post('/', [$defaultController, 'checkOut']);
                });
            }
        );
    }
);

// ORDER
Route::group(
    ['prefix' => CustomerProjectRouteName::ORDER],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/all', [$defaultController, 'getAll'])->middleware(['pagination']);

            Route::get('/{order_id}/details', [$defaultController, 'getOrderAndDealDetailsAsCustomer']);
        });
    }
);

// PAYMENT
Route::group(
    ['prefix' => CustomerProjectRouteName::PAYMENT],
    function () {
        $defaultController = PaymentController::class;

        Route::post('/callback', [$defaultController, 'onlinePaymentCallback']);
    }
);

// LINK
Route::group(
    ['prefix' => CustomerProjectRouteName::LINK],
    function () {
        $defaultController = LinkController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/all', [$defaultController, 'getAllLinks'])->middleware(['pagination']);
            Route::get('/{link_id}/details', [$defaultController, 'getDetails']);

            Route::post('/', [$defaultController, 'createLinks']);
        });
    }
);
