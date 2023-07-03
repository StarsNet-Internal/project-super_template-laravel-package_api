<?php

namespace StarsNet\Project\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\Store;
use StarsNet\Project\App\Models\Deal;
use StarsNet\Project\App\Models\DealCategory;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\DummyDataTrait;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\WishlistItemTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class DealManagementController extends Controller
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait;

    use Cacheable;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->route('store_id');

        // Assign as properties
        /** @var Store $store */
        $this->store = Store::find($storeID);
    }

    public function getAllDealCategories(Request $request)
    {
        // Extract attributes from $request
        $excludeIDs = $request->input('exclude_ids', []);

        return DealCategory::whereItemType('Deal')
            ->statusActive()
            ->excludeIDs($excludeIDs)
            ->get();
    }

    public function getAllDealCategoryHierarchy(Request $request)
    {
        // Get Hierarchy
        $hierarchy = Hierarchy::whereModelID($this->store->_id)->latest()->first();
        if (is_null($hierarchy)) return new Collection();
        $hierarchy = $hierarchy['hierarchy'];

        // Get Categories
        $categories = [];
        foreach ($hierarchy as $branch) {
            $category = Category::objectID($branch['category_id'])
                ->statusActive()
                ->with(['children' => function ($query) {
                    $query->statusActive()->get(['_id', 'parent_id', 'title']);
                }])
                ->first(['parent_id', 'title']);
            if (is_null($category)) continue;
            $categories[] = $category;
        }

        // Return data
        return $categories;
    }

    public function filterDealsByCategories(Request $request)
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

        // Get all DealCategory(s)
        if (count($categoryIDs) === 0) {
            $categoryIDs = DealCategory::whereItemType('Deal')
                ->statusActive()
                ->get()
                ->pluck('_id')
                ->all();
        }

        // Get Deal(s) from selected DealCategory(s)
        $dealIDs = Deal::whereHas('categories', function ($query) use ($categoryIDs) {
            $query->whereIn('_id', $categoryIDs);
        })
            ->statusOngoing()
            ->when(!$keyword, function ($query) {
                $query->limit(250);
            })
            ->get()
            ->pluck('_id')
            ->all();

        // TODO Get matching keywords from Typesense
        // if (!is_null($keyword)) {
        //     $typesense = new TypeSenseSearchEngine('deals');
        //     $dealIDsByKeyword = $typesense->getIDsFromSearch(
        //         $keyword,
        //         'title.en,title.zh'
        //     );
        //     if (count($dealIDsByKeyword) === 0) return new Collection();
        //     $dealIDs = array_intersect($dealIDs, $dealIDsByKeyword);
        // }
        // $dealIDs = array_values($dealIDs);
        // if (count($dealIDs) === 0) return new Collection();

        // Filter Product(s)
        $deals = Deal::objectIDs($dealIDs)->with([
            'product' => function ($product) {
                $product->select('first_product_variant_id');
            },
        ])->get([]);

        foreach ($deals as $deal) {
            $deal['first_product_variant_id'] = $deal['product']['first_product_variant_id'];

            unset($deal['product']);
        }

        // Return data
        return $deals->append(['min_discounted_price', 'max_discounted_price']);
    }

    public function getDealDetails(Request $request)
    {
        // Extract attributes from $request
        $dealID = $request->route('deal_id');

        // Get Deal, then validate
        /** @var Deal $deal */
        $deal = Deal::with([
            'categories' => function ($category) {
                $category->select('item_ids', 'title');
            },
            'dealGroups',
            'product' => function ($product) {
                $product->with([
                    'variants' => function ($variant) {
                        $variant->statusActive()->select('product_id', 'title', 'images', 'price');
                    }
                ])->select('category_ids', 'title', 'long_description', 'images');
            },
            'tiers',
        ])->find($dealID);

        if (is_null($deal)) {
            return response()->json([
                'message' => 'Deal not found'
            ], 404);
        }

        if (!$deal->isDealOngoing()) {
            return response()->json([
                'message' => 'Deal is not available for public'
            ], 404);
        }

        // // Append Product
        // $product = Product::find($deal['product_id']);
        // $product->appendDisplayableFieldsForCustomer($this->store);

        // // Get active ProductVariant(s) by Product
        // /** @var Collection $variants */
        // $variants = $product->variants()
        //     ->statusActive()
        //     ->get();

        // // Append attributes to each ProductVariant
        // /** @var ProductVariant $variant */
        // foreach ($variants as $key => $variant) {
        //     $variant->appendDisplayableFieldsForCustomer($this->store);
        // }
        // $product->variants = $variants;
        // $deal['product'] = $product;

        // Return data
        return response()->json($deal, 200);
    }

    public function createGroup(Request $request)
    {
        // Extract attributes from $request
        $dealID = $request->route('deal_id');

        $deal = Deal::find($dealID);

        if (is_null($deal)) {
            return response()->json([
                'message' => 'Deal not found'
            ], 404);
        }

        $group = $deal->createDealGroup([]);

        return response()->json([
            'message' => 'Created New DealGroup successfully',
            '_id' => $group->_id
        ], 200);
    }
}
