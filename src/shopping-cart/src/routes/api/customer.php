<?php

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\ShoppingCart\App\Http\Controllers\Customer\TestingController;
use StarsNet\Project\ShoppingCart\App\Http\Controllers\Customer\ShoppingCartController;

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

/*
|--------------------------------------------------------------------------
| Development Uses
|--------------------------------------------------------------------------
*/



Route::get('/checkout', function () {
    return 'tovi new route';
});

Route::group(
    ['prefix' => 'stores'],
    function () {
        $defaultController = ShoppingCartController::class;


        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/{store_id}/checkouts', [$defaultController, 'checkout']);
            }
        );
    }
);



// STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        // SHOPPING_CART
        Route::group(['prefix' => 'shopping-cart'], function () {
            $defaultController = ShoppingCartController::class;

            Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
                Route::post('/checkout', [$defaultController, 'checkOut']);
            });
        });
    }
);
