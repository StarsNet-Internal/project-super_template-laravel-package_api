<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\SunKwong\App\Http\Controllers\Customer\DevelopmentController;
use StarsNet\Project\SunKwong\App\Http\Controllers\Customer\PostController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// POST
Route::group(
    ['prefix' => 'posts'],
    function () {
        $defaultController = PostController::class;

        Route::get('/{id}/details', [$defaultController, 'getPostDetails']);

        Route::get('/filter', [$defaultController, 'filterPostsByCategories'])->middleware(['pagination']);

        Route::get('/related-posts-urls', [$defaultController, 'getRelatedPostsUrls'])->middleware(['pagination']);
        Route::get('/ids', [$defaultController, 'getPostsByIDs'])->name('sun-kwong.posts.ids')->middleware(['pagination']);
    }
);
