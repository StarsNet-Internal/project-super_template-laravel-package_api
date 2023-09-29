<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\CateringMan\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\CateringMan\App\Http\Controllers\Admin\ShoppingCartController;
use StarsNet\Project\CateringMan\App\Http\Controllers\Admin\OrderManagementController;
use StarsNet\Project\CateringMan\App\Http\Controllers\Admin\ProductController;

class AdminProjectRouteName
{
    // Product section
    const PRODUCT = 'products';

    // Shopping-cart section
    const SHOPPING_CART = 'shopping-cart';

    // Order section
    const ORDER = 'orders';
}

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// PRODUCT
Route::group(
    ['prefix' => AdminProjectRouteName::PRODUCT],
    function () {
        $defaultController = ProductController::class;

        Route::get('/{id}/variants', [$defaultController, 'getProductVariantsByProductID'])->middleware(['pagination']);
        // Route::group(
        //     ['middleware' => 'auth:api'],
        //     function () use ($defaultController) {
        //         Route::get('/{id}/variants', [$defaultController, function () {
        //             return 'hhi';
        //         }])->middleware(['pagination']);
        //     }
        // );
    }
);

// SHOPPING_CART
Route::group(
    ['prefix' => '/stores/{store_id}/' . AdminProjectRouteName::SHOPPING_CART],
    function () {
        $defaultController = ShoppingCartController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/add-to-cart', [$defaultController, 'addToCartByCategory']);
            Route::post('/checkout', [$defaultController, 'checkOut']);

            Route::post('/all', [$defaultController, 'getAll']);

            Route::delete('/clear-cart', [$defaultController, 'clearCart']);
        });
    }
);

// ORDER
Route::group(
    ['prefix' => AdminProjectRouteName::ORDER],
    function () {
        $defaultController = OrderManagementController::class;

        // Route::group(
        //     ['middleware' => 'auth:api'],
        //     function () use ($defaultController) {
        Route::get('/{id}/details', [$defaultController, 'getOrderDetails']);
        //     }
        // );
    }
);
