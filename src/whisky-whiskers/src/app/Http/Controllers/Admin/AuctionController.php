<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

use Illuminate\Support\Str;

class AuctionController extends Controller
{
    public function createAuctionStore(Request $request)
    {
        // Extract attributes from $request
        $attributes = $request->all();

        // Create Store
        /** @var Store $store */
        $store = Store::createOfflineStore($attributes);

        // Create Warehouse
        $warehouseTitle = 'auction_warehouse_' . $store->_id;
        $warehouse = $store->warehouses()->create([
            'type' => 'AUCTION',
            'slug' => Str::slug($warehouseTitle),
            'title' => [
                'en' => $warehouseTitle,
                'zh' => $warehouseTitle,
                'cn' => $warehouseTitle
            ],
            'is_system' => true,
        ]);

        // Create one default Category
        $categoryTitle = 'all_products' . $store->_id;;
        $category = $store->productCategories()->create([
            'slug' => Str::slug($categoryTitle),
            'title' => [
                'en' => $categoryTitle,
                'zh' => $categoryTitle,
                'cn' => $categoryTitle
            ],
            'is_system' => true,
        ]);

        // Return success message
        return response()->json([
            'message' => 'Created new Auction successfully',
            '_id' => $store->_id,
            'warehouse_id' => $warehouse->_id,
            'category_id' => $category->_id,
        ], 200);
    }
}
