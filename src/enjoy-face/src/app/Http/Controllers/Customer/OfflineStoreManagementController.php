<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

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
use App\Models\Warehouse;
use App\Models\ProductReview;
use App\Models\User;
use StarsNet\Project\EnjoyFace\App\Models\Store;
use StarsNet\Project\EnjoyFace\App\Models\StoreCategory;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WishlistItemTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectProductTrait;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectStoreTrait;
use Illuminate\Http\Request;

class OfflineStoreManagementController extends Controller
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait,
        StoreDependentTrait,
        ProjectProductTrait,
        ProjectStoreTrait;

    use Cacheable;

    public function getAllStoreCategories(Request $request)
    {
        // Get StoreCategory(s)
        /** @var Collection $categories */
        $districts = StoreCategory::whereItemType('Store')
            ->where('store_category_type', 'DISTRICT')
            ->statusActive()
            ->orderByDesc('created_at')
            ->get(['_id', 'title']);
        $ratings = StoreCategory::whereItemType('Store')
            ->where('store_category_type', 'RATING')
            ->statusActive()
            ->orderByDesc('created_at')
            ->get(['_id', 'title']);

        // Return StoreCategory(s)
        return [
            [
                'title' => [
                    'en' => 'District',
                    'zh' => '地區',
                    'cn' => '地区',
                ],
                'type' => 'checkbox',
                'children' => $districts,
            ],
            [
                'title' => [
                    'en' => 'Rating',
                    'zh' => '評分',
                    'cn' => '评分',
                ],
                'type' => 'radio',
                'children' => $ratings,
            ],
        ];
    }

    public function filterStoresByCategories(Request $request)
    {
        // Extract attributes from $request
        $categoryIds = $request->input('category_ids', []);
        $districtIds = $request->input('district_ids', []);
        $ratingIds = $request->input('rating_ids', []);
        $keyword = $request->input('keyword');
        if ($keyword === "") $keyword = null;
        $slug = $request->input('slug', 'distance-from-near-to-far');
        $latitude = $request->input('latitude', '22.3193');
        $longitude = $request->input('longitude', '114.1694');
        $userId = $request->input('user_id');
        if ($userId === "") $userId = null;

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

        // Get Store(s) from selected StoreCategory(s)
        $storeIdsByStoreCategories = Store::whereType(StoreType::OFFLINE)
            ->when(count($districtIds), function ($query) use ($districtIds) {
                $query->whereIn('category_ids', $districtIds);
            })
            ->when(count($ratingIds), function ($query) use ($ratingIds) {
                $query->whereIn('category_ids', $ratingIds);
            })
            ->when(!$keyword, function ($query) {
                $query->limit(250);
            })
            ->get()
            ->unique('_id')
            ->pluck('_id')
            ->all();

        // Get Store(s) from selected ProductCategory(s)
        $storeIdsByProductCategories = Product::when(count($categoryIds), function ($query) use ($categoryIds) {
            $query->whereIn('category_ids', $categoryIds);
        })
            ->statusActive()
            ->when(!$keyword, function ($query) {
                $query->limit(250);
            })
            ->get()
            ->unique('store_id')
            ->pluck('store_id')
            ->all();

        $storeIds = array_intersect($storeIdsByStoreCategories, $storeIdsByProductCategories);

        if (!is_null($keyword)) {
            $storeIdsByKeyword = $this->getIDsFromSearch(
                'https://typesense.client.enjoy-face.tinkleex.com',
                'client_enjoy_face_stores',
                $keyword,
                'title.en,title.zh',
                'id'
            );
            if (count($storeIdsByKeyword) === 0) return new Collection();

            $storeIdsByProduct = $this->getIDsFromSearch(
                'https://typesense.client.enjoy-face.tinkleex.com',
                'client_enjoy_face_products',
                $keyword,
                'title.en,title.zh',
                'store_id'
            );
            if (count($storeIdsByProduct) === 0) return new Collection();

            $storeIdsFromSearch = array_merge($storeIdsByKeyword, $storeIdsByProduct);
            $storeIds = array_intersect($storeIds, $storeIdsFromSearch);
        }
        $storeIds = array_values($storeIds);
        if (count($storeIds) === 0) return new Collection();

        $stores = Store::objectIDs($storeIds)
            ->statusActive()
            ->with([
                'categories' => function ($category) {
                    $category->select('item_ids', 'title', 'store_category_type');
                },
                'orders',
            ])
            ->get();

        $reviews = ProductReview::whereIn('store_id', $storeIds)
            ->where('reply_status', 'APPROVED')
            ->get();

        if (isset($userId)) {
            $customer = User::find($userId)->account->customer;
            $wishlistItems = $customer->wishlistItems()->get()->pluck('store_id')->all();
        } else {
            $wishlistItems = [];
        }

        foreach ($stores as $store) {
            $this->appendStoreAttributes($store, $reviews, $wishlistItems, $latitude, $longitude);
        }

        return $stores;
    }

    public function getStoreDetails(Request $request)
    {
        $storeId = $request->route('store_id');
        $latitude = $request->input('latitude', '22.3193');
        $longitude = $request->input('longitude', '114.1694');
        $userId = $request->input('user_id');
        if ($userId === "") $userId = null;

        $store = Store::with([
            'categories' => function ($category) {
                $category->select('item_ids', 'title', 'store_category_type');
            },
            'orders',
        ])
            ->find($storeId);

        $reviews = ProductReview::where('store_id', $storeId)
            ->where('reply_status', 'APPROVED')
            ->get();

        if (isset($userId)) {
            $customer = User::find($userId)->account->customer;
            $wishlistItems = $customer->wishlistItems()->get()->pluck('store_id')->all();
        } else {
            $wishlistItems = [];
        }

        $this->appendStoreAttributes($store, $reviews, $wishlistItems, $latitude, $longitude);

        return $store;
    }

    public function getStoreProducts(Request $request)
    {
        $store = Store::find($request->route('store_id'));

        $productIds = Product::where('store_id', $request->route('store_id'))
            ->statusActive()
            ->get()
            ->pluck('_id')
            ->all();
        $products = $this->getProjectProductsInfoByAggregation($productIds, $store);

        return $products;
    }

    public function getReviews(Request $request)
    {
        $storeId = $request->input('store_id');
        $userId = $request->input('user_id');

        $reviews = ProductReview::when(isset($storeId), function ($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })
            ->when(isset($userId), function ($query) use ($userId) {
                $query->where('user_id', intval($userId));
            })
            ->where('model_type', 'Product')
            ->where('reply_status', 'APPROVED')
            ->with([
                'store',
                'productVariant',
            ])
            ->get();

        $reviews = array_map(function ($review) {
            $review['product_variant_title'] = $review['store']['title'];
            $review['user_id'] = $review['product_variant']['cost'] ?? 0;

            unset($review['store'], $review['product_variant']);
            return $review;
        }, $reviews->toArray());

        return $reviews;
    }

    public function search(string $baseUrl, string $collection, string $keyword, string $queryBy)
    {
        $url = $baseUrl . '/typesense/search';

        $request = [
            'collection' => $collection,
            'keyword' => $keyword,
            'queryBy' => $queryBy,
        ];

        // Get response from TypeSense Service
        try {
            $response = Http::get($url, $request);
        } catch (\Throwable $th) {
            return null;
        }

        // Extract properties from $data
        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    public function getIDsFromSearch(
        string $baseUrl,
        string $collection,
        string $keyword,
        string $queryBy,
        string $attribute,
        string $sortByColumn = 'created_at',
        string $sortByOrder = 'desc'
    ): ?array {
        $data = $this->search($baseUrl, $collection, $keyword, $queryBy);
        $IDs = array_map(fn ($value): string => $value['document'][$attribute], $data['hits']);
        return $IDs;
    }
}
