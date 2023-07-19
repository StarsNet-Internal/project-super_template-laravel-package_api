<?php

namespace StarsNet\Project\Capi\App\Http\Controllers\Customer;

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
use StarsNet\Project\Capi\App\Models\Deal;
use StarsNet\Project\Capi\App\Models\DealCategory;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\DummyDataTrait;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WishlistItemTrait;
use StarsNet\Project\Capi\App\Traits\Controller\ProjectShoppingCartTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DealManagementController extends Controller
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        StoreDependentTrait,
        WishlistItemTrait,
        ProjectShoppingCartTrait;

    use Cacheable;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $this->store = $this->getStoreByValue($request->route('store_id'));
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

    private function appendDealAttributes($deal)
    {
        $hasDealGroup = count($deal['dealGroups']) > 0;
        $currentOrderQty = $hasDealGroup ? $deal['dealGroups'][0]->quantity_sold : 0;
        $retailPrice = count($deal['product']['variants']) > 0 ? $this->roundingValue($deal['product']['variants'][0]['price']) : '0.00';

        // Mapping to existing keys
        $output = [
            "_id" => $deal->_id,
            "category_ids" => $deal->category_ids,
            "title" => $deal->title,
            "short_description" => $deal->short_description,
            "long_description" => $deal->long_description,
            "images" => $deal->images,
            "seo" => [
                "meta_keywords" => [],
                "meta_description" => ""
            ],
            "is_virtual_product" => $deal['product']['is_virtual_product'],
            "is_free_shipping" => $deal['product']['is_free_shipping'],
            "is_refundable" => $deal['product']['is_refundable'],
            "scheduled_at" => $deal->start_datetime,
            "published_at" => $deal->end_datetime,
            "updated_at" => $deal->updated_at,
            "created_at" => $deal->created_at,
            "first_product_variant_id" => count($deal['active_product_variant_ids']) > 0 ? $deal['active_product_variant_ids'][0] : $deal['product']['first_product_variant_id'],
            "is_new" => true,
            "price" => $retailPrice,
            "point" => 0,
            "local_discount_type" => "PRICE",
            "global_discount" => null,
            "rating" => 5,
            "review_count" => 0,
            "inventory_count" => 0,
            "wishlist_item_count" => 0,
            "is_liked" => false,
            "discounted_price" => "0.00",
        ];

        // New keys
        $output['max_qty'] = $deal->max_qty;
        $output['remaining_qty'] = $deal->max_qty - $currentOrderQty;
        $output['commission'] = $this->roundingValue($deal->commission);
        $output['current_order_qty'] = $currentOrderQty;
        // $output['current_tier_price'] = $hasDealGroup ? $this->getDiscountedPrice($deal['dealGroups'][0]) : $retailPrice;
        $unsortedTiers = $deal['tiers']->toArray();
        usort($unsortedTiers, function ($a, $b) {
            return $b['user_count'] <=> $a['user_count'];
        });
        foreach ($unsortedTiers as $tier) {
            if ($currentOrderQty < $tier['user_count']) {
                $output['next_tier_price'] = $this->roundingValue($tier['discounted_price']);
            }
        }

        usort($unsortedTiers, function ($a, $b) {
            return $a['user_count'] <=> $b['user_count'];
        });
        foreach ($unsortedTiers as $tier) {
            if ($currentOrderQty >= $tier['user_count']) {
                $output['current_tier_price'] = $this->roundingValue($tier['discounted_price']);
            }
        }
        $output['current_tier_price'] = $output['current_tier_price'] ?? $retailPrice;

        $output['supplier'] = [
            'username' => $deal['accountDeal']['account']['username'],
            'avatar' => $deal['accountDeal']['account']['avatar'],
        ];
        $output['tiers'] = array_map(function ($tier) {
            return [
                'user_count' => $tier['user_count'],
                'discounted_price' => $this->roundingValue($tier['discounted_price']),
            ];
        },  $unsortedTiers);
        $output['deal_groups'] = $deal['dealGroups'];

        return $output;
    }

    private function getDealsInfo(array $dealIDs)
    {
        $deals = Deal::objectIDs($dealIDs)->with([
            'categories',
            'dealGroups',
            'product',
            'product.variants',
            'tiers',
            'accountDeal',
            'accountDeal.account',
        ])->get([]);

        $mappedDeals = [];
        foreach ($deals as $deal) {
            $mappedDeals[] = $this->appendDealAttributes($deal);
        }

        // Return data
        return $mappedDeals;
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
        $dealIDs = array_values($dealIDs);
        if (count($dealIDs) === 0) return new Collection();

        // Filter Product(s)
        $deals = $this->getDealsInfo($dealIDs);

        // Return data
        return $deals;
    }

    private function appendDealDetailsAttributes($deal)
    {
        $output = [
            "discount" => null,
            "remarks" => $deal->remarks,
            "status" => $deal->status,
            "is_system" => false,
            "deleted_at" => $deal->deleted_at,
            "is_liked" => false,
            "categories" => array_map(function ($category) {
                return [
                    '_id' => $category['_id'],
                    'title' => $category['title'],
                ];
            },  $deal['categories']->toArray()),
        ];
        foreach ($deal['product']['variants'] as $variant) {
            $variant->appendDisplayableFieldsForCustomer($this->store);
            unset($variant['product']);
            if (in_array($variant['_id'], $deal->active_product_variant_ids)) {
                $output['variants'][] = $variant;
            }
        }

        return $output;
    }

    public function getDealDetails(Request $request)
    {
        // Extract attributes from $request
        $dealID = $request->route('deal_id');

        // Get Deal, then validate
        /** @var Deal $deal */
        $deal = Deal::with([
            'categories',
            'dealGroups',
            'product',
            'product.variants',
            'tiers',
            'accountDeal',
            'accountDeal.account',
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

        $output = array_merge($this->appendDealAttributes($deal), $this->appendDealDetailsAttributes($deal));

        // Return data
        return response()->json($output, 200);
    }

    public function getDealReviews(Request $request)
    {
        // Extract attributes from $request
        $productID = $request->route('product_id');

        return new Collection();
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

        if (count($deal->dealGroups()->get())) {
            return response()->json([
                'message' => 'DealGroup exists for this Deal'
            ], 200);
        }

        $group = $deal->createDealGroup([]);

        return response()->json([
            'message' => 'Created New DealGroup successfully',
            '_id' => $group->_id
        ], 200);
    }

    public function getCurrentServerTime()
    {
        return Carbon::now();
    }

    public function getRelatedDealsUrls(Request $request)
    {
        // Extract attributes from $request
        $dealID = $request->input('product_id');
        $excludedDealIDs = $request->input('exclude_ids', []);
        $itemsPerPage = $request->input('items_per_page');

        // Append to excluded Deal
        $excludedDealIDs[] = $dealID;

        // Initialize a Deal collector
        $deals = [];

        /*
        *   Stage 1:
        *   Get Deal(s) from System DealCategory, recommended-products
        */
        $systemCategory = DealCategory::slug('recommended-products')->first();

        if (!is_null($systemCategory)) {
            // Get Deal(s)
            $recommendedDeals = $systemCategory->deals()
                ->statusActive()
                ->excludeIDs($excludedDealIDs)
                ->get();

            // Randomize ordering
            $recommendedDeals = $recommendedDeals->shuffle(); // randomize ordering

            // Collect data
            $deals = array_merge($deals, $recommendedDeals->all()); // collect Deal(s)
            $excludedDealIDs = array_merge($excludedDealIDs, $recommendedDeals->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 2:
        *   Get Deal(s) from active, related DealCategory(s)
        */
        $deal = Deal::find($dealID);

        if (!is_null($deal)) {
            // Get related DealCategory(s) by Deal and within Store
            $relatedCategories = $deal->categories()
                ->storeID($this->store)
                ->statusActive()
                ->get();

            $relatedCategoryIDs = $relatedCategories->pluck('_id')->all();

            // Get Deal(s)
            $relatedDeals = Deal::whereHas('categories', function ($query) use ($relatedCategoryIDs) {
                $query->whereIn('_id', $relatedCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedDealIDs)
                ->get();

            // Randomize ordering
            $relatedDeals = $relatedDeals->shuffle(); // randomize ordering

            // Collect data
            $deals = array_merge($deals, $relatedDeals->all()); // collect Deal(s)
            $excludedDealIDs = array_merge($excludedDealIDs, $relatedDeals->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 3:
        *   Get Deal(s) assigned to this Store's active DealCategory(s)
        */
        // Get remaining DealCategory(s) by Store
        if (!isset($relatedCategoryIDs)) $relatedCategoryIDs = [];
        $otherCategories = DealCategory::storeID($this->store)
            ->statusActive()
            ->excludeIDs($relatedCategoryIDs)
            ->get();

        if ($otherCategories->count() > 0) {
            $otherCategoryIDs = $otherCategories->pluck('_id')->all();

            // Get Deal(s)
            $otherDeals = Deal::whereHas('categories', function ($query) use ($otherCategoryIDs) {
                $query->whereIn('_id', $otherCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedDealIDs)
                ->get();

            // Randomize ordering
            $otherDeals = $otherDeals->shuffle();

            // Collect data
            $deals = array_merge($deals, $otherDeals->all());
        }

        /*
        *   Stage 4:
        *   Generate URLs
        */
        $dealIDsSet = collect($deals)->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($dealIDsSet as $IDsSet) {
            $urls[] = str_replace('https', 'http', route('deals.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all()
            ]));
        }

        // Return urls
        return $urls;
    }

    public function getDealsByIDs(Request $request)
    {
        // Extract attributes from $request
        $dealIDs = $request->ids;

        // Append attributes to each Deal
        $deals = $this->getDealsInfo($dealIDs);

        // Return data
        return $deals;
    }
}
