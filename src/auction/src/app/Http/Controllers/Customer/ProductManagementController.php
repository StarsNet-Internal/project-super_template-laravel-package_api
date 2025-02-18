<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Order;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductManagementController extends Controller
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait,
        StoreDependentTrait;

    use Cacheable;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $this->store = self::getStoreByValue($request->route('store_id'));
    }

    private function getProductsInfoByAggregation(array $productIDs)
    {
        // Extract variables
        $productIDs = array_values($productIDs);
        $storeID = $this->store->_id;
        $warehouseIDs = $this->store->warehouses()->statusActive()->get()->pluck('_id')->all();

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
                'cost' => ['$ifNull' => [['$first' => '$variants.cost'], 0]],
                'is_free_shipping' => ['$ifNull' => [['$first' => '$variants.is_free_shipping'], false]],
                'local_discount_type' => ['$ifNull' => [['$first' => '$local_discounts.type'], null]],
                'local_discount_value' => ['$ifNull' => [['$first' => '$local_discounts.value'], 0]],
                'end_datetime' => [
                    '$dateToString' => [
                        'date' => [
                            '$ifNull' => [
                                ['$toDate' => ['$first' => '$local_discounts.end_datetime']],
                                ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'hour', 'amount' => 12]]
                            ]
                        ],
                        'format' => '%Y-%m-%dT%H:%M:%S.%LZ',
                        'timezone' => 'UTC'
                    ]
                ],
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
                'wishlist_items'
            ];
            $aggregate[]['$project'] = array_merge(...array_map(function ($item) {
                return [$item => false];
            }, $hiddenKeys));

            return $collection->aggregate($aggregate);
        });

        return $products;
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
                'title.en,title.zh'
            );
            if (count($productIDsByKeyword) === 0) return new Collection();
            $productIDs = array_intersect($productIDs, $productIDsByKeyword);
        }
        if (count($productIDs) === 0) return new Collection();

        // Filter Product(s)
        $products = $this->getProductsInfoByAggregation($productIDs);

        foreach ($products as $product) {
            $items = $this->getAuctionHistoryByProductVariantId($product['first_product_variant_id']);
            $product['highest_bid'] = collect($items)->max('subtotal_price') ?? '0.00';
        }

        // Return data
        return $products;
    }

    public function getAuctionHistory(Request $request)
    {
        // Extract attributes from $request
        $variantID = $request->route('product_variant_id');

        return $this->getAuctionHistoryByProductVariantId($variantID);
    }

    public function getAuctionHistoryByProductVariantId($variantID)
    {
        // Get ProductVariant, then validate
        /** @var ProductVariant $variant */
        $variant = ProductVariant::find($variantID);

        if (is_null($variant)) {
            return [];
            return response()->json([
                'message' => 'ProductVariant not found'
            ], 404);
        }

        $orders = Order::where('cart_items.product_variant_id', $variantID)
            ->where('is_paid', true)
            ->with(['customer' => function ($customer) {
                $customer->with(['account']);
            }])
            ->get();

        $filteredCartItems = $orders->flatMap(function ($order) use ($variantID) {
            return collect($order['cart_items'])->filter(function ($cartItem) use ($variantID) {
                return $cartItem['product_variant_id'] === $variantID;
            })->map(function ($cartItem) use ($order) {
                $account = $order['customer']['account'];

                $cartItem['account'] = [
                    'username' => $account['username'],
                    'avatar' => $account['avatar']
                ];

                $extractedFields = [
                    '_id',
                    'qty',
                    'original_price_per_unit',
                    'discounted_price_per_unit',
                    'original_subtotal_price',
                    'subtotal_price',
                    'updated_at',
                    'created_at',
                    'account',
                ];
                $cartItem = collect($cartItem)->only($extractedFields)->toArray();

                return $cartItem;
            });
        });

        return $filteredCartItems;
    }
}
