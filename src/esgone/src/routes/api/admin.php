<?php

// Default Imports
use Illuminate\Support\Facades\Route;
use StarsNet\Project\Esgone\App\Http\Controllers\Admin\TestingController;
use StarsNet\Project\Esgone\App\Http\Controllers\Admin\AuthenticationController;
use StarsNet\Project\Esgone\App\Http\Controllers\Admin\CustomerController;
use StarsNet\Project\Esgone\App\Http\Controllers\Admin\CustomerGroupController;

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

                Route::get('/user', [$defaultController, 'getAuthUserInfo']);

                Route::get('/verification-code', [$defaultController, 'getVerificationCode']);
            }
        );
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
                Route::get('/all', [$defaultController, 'getAllCustomers'])->middleware(['pagination']);
                Route::post('/', [$defaultController, 'createCustomer']);

                Route::get('/{id}/details', [$defaultController, 'getCustomerDetails']);
                Route::put('/{id}/details', [$defaultController, 'updateCustomerDetails']);
            }
        );
    }
);

// CUSTOMER_GROUP
Route::group(
    ['prefix' => 'customer-groups'],
    function () {
        $defaultController = CustomerGroupController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/{id}/customers/assign', [$defaultController, 'getCustomerGroupAssignedCustomers'])->middleware(['pagination']);
                Route::get('/{id}/customers/unassign', [$defaultController, 'getCustomerGroupUnassignedCustomers'])->middleware(['pagination']);
            }
        );
    }
);
