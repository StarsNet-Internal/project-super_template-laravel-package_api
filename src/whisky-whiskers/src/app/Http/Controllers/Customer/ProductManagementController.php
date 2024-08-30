<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

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
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;

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
        $products = $this->getProductsInfoByAggregation($productIDs);

        // Re-calculate current_bid value
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        foreach ($products as $product) {
            $auctionLotID = $product->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);

            $product->current_bid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);
            $product->is_reserve_price_met = $product->current_bid >= $product->reserve_price ?
                true :
                false;

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

        // Get configuraion
        $config = Configuration::slug('main-store-category-ordering')->latest()->first();
        $parentCategoryIDs = $config->category_ids;

        // Get Product(s) registered for this auction/store
        $productIDs = AuctionLot::where('store_id', $storeID)
            ->whereNotIn('product_id', $excludedProductIDs)
            ->pluck('product_id')
            ->all();
        $parentCategoryCount = count($parentCategoryIDs);

        // Create aggregation pipeline stage for matching_score
        $aggregateMatchingScore = [
            'matching_score' => [
                '$add' => []
            ]
        ];

        foreach ($parentCategoryIDs as $key => $parentCategoryID) {
            $childrenCategoryIDs = Category::where('parent_id', $parentCategoryID)->pluck('_id')->all();

            $weightingFactor = 2;
            $weighting = pow(2, $parentCategoryCount - $key - 1) * $weightingFactor;

            $aggregateMatchingScore["matching_score"]['$add'][] = [
                '$multiply' => [
                    [
                        '$size' => [
                            '$setIntersection' =>
                            ['$category_ids', $childrenCategoryIDs]
                        ]
                    ],
                    $weighting
                ]
            ];
        }


        // Get Products 
        $products = Product::raw(
            function ($collection) use ($productIDs, $aggregateMatchingScore) {
                $aggregate = [];

                // Convert ObjectIDs to String
                $aggregate[]['$addFields'] = [
                    '_id' => [
                        '$toString' => '$_id'
                    ]
                ];

                // Find matching Product ids
                $aggregate[]['$match'] = [
                    '_id' => [
                        '$in' => $productIDs
                    ]
                ];

                $aggregate[]['$addFields'] = $aggregateMatchingScore;

                // Sort from highest to lowest
                $aggregate[]['$sort'] = [
                    'matching_score' => -1
                ];

                return $collection->aggregate($aggregate);
            }
        );

        /*
        *   Stage 4:
        *   Generate URLs
        */
        $productIDsSet = $products
            ->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();


        $urls = [];
        foreach ($productIDsSet as $IDsSet) {
            $urls[] = route('whiskywhiskers.products.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all()
            ]);
        }

        // Return urls
        return $urls;
    }

    public function getAuctionProductsByIDs(Request $request)
    {
        // Extract attributes from $request
        $productIDs = $request->ids;

        // Append attributes to each Product
        $products = $this->getProductsInfoByAggregation($productIDs);

        // Re-calculate current_bid value
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        foreach ($products as $product) {
            $auctionLotID = $product->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);
            $product->current_bid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);
            $product->is_reserve_price_met = $product->current_bid >= $product->reserve_price ? true : false;

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
        $products = $this->getProductsInfoByAggregation($productIDs);

        // Re-calculate current_bid value
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        foreach ($products as $product) {
            $auctionLotID = $product->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);
            $product->current_bid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);
            $product->is_reserve_price_met = $product->current_bid >= $product->reserve_price ? true : false;

            unset($product->bids);
            unset($product->valid_bid_values);
            unset($product->reserve_price);
        }

        // Return data
        return $products;
    }

    private function getProductsInfoByAggregation(array $productIDs, ?Store $store = null)
    {
        $store = $store ?? $this->store;
        $productIDs = array_values($productIDs);

        // Extract variables
        $storeID = $store->_id;
        $warehouseIDs = $store->warehouses()->statusActive()->get()->pluck('_id')->all();

        // Get Products 
        $products = Product::raw(function ($collection) use ($productIDs, $storeID, $warehouseIDs) {
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
                                    ['$eq' => ['$store_id', $storeID]],
                                    ['$eq' => ['$product_id', '$$product_id']],
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
                                    ['$eq' => ['$store_id', $storeID]],
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
                    '$arrayElemAt' => [
                        '$auction_lots.starting_price',
                        0
                    ]
                ],
                'reserve_price' => [
                    '$arrayElemAt' => [
                        '$auction_lots.reserve_price',
                        0
                    ]
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
                                    [
                                        '$in' => [
                                            $storeID,
                                            '$store_ids',
                                        ],
                                    ],
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

            // Get WarehouseInventory(s)
            $aggregate[]['$lookup'] = [
                'from' => 'warehouse_inventories',
                'let' => [
                    'product_variant_id' => '$first_product_variant_id',
                    'warehouse_ids' => $warehouseIDs,
                ],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$in' => ['$warehouse_id', '$$warehouse_ids']],
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
                'as' => 'inventories',
            ];

            // Get WishlistItem(s)
            $aggregate[]['$lookup'] = [
                'from' => 'wishlist_items',
                'localField' => '_id',
                'foreignField' => 'product_id',
                'as' => 'wishlist_items',
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
                'inventory_count' => ['$sum' => '$inventories.qty'],
                'wishlist_item_count' => ['$size' => '$wishlist_items'],
                'is_liked' => false,
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
                'is_bid_placed' => ['$first' => '$auction_lots.is_bid_placed'],
                'auction_lot_id' => [
                    '$cond' => [
                        'if' => [
                            '$gt' => [
                                ['$size' => '$auction_lots'],
                                0
                            ]
                        ],
                        'then' => ['$first' => '$auction_lots._id'],
                        'else' => null
                    ],
                ]
            ];

            // Append is_liked field
            if (Auth::check()) {
                // Get authenticated User information
                $customer = $this->customer();

                $aggregate[]['$addFields'] = [
                    'is_liked' => [
                        '$cond' => [
                            'if' => [
                                '$in' => [
                                    $customer->_id,
                                    '$wishlist_items.customer_id',
                                ],
                            ],
                            'then' => true,
                            'else' => false,
                        ],
                    ],
                ];
            }

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
                // 'wishlist_items',
                // 'bids',
                'auction_lots',
                'listing_status',
                // 'owned_by_customer_id',
            ];
            $aggregate[]['$project'] = array_merge(...array_map(function ($item) {
                return [$item => false];
            }, $hiddenKeys));

            return $collection->aggregate($aggregate);
        });

        return $products;
    }
}
