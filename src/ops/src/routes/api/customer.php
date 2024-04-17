<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\Ops\App\Http\Controllers\Customer\DevelopmentController;
use StarsNet\Project\Ops\App\Http\Controllers\Customer\TemplateController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// TEMPLATE
Route::group(
    ['prefix' => 'templates'],
    function () {
        $defaultController = TemplateController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllTemplates'])->middleware(['pagination']);
                Route::put('/delete', [$defaultController, 'deleteTemplates']);
                Route::post('/', [$defaultController, 'createTemplate']);

                Route::get('/{id}/details', [$defaultController, 'getTemplateDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateTemplateDetails']);

                Route::get('/admin', [$defaultController, 'getAllAdminTemplates'])->middleware(['pagination']);
            }
        );
    }
);
