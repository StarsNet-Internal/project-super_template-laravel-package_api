<?php

namespace StarsNet\Project\HeiFei\App\Http\Controllers\Admin;

use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\StoreDependentTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\HeiFei\App\Models\DailyCashflow;

class CategoryController extends Controller
{
    use StoreDependentTrait;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $this->store = self::getStoreByValue($request->route('store_id'));
    }

    public function getCategoryDetails(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');

        // Get ProductCategory, then validate
        /** @var ProductCategory $category */
        $category = ProductCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'ProductCategory not found'
            ], 404);
        }

        $category->children_categories = $category->children()->get();

        // Return success message
        return response()->json($category, 200);
    }

    public function getAllProductCategories(Request $request)
    {
        // Extract attributes from $request
        $excludeIDs = $request->input('exclude_ids', []);

        $categories = $this->store
            ->productCategories()
            ->statusActive()
            ->excludeIDs($excludeIDs)
            ->get();

        foreach ($categories as $category) {
            $category->children_categories = $category->children()->get();
        }

        return response()->json($categories, 200);
    }
}
