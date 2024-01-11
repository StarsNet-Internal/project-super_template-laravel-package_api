<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
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
use StarsNet\Project\Esgone\App\Traits\Controller\ProjectProductTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Customer\ProductManagementController as CustomerProductManagementController;

class ProductManagementController extends CustomerProductManagementController
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait,
        StoreDependentTrait,
        ProjectProductTrait;

    use Cacheable;

    public function filterProductsByCategories(Request $request)
    {
        // Extract attributes from $request
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
        if (count($categoryIDs) === 0) {
            $categoryIDs = $this->store
                ->productCategories()
                ->statusActive()
                ->get()
                ->pluck('_id')
                ->all();
        }

        // Get Product(s) from selected ProductCategory(s)
        $productIDs = Product::whereHas('categories', function ($query) use ($categoryIDs) {
            $query->whereIn('_id', $categoryIDs);
        })
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
            $productIDs = array_intersect($productIDs, $productIDsByKeyword);
        }
        if (count($productIDs) === 0) return new Collection();

        // Filter Product(s)
        $products = $this->getProductsInfoByEagerLoading($productIDs);

        // Return data
        return $products;
    }
}
