<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Imported Controllers
use StarsNet\Project\Capi\App\Http\Controllers\Internal\OrderController;

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::put('/cart-items/status', [$defaultController, 'updateOrderCartItemStatus']);

        Route::put('/cart-items/{id}/refund', [$defaultController, 'refundCartItem']);
    }
);
