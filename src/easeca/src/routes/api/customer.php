<?php

// Default Imports
use Illuminate\Support\Facades\Route;

// Controllers
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\DevelopmentController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\AuthenticationController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\OfflineStoreManagementController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\ShoppingCartController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\CheckoutController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\OrderController;
use StarsNet\Project\Easeca\App\Http\Controllers\Customer\ScheduleController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

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

                Route::get('/user', [$defaultController, 'getAuthUserInfo']);

                Route::get('/verification-code', [$defaultController, 'getVerificationCode']);

                Route::put('/delete', [$defaultController, 'deleteAccount']);
            }
        );
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

        // PRODUCT_MANAGEMENT
        Route::group(
            ['prefix' => 'product-management'],
            function () {
                $defaultController = ProductManagementController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::get('/categories/all', [$defaultController, 'getAllProductCategories'])->middleware(['pagination']);
                    Route::get('/categories/all/hierachy', [$defaultController, 'getAllProductCategoryHierarchy'])->middleware(['pagination']);
                    Route::get('/products/filter', [$defaultController, 'filterProductsByCategories'])->middleware(['pagination']);
                    Route::get('/products/{product_id}/details', [$defaultController, 'getProductDetails']);
                    Route::get('/products/{product_id}/reviews', [$defaultController, 'getProductReviews'])->middleware(['pagination']);

                    Route::get('/related-products-urls', [$defaultController, 'getRelatedProductsUrls'])->middleware(['pagination']);
                    Route::get('/products/ids', [$defaultController, 'getProductsByIDs'])->name('easeca.products.ids')->middleware(['pagination']);
                });
            }
        );

        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                Route::get('/related-products-urls', [$defaultController, 'getRelatedProductsUrls'])->middleware(['pagination']);

                Route::post('/add-to-cart', [$defaultController, 'addToCart']);

                Route::post('/all', [$defaultController, 'getAll']);

                Route::delete('/clear-cart', [$defaultController, 'clearCartByAccount']);
            });
        });

        // CHECKOUT
        Route::group(
            ['prefix' => 'checkouts'],
            function () {
                $defaultController = CheckoutController::class;

                Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                    Route::post('/', [$defaultController, 'checkOut']);
                });
            }
        );
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::get('/all/offline', [$defaultController, 'getAllOfflineOrders'])->middleware(['pagination']);
        });
    }
);

// SCHEDULE
Route::group(
    ['prefix' => 'schedules'],
    function () {
        $defaultController = ScheduleController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/', [$defaultController, 'getSchedule']);
            }
        );
    }
);
