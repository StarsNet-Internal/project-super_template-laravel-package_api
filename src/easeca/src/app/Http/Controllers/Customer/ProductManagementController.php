<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

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
use StarsNet\Project\Easeca\App\Traits\Controller\ProjectProductTrait;
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

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $account = $this->account();

        if ($account['store_id'] != null) {
            $this->store = $this->getStoreByValue($account['store_id']);
        } else {
            $this->store = $this->getStoreByValue($request->route('store_id'));
        }
    }

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
        $products = $this->getProductInfoByAggregation($productIDs);

        // Return data
        return $products;
    }

    public function getRelatedProductsUrls(Request $request)
    {
        // Extract attributes from $request
        $productID = $request->input('product_id');
        $excludedProductIDs = $request->input('exclude_ids', []);
        $itemsPerPage = $request->input('items_per_page');

        // Append to excluded Product
        $excludedProductIDs[] = $productID;

        // Initialize a Product collector
        $products = [];

        /*
        *   Stage 1:
        *   Get Product(s) from System ProductCategory, recommended-products
        */
        $systemCategory = ProductCategory::slug('recommended-products')->first();

        if (!is_null($systemCategory)) {
            // Get Product(s)
            $recommendedProducts = $systemCategory->products()
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $recommendedProducts = $recommendedProducts->shuffle(); // randomize ordering

            // Collect data
            $products = array_merge($products, $recommendedProducts->all()); // collect Product(s)
            $excludedProductIDs = array_merge($excludedProductIDs, $recommendedProducts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 2:
        *   Get Product(s) from active, related ProductCategory(s)
        */
        $product = Product::find($productID);

        if (!is_null($product)) {
            // Get related ProductCategory(s) by Product and within Store
            $relatedCategories = $product->categories()
                ->storeID($this->store)
                ->statusActive()
                ->get();

            $relatedCategoryIDs = $relatedCategories->pluck('_id')->all();

            // Get Product(s)
            $relatedProducts = Product::whereHas('categories', function ($query) use ($relatedCategoryIDs) {
                $query->whereIn('_id', $relatedCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $relatedProducts = $relatedProducts->shuffle(); // randomize ordering

            // Collect data
            $products = array_merge($products, $relatedProducts->all()); // collect Product(s)
            $excludedProductIDs = array_merge($excludedProductIDs, $relatedProducts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 3:
        *   Get Product(s) assigned to this Store's active ProductCategory(s)
        */
        // Get remaining ProductCategory(s) by Store
        if (!isset($relatedCategoryIDs)) $relatedCategoryIDs = [];
        $otherCategories = $this->store
            ->productCategories()
            ->statusActive()
            ->excludeIDs($relatedCategoryIDs)
            ->get();

        if ($otherCategories->count() > 0) {
            $otherCategoryIDs = $otherCategories->pluck('_id')->all();

            // Get Product(s)
            $otherProducts = Product::whereHas('categories', function ($query) use ($otherCategoryIDs) {
                $query->whereIn('_id', $otherCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $otherProducts = $otherProducts->shuffle();

            // Collect data
            $products = array_merge($products, $otherProducts->all());
        }

        /*
        *   Stage 4:
        *   Generate URLs
        */
        $productIDsSet = collect($products)->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($productIDsSet as $IDsSet) {
            $urls[] = str_replace('https', 'http', route('easeca.products.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all()
            ]));
        }

        // Return urls
        return $urls;
    }

    public function getProductsByIDs(Request $request)
    {
        // Extract attributes from $request
        $productIDs = $request->ids;

        // Append attributes to each Product
        $products = $this->getProductInfoByAggregation($productIDs);

        // Return data
        return $products;
    }
}
