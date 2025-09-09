<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Faker\Generator as Faker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Constants
use App\Constants\Model\CheckoutType;
use App\Constants\Model\LoginType;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;

// Models
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\Deposit;

// Traits
use App\Traits\Utils\RoundingTrait;

class SeederController extends Controller
{
    use RoundingTrait;

    public $faker;

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
        for ($i = 0; $i <= 40; $i++) {
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

            $bidHistoryAttributes = [
                'auction_lot_id' => $lot->_id,
                'current_bid' => $lot->starting_price,
                'histories' => []
            ];
            BidHistory::create($bidHistoryAttributes);
        }

        // Create Category
        $storeCategories = [];
        foreach ($products as $product) {
            $category = $this->createCategory();
            $category->associateStore($store);

            // Assignment
            $category->products()->attach([$product->_id]);
            $storeCategories[] = $category;
        }

        // Create AuctionRegistrationRecord
        $validCustomers = Customer::whereHas('account', function ($query) {
            return $query->where('username', '!=', 'Guest')
                ->whereHas('user', function ($query2) {
                    $query2->where('type', '!=', LoginType::TEMP);
                });
        })
            ->latest()
            ->take(10)
            ->with(['account'])
            ->get();

        $paddleID = 1;
        foreach ($validCustomers as $customer) {
            $recordAttributes = [
                'requested_by_customer_id' => $customer->_id,
                'store_id' => $store->_id,
                'paddle_id' => $paddleID,
                'reply_status' => ReplyStatus::APPROVED
            ];
            $paddleID++;

            $record = AuctionRegistrationRequest::create(
                $recordAttributes
            );

            $depositAttributes = [
                'requested_by_customer_id' => $customer->_id,
                'auction_registration_request_id' => $record->_id,
                'payment_method' => OrderPaymentMethod::OFFLINE,
                'amount' => 10000,
                'currency' => 'HKD',
                'offline' => [
                    'image' => 'https://starsnet-development.oss-cn-hongkong.aliyuncs.com/jpg/6d11fa36-42a2-4ed7-bcb4-42980f2a9252.jpg',
                    'uploaded_at' => now()->toISOString(),
                    'api_response' => null,
                ],
                'reply_status' => ReplyStatus::APPROVED
            ];
            $deposit = Deposit::create($depositAttributes);
            $deposit->updateStatus('submitted');
            $deposit->updateStatus('pending');
            $deposit->updateStatus('on-hold');
        }

