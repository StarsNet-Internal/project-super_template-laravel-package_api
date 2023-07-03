<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\App\Http\Controllers\Customer\ProfileController;
use StarsNet\Project\App\Http\Controllers\Customer\DealManagementController;
use StarsNet\Project\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\App\Http\Controllers\Customer\LinkController;

class CustomerProjectRouteName
{
    const PROFILE = 'profiles';

    const DEAL_MANAGEMENT = 'deal-management';
    const SHOPPING_CART = 'shopping-cart';
    const ORDER = 'orders';

    const CHECKOUT = 'checkouts';

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
                Route::get('/categories/all/hierarchy', [$defaultController, 'getAllDealCategoryHierarchy'])->middleware(['pagination']);
                Route::get('/deals/filter', [$defaultController, 'filterDealsByCategories'])->middleware(['pagination']);
                Route::get('/deals/{deal_id}/details', [$defaultController, 'getDealDetails']);

                Route::post('/deals/{deal_id}/groups', [$defaultController, 'createGroup']);
            }
        );

        // SHOPPING_CART
        Route::group(
            ['prefix' => CustomerProjectRouteName::SHOPPING_CART],
            function () {
                $defaultController = ShoppingCartController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
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
            Route::get('/{order_id}/details', [$defaultController, 'getDetails']);
        });
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
