<?php

// Default Imports

use Illuminate\Support\Facades\Route;

// Controllers
use Starsnet\Project\App\Http\Controllers\Admin\DevelopmentController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);