        // Create Bid and BidHistory
        foreach ($auctionLots as $lot) {
            $startingPrice = (int) $lot->starting_price;
            $bidPrice = $startingPrice;

            $randomCustomers = collect($validCustomers)
                ->shuffle()
                ->take(7);

            foreach ($randomCustomers as $customer) {
                $bidPrice += $this->faker->numberBetween(1, 20) * 100;

                $bidAttributes = [
                    'auction_lot_id' => $lot->_id,
                    'customer_id' => $customer->_id,
                    'store_id' => $store->_id,
                    'product_id' => $lot->product_id,
                    'product_variant_id' => $lot->product_variant_id,
                    'bid' => $bidPrice,
                    'type' => $this->faker->randomElement(['MAX', 'DIRECT', 'ADVANCED']),
                ];

                $bid = Bid::create($bidAttributes);

                // Update History
                $bidHistory = $lot->bidHistory()->first();
                $bidHistoryItemAttributes = [
                    'winning_bid_customer_id' => $customer->_id,
                    'current_bid' => $bidPrice
                ];
                $bidHistory->histories()->create($bidHistoryItemAttributes);
                $bidHistory->update(['current_bid' => $bidPrice]);

                // Update Lot
                $lot->update(
                    [
                        'latest_bid_customer_id' =>
                        $customer->_id,
                        'winning_bid_customer_id' =>
                        $customer->_id,
                        'current_bid' => $bidPrice
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Done'
        ]);
    }

    public function generateAuctionOrders(Request $request)
    {
        // Get Store
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        // Get Auction Lots
        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->whereNotNull('winning_bid_customer_id')
            ->get();

        // Get unique winning_bid_customer_id
        $winningCustomerIDs = $unpaidAuctionLots
            ->pluck('winning_bid_customer_id')
            ->unique()
            ->values()
            ->all();

        // Generate OFFLINE order by system
        $generatedOrderCount = 0;
        foreach ($winningCustomerIDs as $customerID) {
            try {
                // Find all winning Auction Lots
                $winningLots = $unpaidAuctionLots->where('winning_bid_customer_id', $customerID)->all();

                // Add item to Customer's Shopping Cart, with calculated winning_bid + storage_fee
                $customer = Customer::find($customerID);
                foreach ($winningLots as $lot) {
                    $attributes = [
                        'store_id' => $storeID,
                        'product_id' => $lot->product_id,
                        'product_variant_id' => $lot->product_variant_id,
                        'qty' => 1,
                        'winning_bid' => $lot->current_bid,
                    ];
                    $customer->shoppingCartItems()->create($attributes);
                }

                // Get ShoppingCartItem(s)
                $cartItems = $customer->getAllCartItemsByStore($store);

                // Start Shopping Cart calculations
                // Get subtotal Price
                $subtotalPrice = 0;
                $storageFee = 0;

                $SERVICE_CHARGE_MULTIPLIER = 0.1;
                $totalServiceCharge = 0;

                foreach ($cartItems as $item) {
                    // Add keys
                    $item->is_checkout = true;
                    $item->is_refundable = false;
                    $item->global_discount = null;

                    // Get winning_bid, update subtotal_price
                    $winningBid = $item->winning_bid ?? 0;
                    $subtotalPrice += $winningBid;

                    // Update total_service_charge
                    $totalServiceCharge += $winningBid *
                        $SERVICE_CHARGE_MULTIPLIER;
                }
                $totalPrice = $subtotalPrice +
                    $storageFee + $totalServiceCharge;

                // Get shipping_fee, then update total_price
                $shippingFee = 0;
                $totalPrice += $shippingFee;

                // Form calculation data object
                $rawCalculation = [
                    'currency' => 'HKD',
                    'price' => [
                        'subtotal' => $subtotalPrice,
                        'total' => $totalPrice, // Deduct price_discount.local and .global
                    ],
                    'price_discount' => [
                        'local' => 0,
                        'global' => 0,
                    ],
                    'point' => [
                        'subtotal' => 0,
                        'total' => 0,
                    ],
                    'service_charge' => $totalServiceCharge,
                    'storage_fee' => $storageFee,
                    'shipping_fee' => $shippingFee
                ];

                $rationalizedCalculation = $this->rationalizeRawCalculation($rawCalculation);
                $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation); // Round off values

                // Round up calculations.price.total only
                $roundedCalculation['price']['total'] = ceil($roundedCalculation['price']['total']);
                $roundedCalculation['price']['total'] .= '.00';

                // Return data
                $checkoutDetails = [
                    'cart_items' => $cartItems,
                    'gift_items' => [],
                    'discounts' => [],
                    'calculations' => $roundedCalculation,
                    'is_voucher_applied' => false,
                    'is_enough_membership_points' => true
                ];

                // Validate, and update attributes
                $totalPrice = $checkoutDetails['calculations']['price']['total'];
                $paymentMethod = CheckoutType::OFFLINE;

                // Create Order
                $orderAttributes = [
                    'is_paid' => $request->input('is_paid', false),
                    'payment_method' => $paymentMethod,
                    'discounts' => $checkoutDetails['discounts'],
                    'calculations' => $checkoutDetails['calculations'],
                    'delivery_info' => [
                        'country_code' => 'HK',
                        'method' => 'FACE_TO_FACE_PICKUP',
                        'courier_id' => null,
                        'warehouse_id' => null,
                    ],
                    'delivery_details' => [
                        'recipient_name' => null,
                        'email' => null,
                        'area_code' => null,
                        'phone' => null,
                        'address' => null,
                        'remarks' => null,
                    ],
                    'is_voucher_applied' => $checkoutDetails['is_voucher_applied'],
                    'is_system' => true,
                    'payment_information' => [
                        'currency' => 'HKD',
                        'conversion_rate' => 1.00
                    ]
                ];
                $order = $customer->createOrder($orderAttributes, $store);

                // Create OrderCartItem(s)
                $checkoutItems = collect($checkoutDetails['cart_items'])
                    ->filter(function ($item) {
                        return $item->is_checkout;
                    })->values();

                $variantIDs = [];
                foreach ($checkoutItems as $item) {
                    $attributes = $item->toArray();
                    unset($attributes['_id'], $attributes['is_checkout']);

                    // Update WarehouseInventory(s)
                    $variantID = $attributes['product_variant_id'];
                    $variantIDs[] = $variantID;
                    $qty = $attributes['qty'];
                    /** @var ProductVariant $variant */
                    $variant = ProductVariant::find($variantID);
                    $order->createCartItem($attributes);
                }

                // Update Order
                $status = Str::slug(ShipmentDeliveryStatus::SUBMITTED);
                $order->updateStatus($status);

                // Create Checkout
                $checkout = $this->createBasicCheckout($order, $paymentMethod);

                // Delete ShoppingCartItem(s)
                // $variants = ProductVariant::objectIDs($variantIDs)->get();
                // $customer->clearCartByStore($store, $variants->shuffle()->take(1));

                $generatedOrderCount++;
            } catch (\Throwable $th) {
                print($th);
            }
        }

        return response()->json([
            'message' => "Generated All {$generatedOrderCount} Auction Store Orders Successfully"
        ], 200);
    }

