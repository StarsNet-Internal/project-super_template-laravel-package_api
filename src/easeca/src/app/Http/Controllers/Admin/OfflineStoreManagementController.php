<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

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
use Illuminate\Http\Request;

class OfflineStoreManagementController extends Controller
{
    public function getAllOfflineStores(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get all Store(s)
        $stores = Store::whereType(StoreType::OFFLINE)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            // ->with([
            //     'warehouses' => function ($query) {
            //         $query->statuses(Status::$typesForAdmin);
            //     },
            //     'cashiers' => function ($query) {
            //         $query->statuses(Status::$typesForAdmin);
            //     },
            // ])
            ->get();

        // Return Store(s)
        return $stores;
    }

    public function massUpdateStores(Request $request)
    {
        $storeAttributes = $request->stores;

        foreach ($storeAttributes as $storeAttribute) {
            $storeId = $storeAttribute['id'];
            $store = Store::find($storeId);

            // Check if the Store exists
            if (!is_null($store)) {
                $updateAttributes = $storeAttribute;
                unset($updateAttributes['id']);
                $store->update($updateAttributes);
            }
        }

        return response()->json([
            'message' => 'Stores updated successfully'
        ], 200);
    }

    public function deleteStores(Request $request)
    {
        // Extract attributes from $request
        $storeIDs = $request->input('ids', []);

        // Get Store(s)
        /** @var Collection $stores */
        $stores = Store::find($storeIDs);

        // Filter non-system Store(s)
        $stores = $stores->filter(function ($store) {
            return !$store->is_system;
        });

        // Update Store(s)
        /** @var Store $store */
        foreach ($stores as $store) {
            $store->statusDeletes();
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $stores->count() . ' Store(s) successfully'
        ], 200);
    }
}
