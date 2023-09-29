<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\CateringMan\App\Http\Controllers\Customer\DevelopmentController;
use StarsNet\Project\CateringMan\App\Http\Controllers\Customer\FakerController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

Route::group(
    ['prefix' => '/faker'],
    function () {
        $defaultController = FakerController::class;

        Route::get('/membershipPointsAndCoin', [$defaultController, 'membershipPointsAndCoin']);
        Route::get('/totalCreditedAndWithdrawal', [$defaultController, 'totalCreditedAndWithdrawal']);
        Route::get('/lineGraph', [$defaultController, 'lineGraph']);
    }
);
