<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Cashier;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Warehouse;
use StarsNet\Project\EnjoyFace\App\Models\StoreCategory;
use Illuminate\Http\Request;

use App\Http\Controllers\Admin\OfflineStoreManagementController as AdminOfflineStoreManagementController;

class OfflineStoreManagementController extends AdminOfflineStoreManagementController
{
    public function getAllOfflineStores(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get all Store(s)
        $stores = Store::whereType(StoreType::OFFLINE)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->with([
                'warehouses' => function ($query) {
                    $query->statuses(Status::$typesForAdmin)->get();
                },
                'cashiers' => function ($query) {
                    $query->statuses(Status::$typesForAdmin)->get();
                },
                'orders',
            ])
            ->get();

        // Append attributes
        foreach ($stores as $store) {
            $store['order_count'] = count($store['orders']);
            unset($store['orders']);
        }

        // Return Store(s)
        return $stores;
    }

    public function createStoreCategory(Request $request)
    {
        // Create StoreCategory
        /** @var StoreCategory $category */
        $category = StoreCategory::create($request->all());

        // Return success message
        return response()->json([
            'message' => 'Created New StoreCategory successfully',
            '_id' => $category->_id
        ], 200);
    }

    public function getAllStoreCategories(Request $request)
    {
        // Extract attributes from $request
        $type = $request->input('type');

        // Get StoreCategory(s)
        /** @var Collection $categories */
        $categories = StoreCategory::whereItemType('Store')
            ->where('store_category_type', $type)
            ->get();

        // Return StoreCategory(s)
        return $categories;
    }

    public function assignStoresToCategory(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $storeIDs = $request->input('ids', []);

        // Get StoreCategory, then validate
        /** @var StoreCategory $category */
        $category = StoreCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'StoreCategory not found'
            ], 404);
        }

        // Get Store(s)
        /** @var Collection $stores */
        $stores = Store::find($storeIDs);

        // Update relationships
        $category->attachStores(collect($stores));

        // Return success message
        return response()->json([
            'message' => 'Assigned ' . $stores->count() . ' Store(s) successfully'
        ], 200);
    }

    public function unassignStoresFromCategory(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $storeIDs = $request->input('ids', []);

        // Get StoreCategory, then validate
        /** @var StoreCategory $category */
        $category = StoreCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'StoreCategory not found'
            ], 404);
        }

        // Get Store(s)
        /** @var Collection $stores */
        $stores = Store::find($storeIDs);

        // Update relationships
        $category->detachStores(collect($stores));

        // Return success message
        return response()->json([
            'message' => 'Unassigned ' . $stores->count() . ' Store(s) successfully'
        ], 200);
    }
}
