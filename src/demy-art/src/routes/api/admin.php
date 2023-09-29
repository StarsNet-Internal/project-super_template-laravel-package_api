<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\DemyArt\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\DemyArt\App\Http\Controllers\Admin\CalendarController;

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
            Route::post('/{google_event_id}/details', [$defaultController, 'createOrUpdateCalendarEvent']);
            Route::get('/{google_event_id}/details', [$defaultController, 'getEventDetails']);
        });
    }
);
