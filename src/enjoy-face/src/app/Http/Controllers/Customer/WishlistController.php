<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\WishlistItem;
use StarsNet\Project\EnjoyFace\App\Models\Store;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectStoreTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    use AuthenticationTrait,
        Sortable,
        ProductTrait,
        StoreDependentTrait,
        ProjectStoreTrait;

    /** @var Store $store */
    protected $store;

    protected $model = WishlistItem::class;

    public function __construct(Request $request)
    {
        $this->store = self::getStoreByValue($request->route('store_id'));
    }

    public function getAll(Request $request)
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

        // Get authenticated User information
        $customer = $this->customer();

        // Get WishlistItem(s)
        $wishlistItemIds = $customer->wishlistItems()
            ->pluck('store_id')
            ->all();

        $storeIds = array_intersect($wishlistItemIds, $storeIdsByProductCategories);

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

        foreach ($stores as $store) {
            $this->appendStoreAttributes($store, $reviews, $wishlistItemIds, $latitude, $longitude);
        }

        return $stores;
    }

    public function addAndRemove(Request $request)
    {
        // Extract attributes from $request
        $storeId = $request->product_id;

        // Get Product, then validate
        /** @var Store $store */
        $store = Store::find($storeId);

        if (!$store->isStatusActive()) {
            return response()->json([
                'message' => 'Product is not available for public'
            ], 404);
        }

        // Get authenticated User information
        $customer = $this->customer();

        // Delete WishlistItem
        if ($customer->wishlistItems()->byStore($store)->exists()) {
            $items = $customer->wishlistItems()
                ->byStore($store);

            $items->delete();

            // Return success message
            return response()->json([
                'message' => 'Removed Product from wishlist successfully'
            ], 200);
        }

        // Create WishlistItem
        $item = $customer->wishlistItems()->create();
        $item->associateStore($store);

        // Return success message
        return response()->json([
            'message' => 'Added Product to wishlist successfully'
        ], 200);
    }
}
