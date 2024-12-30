<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WishlistItemTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductManagementController extends Controller
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait,
        StoreDependentTrait;

    use Cacheable;

    public function getAllProductCategoryHierarchy(Request $request)
    {
        // Get Main Store Hierarchy for Offline Stores
        $modelId = $request->route('store_id') === 'default-mini-store' ? 'default-mini-store' : 'default-main-store';
        $hierarchy = Hierarchy::whereModelID(Alias::getValue($modelId))->first();
        if (is_null($hierarchy)) return new Collection();
        $hierarchy = $hierarchy['hierarchy'];

        $allCategories = ProductCategory::where('item_type', 'Product')
            ->get(['parent_id', 'title'])
            ->makeHidden(['parent_category']);

        if ($request->route('store_id') === 'default-main-store' || $request->route('store_id') === 'default-mini-store') {
            // Get Categories
            $categories = [];
            foreach ($hierarchy as $branch) {
                $category = $allCategories->firstWhere('_id', $branch['category_id'])->toArray();
                $children = [];
                foreach ($branch['children'] as $child) {
                    $childCategory = $allCategories->firstWhere('_id', $child['category_id']);
                    $children[] = $childCategory;
                }
                $category['children'] = $children;
                $categories[] = $category;
            }
        } else {
            $categoryIds = Product::where('store_id', $request->route('store_id'))
                ->statusActive()
                ->get()
                ->pluck('category_ids')
                ->all();

            $flattened = array_merge(...$categoryIds);
            $categoryIds = collect($flattened)->unique()->values()->all();

            // Get Non-empty Categories of a given Store in Store Details
            $allCategories = collect($allCategories->whereIn('_id', $categoryIds)->all());

            $categories = [];
            foreach ($hierarchy as $branch) {
                $category = $allCategories->firstWhere('_id', $branch['category_id']);
                if (is_null($category)) continue;
                $category = $category->toArray();

                $children = [];
                foreach ($branch['children'] as $child) {
                    $childCategory = $allCategories->firstWhere('_id', $child['category_id']);
                    if (is_null($childCategory)) continue;

                    $children[] = $childCategory;
                }
                $category['children'] = $children;
                $categories[] = $category;
            }
        }

        return $categories;
    }
}
