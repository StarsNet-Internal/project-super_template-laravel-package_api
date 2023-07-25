<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;

use App\Models\Warehouse;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use App\Traits\Utils\Flattenable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\TripleGaga\Traits\Controllers\RecursiveArrayModifier;

class TenantController extends Controller
{
    use Flattenable, ProductTrait, Sortable, RecursiveArrayModifier;

    public function getAllTenants(Request $request)
    {
        $roleSlug = $request->input('role_slug', 'staff');
        $role = Role::slug($roleSlug)->first();

        $users = User::whereIsDeleted(false)
            ->whereHas('account', function ($query) use ($role) {
                $query->where('role_id', $role->_id);
            })
            ->get()
            ->pluck('account');

        return $users;
    }

    public function getTenantDetails(Request $request)
    {
        $accountId = $request->account_id;
        $account = Account::find($accountId);
        return $account;
    }

    public function getTenantCategoryHierarchy(Request $request)
    {
        // Get all Categories
        $accountId = $request->account_id;
        $categoryIds = Product::where('created_by_account_id', $accountId)->statusActive()->pluck("category_ids")->collapse()->unique()->all();
        $parentCategoryIds = Category::objectIDs($categoryIds)->whereNotNull('parent_id')->pluck('parent_id')->unique()->all();
        $categoryIds = array_unique(array_merge($categoryIds, $parentCategoryIds));

        // Extract attributes from $request
        $storeId = $request->store_id;
        $store = Store::find($storeId);

        // Get Hierarchy
        $hierarchy = optional(Hierarchy::whereModelID($store->_id)->first())->hierarchy;
        if (is_null($hierarchy)) return new Collection();

        $this->recursiveAppendIsKeep($hierarchy, $categoryIds);
        $hierarchy = $this->recursiveRemoveItem($hierarchy);
        $this->recursiveGetCategoryInfo($hierarchy);

        return $hierarchy;
    }

    public function filterTenantProductsByCategories(Request $request)
    {
        // Extract attributes from $request
        $accountId = $request->route('account_id');
        $storeId = $request->route('store_id');

        $categoryIDs = $request->input('category_ids', []);
        $keyword = $request->input('keyword');
        if ($keyword === "") $keyword = null;
        $slug = $request->input('slug', 'by-keyword-relevance');

        // Get sorting attributes via slugs
        if (!is_null($slug)) {
            $sortingValue = $this->getProductSortingAttributesBySlug('product-sorting', $slug);
            switch ($sortingValue['type']) {
                case 'KEY':
                    $request['sort_by'] = $sortingValue['key'];
                    $request['sort_order'] = $sortingValue['ordering'];
                    break;
                case 'KEYWORD':
                    break;
                default:
                    break;
            }
        }

        // Get all ProductCategory(s)
        $store = Store::find($storeId);
        if (count($categoryIDs) === 0) {
            $categoryIDs = $store
                ->productCategories()
                ->statusActive()
                ->get()
                ->pluck('_id')
                ->all();
        }

        // Get Product(s) from selected ProductCategory(s)
        $productIDs = Product::where('created_by_account_id', $accountId)
            ->whereHas(
                'categories',
                function ($query) use ($categoryIDs) {
                    $query->whereIn('_id', $categoryIDs);
                }
            )
            ->statusActive()
            ->when(!$keyword, function ($query) {
                $query->limit(250);
            })
            ->get()
            ->pluck('_id')
            ->all();

        // Get matching keywords from Typesense
        if (!is_null($keyword)) {
            $typesense = new TypeSenseSearchEngine('products');
            $productIDsByKeyword = $typesense->getIDsFromSearch(
                $keyword,
                'title.en,title.zh,title.cn'
            );
            if (count($productIDsByKeyword) === 0) return new Collection();
            $productIDs = array_intersect(
                $productIDs,
                $productIDsByKeyword
            );
        }
        if (count($productIDs) === 0) return new Collection();

        // Filter Product(s)
        $products = $this->getProductsInfoByAggregation($productIDs, $store);

        // Return data
        return $products;
    }
}
