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
}
