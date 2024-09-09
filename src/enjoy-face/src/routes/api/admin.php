<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\StaffManagementController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\ProductController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\OfflineStoreManagementController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\OrderManagementController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin\PostController;

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

// CUSTOMER
Route::group(
    ['prefix' => 'customers'],
    function () {
        $defaultController = CustomerController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::put('/delete', [$defaultController, 'deleteCustomers']);

                Route::post('/membership/distribute', [$defaultController, 'distributeMembershipPoint']);
            }
        );
    }
);

// STAFF
Route::group(
    ['prefix' => 'staff'],
    function () {
        $defaultController = StaffManagementController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::put('/merchants/{id}/details', [$defaultController, 'updateMerchantDetails']);
        });
    }
);

// POST
Route::group(
    ['prefix' => 'posts'],
    function () {
        $defaultController = PostController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/', [$defaultController, 'createPost']);

                Route::put('/{id}/details', [$defaultController, 'updatePostDetails']);

                Route::post('/reviews/{review_id}/reply', [$defaultController, 'replyPostReview']);

                Route::put('/reviews/status', [$defaultController, 'updatePostReviewReplyStatus']);
            }
        );
    }
);

// PRODUCT
Route::group(
    ['prefix' => 'products'],
    function () {
        $defaultController = ProductController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllProducts'])->middleware(['pagination']);

                Route::put('/reviews/status', [$defaultController, 'updateReviewStatus']);
            }
        );
    }
);

// OFFLINE_STORE
Route::group(
    ['prefix' => '/stores'],
    function () {
        $defaultController = OfflineStoreManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/offline/all', [$defaultController, 'getAllOfflineStores'])->middleware(['pagination']);

                Route::get('/categories/all', [$defaultController, 'getAllStoreCategories'])->middleware(['pagination']);
                Route::post('/categories', [$defaultController, 'createStoreCategory']);

                Route::put('/categories/{category_id}/stores/assign', [$defaultController, 'assignStoresToCategory']);
                Route::put('/categories/{category_id}/stores/unassign', [$defaultController, 'unassignStoresFromCategory']);
            }
        );
    }
);

// ORDER
Route::group(
    ['prefix' => 'orders'],
    function () {
        $defaultController = OrderManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllOrdersByStore'])->middleware(['pagination']);

                Route::get('/{id}/details', [$defaultController, 'getOrderDetails']);

                Route::put('/{id}/address', [$defaultController, 'updateDeliveryAddress']);

                Route::put('/{id}/cancel', [$defaultController, 'cancelOrder']);
                Route::put('/cancel-all', [$defaultController, 'cancelAllIdleOrders']);
            }
        );
    }
);
