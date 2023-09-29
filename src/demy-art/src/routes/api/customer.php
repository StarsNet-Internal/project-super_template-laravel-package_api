<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\DemyArt\App\Http\Controllers\Customer\DevelopmentController;
use StarsNet\Project\DemyArt\App\Http\Controllers\Customer\CalendarController;
use StarsNet\Project\DemyArt\App\Http\Controllers\Customer\OrderController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

Route::group(
    ['prefix' => '/calendar'],
    function () {
        $defaultController = CalendarController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/{google_event_id}/details', [$defaultController, 'getEventDetails']);
            Route::get('/filter', [$defaultController, 'getEventList']);
        });
    }
);

Route::group(
    ['prefix' => '/orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/{order_id}/reviews', [$defaultController, 'createOrderReview']);
            Route::get('/{order_id}/reviews', [$defaultController, 'getOrderReviews'])->middleware(['pagination']);
        });
    }
);
