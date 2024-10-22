<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Configuration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\WatchlistItem;

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

        // Re-calculate current_bid value
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

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

    public function getRelatedAuctionProductsUrls(Request $request)
    {
        // Extract attributes from $request
        $productID = $request->input('product_id');
        $storeID = $request->route('store_id');
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
        $store = Store::find($storeID);

        if (!is_null($product)) {
            // Get related ProductCategory(s) by Product and within Store
            $relatedCategories = $product->categories()
                ->storeID($store)
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
        $productIDsSet = collect($products)
            ->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($productIDsSet as $IDsSet) {
            $url = route('paraqon.products.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all(),
                'sort_by' => 'a'
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

        // Re-calculate current_bid value
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

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

            // is_watching
            $product->is_watching = in_array($auctionLotID, $watchingAuctionLotIDs);

            unset(
                $product->bids,
                $product->valid_bid_values,
                $product->reserve_price
            );
        }

        // Return data
        return $products;
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

        // Re-calculate current_bid value
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

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
