<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Imported Controllers
use StarsNet\Project\App\Http\Controllers\Internal\OrderController;

class InternalProjectRouteName
{
    const ORDER = 'orders';
}

// ORDER
Route::group(
    ['prefix' => InternalProjectRouteName::ORDER],
    function () {
        $defaultController = OrderController::class;

        Route::put('/cart-items/status', [$defaultController, 'updateOrderCartItemStatus']);

        Route::put('/cart-items/{id}/refund', [$defaultController, 'refundCartItem']);
    }
);
