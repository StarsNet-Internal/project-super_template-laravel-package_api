<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Imported Controllers
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Internal\OrderController;

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::get('/{id}/number', [$defaultController, 'getOrderNumber']);
    }
);
