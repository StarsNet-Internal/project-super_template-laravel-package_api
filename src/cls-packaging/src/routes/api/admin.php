<?php

use Illuminate\Support\Facades\Route;

use StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin\ShoppingCartController;
use StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin\OrderManagementController;
use StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin\ProductController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// PRODUCT
Route::group(
    ['prefix' => '/products'],
    function () {
        $defaultController = ProductController::class;

        Route::get('/{id}/variants', [$defaultController, 'getProductVariantsByProductID'])->middleware(['pagination']);
    }
);

// SHOPPING_CART
Route::group(
    ['prefix' => '/stores/{store_id}/shopping-cart'],
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
    ['prefix' => '/orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::get('/{id}/details', [$defaultController, 'getOrderDetails']);
    }
);
