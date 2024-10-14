<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\ShipmentDeliveryStatus;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Configuration;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShoppingCartItem;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;

use Faker\Generator as Faker;

class SeederController extends Controller
{
    private $faker;

    public function __construct()
    {
        $faker = new Faker;
        $faker->addProvider(new \Faker\Provider\Base($faker));
        $this->faker = $faker;
    }


    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from seeder/paraqon'
        ], 200);
    }

    public function fromStoreToOrders()
    {
        // Constants
        $customerID = '66fd01a17dc9e8ed9901d575';

        // Create Store
        $store = $this->createStore();

        // Create Product
        $products = [];
        for ($i = 0; $i < 2; $i++) {
            $product = $this->createProduct($customerID);
            $products[] = $product;
        }

        // Create AuctionLot
        $auctionLots = [];
        foreach ($products as $product) {
            $lot = $this->createAuctionLot(
                $store->id,
                $product->id,
                $customerID
            );
            $auctionLots[] = $lot;
        }

        // Create Category
    }

    private function createAuctionLot(
        $storeID,
        $productID,
        $customerID
    ) {
        $store = Store::find($storeID);

        $product = Product::find($productID);
        $variant = $product->variants()->first();

        $existingLotCount = AuctionLot::where('store_id', $storeID)
            ->where('product_id', $productID)
            ->count();
        $lotNumber = $existingLotCount + 1;

        $startingPrice = $this->faker->numberBetween(1, 20) * 100;
        $reservePrice = $startingPrice * 100;

        $title = $product->title;

        $data = [
            "lot_number" => $lotNumber,
            "product_id" => $productID,
            "product_variant_id" => $variant->_id,
            "store_id" => $storeID,
            "owned_by_customer_id" => $customerID,
            "starting_price" => $startingPrice,
            "reserve_price" => $reservePrice,
            'title' =>  [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'short_description' =>  [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'long_description' =>  [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'auction_time_settings' => [
                'allow_duration' => [
                    'days' => 1,
                    'hours' => 0,
                    'mins' => 0
                ],
                'extension' => [
                    'days' => 0,
                    'hours' => 0,
                    'mins' => 10
                ]
            ],
            'bid_incremental_settings' => [
                'estimate_price' => [
                    'min' => 15000,
                    'max' => 50000
                ],
                'increments' => [
                    [
                        'from' => 0,
                        'to' => 9999,
                        'increment' => 500
                    ],
                    [
                        'from' => 10000,
                        'to' => 99999,
                        'increment' => 1000
                    ],
                    [
                        'from' => 100000,
                        'to' => 99999999,
                        'increment' => 5000
                    ]
                ]
            ],
            'documents' => [
                [
                    'type' => 'certificate',
                    'value' => 'https://starsnet-development.oss-cn-hongkong.aliyuncs.com/pdf/a1bded2a-5193-46be-a09d-1b69cd3590e0.pdf'
                ],
                [
                    'type' => 'conditions',
                    'value' => 'https://starsnet-development.oss-cn-hongkong.aliyuncs.com/pdf/a1bded2a-5193-46be-a09d-1b69cd3590e0.pdf'
                ]
            ],
            'attributes' => [
                [
                    'title' => [
                        'en' => 'Style',
                        'zh' => 'Style',
                        'cn' => 'Style'
                    ],
                    'value' => [
                        'cn' => 'Antique, Art Deco',
                        'en' => 'Antique, Art Deco',
                        'zh' => 'Antique, Art Deco'
                    ]
                ]
            ],
            'shipping_costs' => [
                [
                    'area' => 'HK',
                    'cost' => 250
                ],
                [
                    'area' => 'NA',
                    'cost' => 400
                ],
                [
                    'area' => 'EU',
                    'cost' => 600
                ]
            ]
        ];

        $lot = AuctionLot::create($data);
        return $lot;
    }

    private function createProduct($customerID)
    {
        $title = $this->faker()->firstName();

        $data = [
            'title' =>  [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'short_description' =>  [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'long_description' =>  [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'is_system' => false,
            'owned_by_customer_id' => $customerID,
            'listing_status' => "AVAILABLE"
        ];

        $product = Product::create($data);
        $variant = $product->variants()->create($data);

        return $product;
    }

    private function createStore()
    {
        $data = [
            'store' => [
                'title' => [
                    'en' => '2024 November Auction Dev',
                    'zh' => '2024 November Auction Dev',
                    'cn' => '2024 November Auction Dev'
                ],
                'short_description' => [
                    'en' => 'short desc',
                    'zh' => 'short desc',
                    'cn' => 'short desc'
                ],
                'long_description' => [
                    'en' => 'long desc',
                    'zh' => 'long desc',
                    'cn' => 'long desc'
                ],
                'auction_location' => [
                    'en' => 'Hong Kong',
                    'zh' => 'Hong Kong',
                    'cn' => 'Hong Kong'
                ],
                'auction_address' => [
                    'en' => '432 Park Avenue, Hong Kong, 10022 (map)',
                    'zh' => '432 Park Avenue, Hong Kong, 10022 (map)',
                    'cn' => '432 Park Avenue, Hong Kong, 10022 (map)'
                ],
                'viewing_location' => [
                    'en' => 'Hong Kong',
                    'zh' => 'Hong Kong',
                    'cn' => 'Hong Kong'
                ],
                'viewing_address' => [
                    'en' => '432 Park Avenue, Hong Kong, 10022 (map)',
                    'zh' => '432 Park Avenue, Hong Kong, 10022 (map)',
                    'cn' => '432 Park Avenue, Hong Kong, 10022 (map)'
                ],
                'viewing_start_datetime' => now(),
                'viewing_end_datetime' => now()->addMonth(),
                'opening_hours' => [
                    [
                        'title' => [
                            'en' => 'Monday',
                            'zh' => 'Monday',
                            'cn' => 'Monday'
                        ],
                        'start_time' => '10:00',
                        'end_time' => '18:00'
                    ],
                    [
                        'title' => [
                            'en' => 'Sunday',
                            'zh' => 'Sunday',
                            'cn' => 'Sunday'
                        ],
                        'start_time' => '10:00',
                        'end_time' => '18:00'
                    ]
                ],
                'contact_info' => [
                    'name' => [
                        'en' => 'Stephen Woo',
                        'zh' => 'Stephen Woo',
                        'cn' => 'Stephen Woo'
                    ],
                    'position' => [
                        'en' => 'CEO',
                        'zh' => 'CEO',
                        'cn' => 'CEO'
                    ],
                    'email' => 'stephen.woo@paraqon.com',
                    'phone' => '+852 12345678'
                ],
                'auction_time_settings' => [
                    'allow_duration' => [
                        'days' => 5,
                        'hours' => 20,
                        'mins' => 30
                    ],
                    'extension' => [
                        'days' => 0,
                        'hours' => 0,
                        'mins' => 15
                    ]
                ],
                'bid_incremental_settings' => [
                    'estimate_price' => [
                        'min' => 15000,
                        'max' => 50000
                    ],
                    'reserved_price' => [
                        'percentage' => 20,
                        'condition' => 'above',
                        'estimate' => 'min'
                    ],
                    'starting_price' => [
                        'percentage' => 20,
                        'condition' => 'below',
                        'estimate' => 'min'
                    ],
                    'increments' => [
                        [
                            'from' => 0,
                            'to' => 9999,
                            'increment' => 500
                        ],
                        [
                            'from' => 10000,
                            'to' => 99999,
                            'increment' => 1000
                        ],
                        [
                            'from' => 100000,
                            'to' => 99999999,
                            'increment' => 5000
                        ]
                    ]
                ],
                'auction_type' => 'ONLINE',
                'deposit_fee' => 500,
                'deposit_currency' => 'HKD',
                'start_datetime' => now()->addHour(),
                'end_datetime' => now()->addMonth()
            ]
        ];

        $store = Store::create($data);
        return $store;
    }
}
