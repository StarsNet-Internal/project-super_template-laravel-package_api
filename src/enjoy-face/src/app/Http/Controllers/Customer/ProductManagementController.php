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
        // Get Hierarchy
        $hierarchy = Hierarchy::whereModelID(Alias::getValue('default-main-store'))->first();
        if (is_null($hierarchy)) return new Collection();
        $hierarchy = $hierarchy['hierarchy'];

        $categoryIds = Product::where('store_id', $request->route('store_id'))
            ->statusActive()
            ->get()
            ->pluck('category_ids')
            ->all();

        $flattened = array_merge(...$categoryIds);
        $categoryIds = collect($flattened)->unique()->values()->all();

        // Get Categories
        $categories = [];
        foreach ($hierarchy as $branch) {
            $category = Category::objectID($branch['category_id'])
                ->whereIn('_id', $categoryIds)
                ->statusActive()
                ->with(['children' => function ($query) use ($categoryIds) {
                    $query->whereIn('_id', $categoryIds)
                        ->statusActive()
                        ->orderBy('title.en', 'asc')
                        ->get(['_id', 'parent_id', 'title']);
                }])
                ->first(['parent_id', 'title']);
            if (is_null($category)) continue;
            $categories[] = $category;
        }

        // Return data
        return $categories;
    }
}
