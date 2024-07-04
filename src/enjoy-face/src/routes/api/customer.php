<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\OfflineStoreManagementController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\WishlistController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\ProfileController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer\PostController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(
    ['prefix' => 'tests'],
    function () {
        $defaultController = TestingController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// AUTH
Route::group(
    ['prefix' => 'auth'],
    function () {
        $defaultController = AuthenticationController::class;

        Route::post('/login', [$defaultController, 'login']);
        Route::post('/register', [$defaultController, 'register']);

        Route::post('/forget-password', [$defaultController, 'forgetPassword']);

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/logout', [$defaultController, 'logoutMobileDevice']);

                Route::get('/verification-code', [$defaultController, 'getVerificationCode']);
            }
        );
    }
);

// PROFILE
Route::group(
    ['prefix' => 'profiles'],
    function () {
        $defaultController = ProfileController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/membership/transfer', [$defaultController, 'transferMembershipPoint']);
        });
    }
);

// POST
Route::group(
    ['prefix' => 'posts'],
    function () {
        $defaultController = PostController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/{id}/reviews', [$defaultController, 'getPostReviews'])->middleware(['pagination']);

            Route::get('/liked/all', [$defaultController, 'getAllLikedPosts'])->middleware(['pagination']);
        });
    }
);

// OFFLINE_STORE
Route::group(
    ['prefix' => '/stores'],
    function () {
        $defaultController = OfflineStoreManagementController::class;

        Route::get('/categories/all', [$defaultController, 'getAllStoreCategories'])->middleware(['pagination']);
        Route::get('/filter', [$defaultController, 'filterStoresByCategories'])->middleware(['pagination']);
        Route::get('/{store_id}/details', [$defaultController, 'getStoreDetails']);
        Route::get('/{store_id}/products', [$defaultController, 'getStoreProducts'])->middleware(['pagination']);
    }
);

// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        // PRODUCT_MANAGEMENT
        Route::group(
            ['prefix' => 'product-management'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::get('/categories/all/hierachy', [$defaultController, 'getAllProductCategoryHierarchy'])->middleware(['pagination']);
            }
        );

        // WISHLIST
        Route::group(
            ['prefix' => 'wishlist'],
            function () {
                $defaultController = WishlistController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::get('/all', [$defaultController, 'getAll'])->middleware(['pagination']);
                    Route::post('/add-to-wishlist', [$defaultController, 'addAndRemove']);
                });
            }
        );

        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                Route::post('/all', [$defaultController, 'getAll']);
            });
        });
    }
);

// REVIEW
Route::group(
    ['prefix' => '/reviews'],
    function () {
        $defaultController = OfflineStoreManagementController::class;

        Route::get('/all', [$defaultController, 'getReviews'])->middleware(['pagination']);
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/{order_id}/details', [$defaultController, 'getOrderDetails']);
        });
    }
);