    public function createCategory()
    {
        $title = $this->faker->randomElement([
            "Aaa",
            "Bbb",
            "Ccc",
            "Ddd",
            "Eee",
            "Fff",
        ]);

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
        ];

        $category = ProductCategory::create($data);

        return $category;
    }

    public function createAuctionLot(
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

        $startingPrice = $this->faker->numberBetween(1, 10) * 100;
        $reservePrice = $startingPrice * 10;

        $title = $product->title;

        $data = [
            'owned_by_customer_id' => $customerID,
            'product_id' => $productID,
            'product_variant_id' => $variant->_id,
            'store_id' => $storeID,
            'lot_number' => $lotNumber,
            'starting_price' => $startingPrice,
            'reserve_price' => $reservePrice,
            'current_bid' => $startingPrice,
            'start_datetime' => now()->toISOString(),
            'end_datetime' => now()->addMonth()->toISOString(),
            'title' => $title,
            'short_description' => $title,
            'long_description' => $title,
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

    public function createProduct($customerID)
    {
        $title = $this->faker->randomElement([
            "Aaa",
            "Bbb",
            "Ccc",
            "Ddd",
            "Eee",
            "Fff",
        ]);

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
            'images' => ['https://starsnet-development.oss-cn-hongkong.aliyuncs.com/jpg/6d11fa36-42a2-4ed7-bcb4-42980f2a9252.jpg'],
            'is_system' => false,
            'owned_by_customer_id' => $customerID,
            'listing_status' => "AVAILABLE"
        ];

        $product = Product::create($data);
        $variant = $product->variants()->create($data);

        return $product;
    }

    public function createStore()
    {
        $title = $this->faker->randomElement([
            "Aaa",
            "Bbb",
            "Ccc",
            "Ddd",
            "Eee",
            "Fff",
        ]);

        $data = [
            'title' => [
                'en' => $title,
                'zh' => $title,
                'cn' => $title
            ],
            'type' => 'OFFLINE',
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
            'viewing_start_datetime' => now()->toISOString(),
            'viewing_end_datetime' => now()->addMonth()->toISOString(),
            'display_end_datetime' => now()->addMonth()->toISOString(),
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
            'start_datetime' => now()->addHour()->toISOString(),
            'end_datetime' => now()->addMonth()->toISOString(),
        ];

        $store = Store::create($data);
        return $store;
    }

    private function rationalizeRawCalculation(array $rawCalculation)
    {
        return [
            'currency' => $rawCalculation['currency'],
            'price' => [
                'subtotal' => max(0, $rawCalculation['price']['subtotal']),
                'total' => max(0, $rawCalculation['price']['total']),
            ],
            'price_discount' => [
                'local' => $rawCalculation['price_discount']['local'],
                'global' => $rawCalculation['price_discount']['global'],
            ],
            'point' => [
                'subtotal' => max(0, $rawCalculation['point']['subtotal']),
                'total' => max(0, $rawCalculation['point']['total']),
            ],
            'service_charge' => max(0, $rawCalculation['service_charge']),
            'shipping_fee' => max(0, $rawCalculation['shipping_fee']),
            'storage_fee' => max(0, $rawCalculation['storage_fee'])
        ];
    }

    private function createBasicCheckout(Order $order, string $paymentMethod = CheckoutType::ONLINE)
    {
        $attributes = [
            'payment_method' => $paymentMethod
        ];
        /** @var Checkout $checkout */
        $checkout = $order->checkout()->create($attributes);
        return $checkout;
    }
}
