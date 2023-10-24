<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\DevelopmentController;
use StarsNet\Project\Easeca\App\Http\Controllers\Admin\GeneralDeliveryScheduleController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

Route::group(
    ['prefix' => '/general-delivery-schedule'],
    function () {
        $defaultController = GeneralDeliveryScheduleController::class;

        Route::get('/details', [$defaultController, 'getGeneralDeliverySchedule']);
        Route::put('/details', [$defaultController, 'updateGeneralDeliverySchedule']);
    }
);

Route::group(
    ['prefix' => '/order-cut-off-schedule'],
    function () {
        $defaultController = GeneralDeliveryScheduleController::class;

        Route::get('/all', [$defaultController, 'getAllOrderCutOffSchedule']);
        Route::put('/details', [$defaultController, 'updateOrderCutOffSchedule']);
        Route::put('/all', [$defaultController, 'updateOrderCutOffSchedules']);
    }
);
