<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\CasaModernism\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\CasaModernism\App\Http\Controllers\Admin\CustomerController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// CUSTOMER
Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{id}/trade-registration', [$defaultController, 'getTradeRegistration']);
                Route::put('/{id}/trade-registration', [$defaultController, 'approveTradeRegistration']);
            }
        );
    }
);
