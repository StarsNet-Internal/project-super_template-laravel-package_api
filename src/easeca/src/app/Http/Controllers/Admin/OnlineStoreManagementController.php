<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Admin\OnlineStoreManagementController as AdminOnlineStoreManagementController;

class OnlineStoreManagementController extends AdminOnlineStoreManagementController
{
    use StoreDependentTrait;

    /** @var Store $store */
    protected $store;

    public function getCategoryUnassignedProducts(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get ProductCategory, then validate
        /** @var ProductCategory $category */
        $category = ProductCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'ProductCategory not found'
            ], 404);
        }

        // Get assigned Product(s)
        $assignedProductIDs = $category->products()->pluck('_id')->all();
        /** @var Collection $products */
        $products = Product::where('store_id', $this->store->_id)
            ->excludeIDs($assignedProductIDs)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->get();

        // Return Product(s)
        return $products;
    }
}
