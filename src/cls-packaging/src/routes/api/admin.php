<?php

use Illuminate\Support\Facades\Route;

use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\AddressController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\DevelopmentController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\CustomerController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\OnlineStoreManagementController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\ProductController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\ShipmentController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\WarehouseController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\OrderManagementController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\ShoppingCartController;
use Starsnet\Project\ClsPackaging\App\Http\Controllers\Admin\QuotationItemController;

Route::group(
    ['prefix' => '/tests'],
    function () {
        $defaultController = DevelopmentController::class;

        Route::get('/health-check', [$defaultController, 'healthCheck']);
    }
);

// WAREHOUSE
Route::group(
    ['prefix' => 'warehouses'],
    function () {
        $defaultController = WarehouseController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllWarehousesByCustomerGroups'])->middleware(['pagination']);
                Route::post('/', [$defaultController, 'createWarehouseByCustomerGroup']);
                Route::post('/admin', [$defaultController, 'createCustomerGroupWarehouseByAdmin']);
                Route::get('/history', [$defaultController, 'getAllWarehouseInventoryHistoryByCustomerGroups'])->middleware(['pagination']);
                Route::get('/report', [$defaultController, 'getReportByCustomerGroups']);
                Route::post('/{warehouse_id}/product-variants/{product_variant_id}', [$defaultController, 'updateWarehouseInventoryAndCreateWarehouseInventoryHistory']);
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
                Route::get('/all', [$defaultController, 'getAllProductsByCustomerGroups'])->middleware(['pagination']);
                Route::get('/filter', [$defaultController, 'filterAllProducts'])->middleware(['pagination']);

                Route::post('/', [$defaultController, 'createProductByCustomerGroup']);
                Route::post('/admin', [$defaultController, 'createCustomerGroupProductByAdmin']);

                Route::get('/variants', [$defaultController, 'getAllProductVariantsByCustomerGroups'])->middleware(['pagination']);

                Route::get('/{id}/type', [$defaultController, 'getProductType']);
            }
        );
    }
);

// QUOTATION
Route::group(
    ['prefix' => 'quotations'],
    function () {
        $defaultController = QuotationItemController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::post('/', [$defaultController, 'createCustomerGroupQuotationItemByAdmin']);
            }
        );
    }
);

// ONLINE_STORE
Route::group(
    ['prefix' => '/stores/{store_id}/'],
    function () {
        $defaultController = OnlineStoreManagementController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/categories/all', [$defaultController, 'getAllProductCategoriesByCustomerGroups'])->middleware(['pagination']);
                Route::post('/categories', [$defaultController, 'createProductCategoryByCustomerGroup']);
                Route::post('/categories/admin', [$defaultController, 'createCustomerGroupProductCategoryByAdmin']);

                Route::get('/categories/{category_id}/products/unassign', [$defaultController, 'getCategoryUnassignedProductsByCustomerGroups'])->middleware(['pagination']);
            }
        );
    }
);

// SHOPPING_CART
Route::group(
    ['prefix' => '/stores/{store_id}/' . 'shopping-cart'],
    function () {
        $defaultController = ShoppingCartController::class;

        Route::group(['middleware' => 'auth:api'], function () use ($defaultController) {
            Route::post('/checkout', [$defaultController, 'checkOut']);
        });
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
                Route::get('/all', [$defaultController, 'getAllCustomerGroupOrdersByStore'])->middleware(['pagination']);

                Route::get('/{id}/type', [$defaultController, 'getOrderType']);
            }
        );
    }
);

// ADDRESS
Route::group(
    ['prefix' => 'addresses'],
    function () {
        $defaultController = AddressController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllAddressesByCustomerGroups'])->middleware(['pagination']);
                Route::post('/', [$defaultController, 'createAddressByCustomerGroup']);
                Route::post('/admin', [$defaultController, 'createCustomerGroupAddressByAdmin']);
            }
        );
    }
);

// SHIPMENT
Route::group(
    ['prefix' => 'shipments'],
    function () {
        $defaultController = ShipmentController::class;

        Route::group(
            ['middleware' => 'auth:api'],
            function () use ($defaultController) {
                Route::get('/all', [$defaultController, 'getAllShipmentsByCustomerGroups'])->middleware(['pagination']);
                Route::post('/', [$defaultController, 'createShipmentByCustomerGroup']);
                Route::post('/admin', [$defaultController, 'createCustomerGroupShipmentByAdmin']);
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
                Route::get('/groups', [$defaultController, 'getCustomerGroups'])->middleware(['pagination']);
                Route::get('/details', [$defaultController, 'getCustomerDetails']);
            }
        );
    }
);
