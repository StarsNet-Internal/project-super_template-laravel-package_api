<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Models
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\WatchlistItem;

// Traits
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;

class ProductManagementController extends Controller
{
    use Sortable,
        StoreDependentTrait;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $this->store = self::getStoreByValue($request->route('store_id'));
    }

    public function filterAuctionProductsByCategories(Request $request)
    {
        // Extract attributes from $request
        $categoryIDs = $request->input('category_ids', []);
        $categoryIDs = array_unique($categoryIDs);
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
        $productIDs = AuctionLot::where('store_id', $this->store->id)
            ->statuses([Status::ACTIVE, Status::ARCHIVED])
            ->get()
            ->pluck('product_id')
            ->all();

        if (count($categoryIDs) > 0) {
            $allProductCategoryIDs = Category::slug('all-products')->pluck('_id')->all();
            if (!array_intersect($categoryIDs, $allProductCategoryIDs)) {
                $productIDs = Product::objectIDs($productIDs)
                    ->whereHas('categories', function ($query) use ($categoryIDs) {
                        $query->whereIn('_id', $categoryIDs);
                    })
                    ->statuses([Status::ACTIVE, Status::ARCHIVED])
                    ->when(!$keyword, function ($query) {
                        $query->limit(250);
                    })
                    ->get()
                    ->pluck('_id')
                    ->all();
            }
        }

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
        $products = $this->getProductsInfoByAggregation($productIDs, $this->store->_id);
        $products = $products->filter(function ($item) {
            return $item->auction_lot_id != '0';
        })->values();

        // Get WatchlistItem 
        $customer = $this->customer();
        $watchingAuctionIDs = WatchlistItem::where('customer_id', $customer->id)
            ->where('item_type', 'auction-lot')
            ->get()
            ->pluck('item_id')
            ->all();

        foreach ($products as $product) {
            $auctionLotID = $product->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);

            $product->current_bid = $auctionLot->getCurrentBidPrice();
            $product->is_reserve_price_met = $product->current_bid >= $product->reserve_price;

            $product->title = $auctionLot->title;
            $product->short_description = $auctionLot->short_description;
            $product->long_description = $auctionLot->long_description;
            $product->bid_incremental_settings = $auctionLot->bid_incremental_settings;
            $product->start_datetime = $auctionLot->start_datetime;
            $product->end_datetime = $auctionLot->end_datetime;
            $product->lot_number = $auctionLot->lot_number;
            $product->status = $auctionLot->status;
            $product->is_disabled = $auctionLot->is_disabled;
            $product->is_closed = $auctionLot->is_closed;

            $product->sold_price = $auctionLot->sold_price;
            $product->commission = $auctionLot->commission;

            $product->max_estimated_price = data_get($auctionLot, 'bid_incremental_settings.estimate_price.max') ?? 0;
            $product->min_estimated_price = data_get($auctionLot, 'bid_incremental_settings.estimate_price.min') ?? 0;

            // is_watching
            $auctionLot->is_watching = in_array($auctionLotID, $watchingAuctionIDs);

            unset(
                $product->bids,
                $product->valid_bid_values,
                $product->reserve_price
            );
        }

        // Return data
        return $products;
    }

    public function filterAuctionProductsByCategoriesV2(Request $request)
    {
        // Get all product_id from all AuctionLot in this Store
        $productIDs = AuctionLot::where('store_id', $this->store->id)
            ->statuses([Status::ACTIVE, Status::ARCHIVED])
            ->pluck('product_id')
            ->all();

        // Get all product_id assigned to category_ids[] input from Request
        $categoryProductIDs = [];

        $categoryIDs = array_filter(array_unique((array) $request->category_ids));
        if (count($categoryIDs) > 0) {
            $categoryProductIDs = Category::whereIn('_id', $categoryIDs)
                ->pluck('item_ids')
                ->flatten()
                ->filter(fn($id) => !is_null($id))
                ->unique()
                ->values()
                ->all();

            // Override $productIDs only, with array intersection
            $productIDs = array_intersect($productIDs, $categoryProductIDs);
        }

        // Get Product(s)
        /** @var Collection $products */
        $products = Product::whereIn('_id', $productIDs)
            ->statuses([Status::ACTIVE, Status::ARCHIVED])
            ->get();

        // Get AuctionLot(s)
        /** @var Collection $auctionLots */
        $auctionLots = AuctionLot::whereIn('product_id', $productIDs)
            ->where('store_id', $this->store->id)
            ->with(['watchlistItems'])
            ->get()
            ->map(function ($lot) {
                $lot->watchlist_item_count = $lot->watchlistItems->count();
                unset($lot->watchlistItems);
                return $lot;
            })
            ->keyBy('product_id');

        // Get WatchlistItem 
        $watchingAuctionIDs = WatchlistItem::where('customer_id', $this->customer()->id)
            ->where('item_type', 'auction-lot')
            ->pluck('item_id')
            ->all();

        $products = $products->map(
            function ($product)
            use ($auctionLots, $watchingAuctionIDs) {
                $auctionLot = $auctionLots[$product->_id];

                // Safely extract nested values with null checks
                $bidSettings = $auctionLot->bid_incremental_settings ?? [];
                $estimatePrice = $bidSettings['estimate_price'] ?? [];

                $product->fill([
                    'auction_lot_id' => $auctionLot->_id,
                    'current_bid' => $auctionLot->current_bid,
                    'is_reserve_price_met' => $auctionLot->current_bid >= $auctionLot->reserve_price,
                    'title' => $auctionLot->title,
                    'short_description' => $auctionLot->short_description,
                    'long_description' => $auctionLot->long_description,
                    'bid_incremental_settings' => $bidSettings,
                    'start_datetime' => $auctionLot->start_datetime,
                    'end_datetime' => $auctionLot->end_datetime,
                    'lot_number' => $auctionLot->lot_number,
                    // 'status' => $auctionLot->status,
                    // 'is_disabled' => $auctionLot->is_disabled,
                    // 'is_closed' => $auctionLot->is_closed,
                    'sold_price' => $auctionLot->sold_price,
                    'commission' => $auctionLot->commission,
                    'max_estimated_price' => $estimatePrice['max'] ?? 0,
                    'min_estimated_price' => $estimatePrice['min'] ?? 0,
                    'auction_lots' => [$auctionLot],
                    'starting_price' => $auctionLot->starting_price,
                    // 'local_discount_type' => null,
                    // 'global_discount' => null,
                    // 'rating' => null,
                    // 'review_count' => 0,
                    // 'inventory_count' => 0,
                    // 'is_liked' => false,
                    // 'discounted_price' => "0",
                    'is_bid_placed' => $auctionLot->is_bid_placed,
                    'watchlist_item_count' => $auctionLot->watchlist_item_count,
                    'is_watching' => in_array($auctionLot->_id, $watchingAuctionIDs, true),
                    'store' => $this->store
                ]);

                unset($product->reserve_price);

                return $product;
            }
        );

        // Return data
        return $products;
    }

    public function getRelatedAuctionProductsUrls(Request $request)
    {
        // Extract attributes from $request
        $productID = $request->input('product_id');
        $storeID = $request->route('store_id');
        $excludedProductIDs = $request->input('exclude_ids', []);
        $itemsPerPage = $request->input('items_per_page');

        // Append to excluded Product
        $excludedProductIDs[] = $productID;

        // Get Product(s) registered for this auction/store
        $productIDs = AuctionLot::where('store_id', $storeID)
            ->statuses([Status::ARCHIVED, Status::ACTIVE])
            ->whereNotIn('product_id', $excludedProductIDs)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        // Get data from input 
        $originalProduct = Product::find($productID);
        $originalProductCategoryIDs = $originalProduct->category_ids;
        $store = Store::find($storeID);

        /*
        *   Stage 1:
        *   Append related_score per product based on intersected related_slugs
        */
        $parentCategoryOrdering = $store->parent_category_ordering ?? [];
        $parentCategoryOrdering = array_unique($parentCategoryOrdering);

        if (!is_null($parentCategoryOrdering) && count($parentCategoryOrdering) > 0) {
            $parentCategoryCount = count($parentCategoryOrdering);

            // First category_id in $parentCategoryOrdering must be Brand Parent Category
            $brandParentCategoryID = $parentCategoryOrdering[0];
            $brandChildrenCategoryIDs = Category::where('parent_id', $brandParentCategoryID)->pluck('_id')->all();

            // Check if this product assigned to any children categories from Brand Parent Category
            $doesProductHaveBrandCategory = false;
            if (array_intersect($originalProductCategoryIDs, $brandChildrenCategoryIDs)) {
                $doesProductHaveBrandCategory = true;
            }

            $childrenCategorySets = [];

            // This is weighting for Brand Category
            if ($doesProductHaveBrandCategory == true) {
                // Add weighting for matching Brand children category
                $childrenCategorySets[] = [
                    'weighting' => pow(2, $parentCategoryCount),
                    'ids' =>  array_intersect($originalProductCategoryIDs, $brandChildrenCategoryIDs)
                ];

                // Add weighting for mismatching Brand children category
                $childrenCategorySets[] = [
                    'weighting' => pow(2, $parentCategoryCount - 1),
                    'ids' => array_values(array_diff($brandChildrenCategoryIDs, $originalProductCategoryIDs))
                ];
            }

            // Remove first item from the Category Ordering
            array_shift($parentCategoryOrdering);
            $parentCategoryOrdering = array_values($parentCategoryOrdering);

            // For the rests of Parent Categories, append weighting score
            $weightingBaseExponent = $parentCategoryCount - 1;
            foreach ($parentCategoryOrdering as $key => $parentCategoryID) {
                $childrenCategoryIDs = Category::where('parent_id', $parentCategoryID)->pluck('_id')->all();
                $matchingChildrenCategoryIDs = array_intersect($originalProductCategoryIDs, $childrenCategoryIDs);

                $weighting = pow(2, $weightingBaseExponent - $key - 1);
                if (count($matchingChildrenCategoryIDs) > 0) {
                    $childrenCategorySets[] = [
                        'weighting' => $weighting,
                        'ids' =>  array_values($matchingChildrenCategoryIDs)
                    ];
                }
            }

            $products = Product::find($productIDs);
            foreach ($products as $product) {
                // Append default value
                $product->related_category_score = 0;
                $productCategoryIDs = $product->category_ids;

                foreach ($childrenCategorySets as $set) {
                    if (!empty(array_intersect($set['ids'], $productCategoryIDs))) {
                        $product->related_category_score += $set['weighting'];
                    }
                }

                // foreach ($childrenCategorySets as $set) {
                //     foreach ($set['ids'] as $id) {
                //         if (in_array($id, $productCategoryIDs)) {
                //             $product->related_category_score += $set['weighting'];
                //             break;
                //         }
                //     }
                // }
            }

            $products = $products->filter(function ($product) {
                return $product->related_category_score > 0;
            });
            $products = $products->sortByDesc('related_category_score');
            $request['sort_by'] = 'default';
        } else {
            $products = Product::find($productIDs);
        }

        /*
        *   Stage 3:
        *   Generate URLs
        */
        $productIDsSet = $products
            ->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($productIDsSet as $IDsSet) {
            $url = route('paraqon.products.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all(),
                'sort_by' => 'default'
            ]);
            $urls[] = $url;
        }

        // Return urls
        return $urls;
    }

    public function getAuctionProductsByIDs(Request $request)
    {
        // Extract attributes from $request
        $productIDs = $request->ids;

        // Append attributes to each Product
        $products = $this->getProductsInfoByAggregation($productIDs, $this->store->_id);

        $products = $products->filter(function ($item) {
            return $item->auction_lot_id != '0';
        })->values();

        // Get WatchlistItem 
        $customer = $this->customer();
        $watchingAuctionLotIDs = WatchlistItem::where('customer_id', $customer->id)
            ->where('item_type', 'auction-lot')
            ->get()
            ->pluck('item_id')
            ->all();

        foreach ($products as $product) {
            $auctionLotID = $product->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);

            $product->current_bid = $auctionLot->getCurrentBidPrice();
            $product->is_reserve_price_met = $product->current_bid >= $product->reserve_price;

            $product->title = $auctionLot->title;
            $product->short_description = $auctionLot->short_description;
            $product->long_description = $auctionLot->long_description;
            $product->bid_incremental_settings = $auctionLot->bid_incremental_settings;
            $product->start_datetime = $auctionLot->start_datetime;
            $product->end_datetime = $auctionLot->end_datetime;
            $product->lot_number = $auctionLot->lot_number;
            $product->status = $auctionLot->status;
            $product->is_disabled = $auctionLot->is_disabled;
            $product->is_closed = $auctionLot->is_closed;

            $product->sold_price = $auctionLot->sold_price;
            $product->commission = $auctionLot->commission;

            $product->max_estimated_price = data_get($auctionLot, 'bid_incremental_settings.estimate_price.max') ?? 0;
            $product->min_estimated_price = data_get($auctionLot, 'bid_incremental_settings.estimate_price.min') ?? 0;

            // is_watching
            $product->is_watching = in_array($auctionLotID, $watchingAuctionLotIDs);

            unset(
                $product->bids,
                $product->valid_bid_values,
                $product->reserve_price
            );
        }

        $productMap = array_column($products->all(), null, 'id');
        $sortedProducts = array_map(fn($id) => $productMap[$id], $productIDs);

        // Return data
        return $sortedProducts;
    }

    public function getAllWishlistAuctionLots(Request $request)
    {
        // Extract attributes from $request
        $categoryIDs = $request->input('category_ids', []);
        $keyword = $request->input('keyword');
        if ($keyword === "") $keyword = "*";
        $gate = $request->input('logic_gate', 'OR');
        $slug = $request->input('slug', 'by-keyword-relevance');

        // Get sorting attributes via slugs
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

        // Get matching keywords from Typesense
        $matchingProductIDs = [];
        if (!is_null($keyword)) {
            $typesense = new TypeSenseSearchEngine('products');
            $matchingProductIDs = $typesense->getIDsFromSearch(
                $keyword,
                'title.en,title.zh,title.cn'
            );
        }

        // Get authenticated User information
        $customer = $this->customer();

        // Get WishlistItem(s)
        $wishlistItems = $customer->wishlistItems()
            ->byStore($this->store)
            ->when($categoryIDs, function ($query) use ($categoryIDs) {
                return $query->whereHas('product', function ($query2) use ($categoryIDs) {
                    return $query2->whereHas('categories', function ($query3) use ($categoryIDs) {
                        return $query3->objectIDs($categoryIDs);
                    });
                });
            })
            ->when($keyword, function ($query) use ($matchingProductIDs) {
                return $query->byProductIDs($matchingProductIDs);
            })
            ->get();

        // Get Products
        $productIDs = $wishlistItems->pluck('product_id')->all();
        if (count($productIDs) == 0) {
            return new Collection();
        }

        $products = $this->getProductsInfoByAggregation($productIDs, $this->store->_id);
        $products = $products->filter(function ($item) {
            return $item->auction_lot_id != '0';
        })->values();

        foreach ($products as $product) {
            $auctionLotID = $product->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);
            $product->current_bid = $auctionLot->getCurrentBidPrice();
            $product->is_reserve_price_met = $product->current_bid >= $product->reserve_price ? true : false;
            $product->lot_number = $auctionLot->lot_number;

            unset(
                $product->bids,
                $product->valid_bid_values,
                $product->reserve_price
            );
        }

        // Return data
        return $products;
    }

    public function getProductDetails(Request $request)
    {
        /** @var Product $product */
        $product = Product::find($request->route('product_id'));
        if (is_null($product)) return response()->json(['message' => 'Product not found'], 404);
        if ($product->status !== Status::ACTIVE) return response()->json(['message' => 'Product is not available for public'], 404);

        // Append attributes for Product
        $product->is_liked = $this->customer()->isWishlistItemExists($product, $this->store);
        $product->appendDisplayableFieldsForCustomer($this->store);

        // Append Categories
        $product->categories = Category::find($product->category_ids, ['_id', 'title']);

        // Get active ProductVariant(s) by Product
        /** @var Collection $variants */
        $variants = $product->variants()->statusActive()->get();

        // Append attributes to each ProductVariant
        /** @var ProductVariant $variant */
        foreach ($variants as $variant) {
            $variant->appendDisplayableFieldsForCustomer($this->store);
        }
        $product->variants = $variants;

        return $product;
    }

    private function getProductsInfoByAggregation(array $productIDs, ?string $storeID = null)
    {
        $productIDs = array_values($productIDs);

        // Get Products 
        if (count($productIDs) == 0) return new Collection();

        $products = Product::raw(function ($collection) use ($productIDs, $storeID) {
            $aggregate = [];

            // Convert ObjectIDs to String
            $aggregate[]['$addFields'] = [
                '_id' => [
                    '$toString' => '$_id'
                ]
            ];

            // Find matching IDs
            if (count($productIDs) > 0) {
                $aggregate[]['$match'] = [
                    '_id' => [
                        '$in' => $productIDs
                    ]
                ];
            }

            // Get AuctionLot
            $aggregate[]['$lookup'] = [
                'from' => 'auction_lots',
                'let' => ['product_id' => '$_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    !is_null($storeID) ? ['$eq' => ['$store_id', $storeID]] : [],
                                    ['$eq' => ['$product_id', '$$product_id']],
                                    [
                                        '$in' => [
                                            '$status',
                                            [Status::ACTIVE, Status::ARCHIVED]
                                        ]
                                    ]
                                ],
                            ],
                        ],
                    ],
                ],
                'as' => 'auction_lots',
            ];

            // Get Bid
            $aggregate[]['$lookup'] = [
                'from' => 'bids',
                'let' => ['product_id' => '$_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    // ['$eq' => ['$store_id', $storeID]],
                                    ['$eq' => ['$product_id', '$$product_id']],
                                    ['$eq' => ['$is_hidden', false]],
                                ],
                            ],
                        ],
                    ],
                ],
                'as' => 'bids',
            ];

            $aggregate[]['$addFields'] = [
                'starting_price' => [
                    '$last' => '$auction_lots.starting_price'
                ],
                'reserve_price' => [
                    '$last' => '$auction_lots.reserve_price'
                ],
                'valid_bid_values' => [
                    '$map' => [
                        'input' => '$bids',
                        'as' => 'bid',
                        'in' => '$$bid.bid'
                    ]
                ]
            ];

            // Get ProductVariants
            $aggregate[]['$lookup'] = [
                'from' => 'product_variants',
                'let' => ['product_id' => '$_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$eq' => ['$status', 'ACTIVE']],
                                    ['$eq' => ['$product_id', '$$product_id']],
                                ],
                            ],
                        ],
                    ],
                ],
                'as' => 'variants',
            ];

            // Get first ACTIVE ProductVariant
            $aggregate[]['$addFields'] = [
                'first_product_variant_id' => [
                    '$toString' => ['$first' => '$variants._id'],
                ],
            ];

            // Get ProductVariantDiscount(s)
            $aggregate[]['$lookup'] = [
                'from' => 'product_variant_discounts',
                'let' => ['product_variant_id' => '$first_product_variant_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$lt' => ['$start_datetime', '$$NOW']],
                                    ['$gte' => ['$end_datetime', '$$NOW']],
                                    ['$eq' => ['$status', 'ACTIVE']],
                                    [
                                        '$eq' => [
                                            '$product_variant_id',
                                            '$$product_variant_id',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'as' => 'local_discounts',
            ];

            // Get GlobalDiscounts
            $aggregate[]['$lookup'] = [
                'from' => 'discount_templates',
                'let' => ['product_variant_id' => '$first_product_variant_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    // [
                                    //     '$in' => [
                                    //         $storeID,
                                    //         '$store_ids',
                                    //     ],
                                    // ],
                                    ['$lt' => ['$start_datetime', '$$NOW']],
                                    ['$gte' => ['$end_datetime', '$$NOW']],
                                    ['$eq' => ['$status', 'ACTIVE']],
                                    [
                                        '$eq' => [
                                            '$$product_variant_id',
                                            '$x.product_variant_id',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'as' => 'global_discounts',
            ];

            // Get Review(s)
            $aggregate[]['$lookup'] = [
                'from' => 'reviews',
                'let' => ['product_id' => '$_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$eq' => ['$status', 'ACTIVE']],
                                    ['$eq' => ['$model_type_id', '$$product_id']],
                                ],
                            ],
                        ],
                    ],
                ],
                'as' => 'reviews',
            ];

            // Append Info
            $aggregate[]['$addFields'] = [
                'price' => ['$ifNull' => [['$first' => '$variants.price'], 0]],
                'point' => ['$ifNull' => [['$first' => '$variants.point'], 0]],
                'is_free_shipping' => ['$ifNull' => [['$first' => '$variants.is_free_shipping'], false]],
                'local_discount_type' => ['$ifNull' => [['$first' => '$local_discounts.type'], null]],
                'local_discount_value' => ['$ifNull' => [['$first' => '$local_discounts.value'], 0]],
                'global_discount' => [
                    '$cond' => [
                        'if' => ['$eq' => [['$size' => '$global_discounts'], 0]],
                        'then' => null,
                        'else' => ['$first' => '$global_discounts'],
                    ],
                ],
                'rating' => ['$avg' => '$reviews.rating'],
                'review_count' => ['$size' => '$reviews'],
                // 'inventory_count' => ['$sum' => '$inventories.qty'],
                'inventory_count' => 0,
                'is_liked' => false,
                'is_watching' => false,
            ];

            // Append discounted_price
            $aggregate[]['$addFields'] = [
                'discounted_price' => [
                    '$switch' => [
                        'branches' => [
                            [
                                'case' => [
                                    '$eq' => ['$local_discount_type', ProductVariantDiscountType::PRICE]
                                ],
                                'then' => [
                                    '$subtract' => [
                                        '$price',
                                        '$local_discount_value'
                                    ]
                                ]
                            ],
                            [
                                'case' => [
                                    '$eq' => ['$local_discount_type', ProductVariantDiscountType::PERCENTAGE]
                                ],
                                'then' => [
                                    '$divide' => [
                                        [
                                            '$multiply' => [
                                                '$price',
                                                [
                                                    '$subtract' => [
                                                        100,
                                                        '$local_discount_value'
                                                    ]
                                                ]
                                            ]
                                        ],
                                        100
                                    ]
                                ]
                            ]
                        ],
                        'default' => '$price'
                    ],
                ]
            ];

            $aggregate[]['$addFields'] = [
                'discounted_price' => [
                    '$cond' => [
                        'if' => [
                            '$lte' => [
                                '$discounted_price',
                                0
                            ],
                        ],
                        'then' =>  '0',
                        'else' => [
                            '$toString' => [
                                '$round' => [
                                    '$discounted_price',
                                    2
                                ]
                            ]
                        ],
                    ],
                ]
            ];

            $aggregate[]['$addFields'] = [
                // 'current_bid' => [
                //     '$cond' => [
                //         'if' => [
                //             '$gt' => [
                //                 ['$size' => '$auction_lots'],
                //                 0
                //             ]
                //         ],
                //         'then' => ['$first' => '$auction_lots.current_bid'],
                //         'else' => 0
                //     ],
                // ],
                // 'is_reserve_price_met' => [
                //     '$cond' => [
                //         'if' => [
                //             '$gte' => [
                //                 ['$first' => '$auction_lots.current_bid'],
                //                 ['$first' => '$auction_lots.reserve_price'],
                //             ],
                //         ],
                //         'then' => true,
                //         'else' => false
                //     ],
                // ],
                'is_bid_placed' => ['$last' => '$auction_lots.is_bid_placed'],
                'auction_lot_id' => [
                    '$cond' => [
                        'if' => [
                            '$gt' => [
                                ['$size' => '$auction_lots'],
                                0
                            ]
                        ],
                        'then' => [
                            '$toString' => ['$last' => '$auction_lots._id']
                        ],
                        'else' => '0'
                    ],
                ],
                'store_id' => [
                    '$cond' => [
                        'if' => [
                            '$gt' => [
                                ['$size' => '$auction_lots'],
                                0
                            ]
                        ],
                        'then' => [
                            '$toString' => ['$last' => '$auction_lots.store_id']
                        ],
                        'else' => '0'
                    ],
                ],
            ];

            // Get WishlistItem(s)
            $aggregate[]['$lookup'] = [
                'from' => 'watchlist_items',
                'localField' => 'auction_lot_id',
                'foreignField' => 'item_id',
                'as' => 'watchlist_items',
            ];

            // Append is_liked field
            if (Auth::check()) {
                // Get authenticated User information
                $customer = $this->customer();

                $aggregate[]['$addFields'] = [
                    'watchlist_item_count' => ['$size' => '$watchlist_items'],
                    'is_watching' => [
                        '$cond' => [
                            'if' => [
                                '$in' => [
                                    $customer->_id,
                                    '$watchlist_items.customer_id',
                                ],
                            ],
                            'then' => true,
                            'else' => false,
                        ],
                    ],
                ];
            }

            // Get store(s)
            $aggregate[]['$lookup'] = [
                'from' => 'stores',
                'let' => ['store_id' => '$store_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$eq' => [['$toString' => '$_id'], '$$store_id']
                            ]
                        ]
                    ]
                ],
                'as' => 'store',
            ];

            $aggregate[]['$unwind'] = [
                'path' => '$store',
                'preserveNullAndEmptyArrays' => true
            ];

            // Hide attributes
            $hiddenKeys = [
                'discount',
                'remarks',
                'status',
                'is_system',
                'deleted_at',
                'variants',
                'local_discounts',
                'local_discount_value',
                'global_discounts',
                'reviews',
                'inventories',
                // 'watchlist_items',
                'valid_bid_values',
                'bids',
                // 'auction_lots',
                'listing_status',
                // 'owned_by_customer_id',
                'store_id'
            ];
            $aggregate[]['$project'] = array_merge(...array_map(function ($item) {
                return [$item => false];
            }, $hiddenKeys));

            return $collection->aggregate($aggregate);
        });

        return $products;
    }
}
