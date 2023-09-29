<?php

use Illuminate\Support\Facades\Route;

use StarsNet\Project\ClsPackaging\App\Http\Controllers\Command\DevelopmentController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);
