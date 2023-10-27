<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\DevelopmentController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\OfflineStoreManagementController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {

        // STORE
        Route::group(
            ['prefix' => 'stores'],
            function () {
                $defaultController = OfflineStoreManagementController::class;

                Route::get('/offline/all', [$defaultController, 'getAllOfflineStores'])->middleware(['pagination']);
            }
        );
    }
);
