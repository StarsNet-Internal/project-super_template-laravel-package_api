<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\SnoreCircle\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\SnoreCircle\App\Http\Controllers\Admin\OrderManagementController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOrdersByStore'])->middleware(['pagination']);

                Route::get('/{id}/details', [$defaultController, 'getOrderDetails']);
            }
        );
    }
);
