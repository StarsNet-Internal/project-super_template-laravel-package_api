<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Configuration;
use App\Models\ProductVariant;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\ShipmentDeliveryStatus;

use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Str;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuctionController extends Controller
{
    use RoundingTrait;

    public function updateAuctionStatuses(Request $requests)
    {
        $now = now();

        // Make stores ACTIVE
        $archivedStores = Store::where('type', 'OFFLINE')
            ->where('status', Status::ARCHIVED)
            ->get();

        $archivedStoresUpdateCount = 0;
        foreach ($archivedStores as $store) {
            $startTime = Carbon::parse($store->start_datetime);
            $endTime = Carbon::parse($store->end_datetime);

            if ($now >= $startTime && $now < $endTime) {
                $store->update(['status' => Status::ACTIVE]);
                $archivedStoresUpdateCount++;
            }
        }

        // Make stores ARCHIVED
        $activeStores = Store::where('type', 'OFFLINE')
            ->where('status', Status::ACTIVE)
            ->get();

        $activeStoresUpdateCount = 0;
        foreach ($activeStores as $store) {
            $endTime = Carbon::parse($store->end_datetime);

            if ($now >= $endTime) {
                $store->update(['status' => Status::ARCHIVED]);
                $activeStoresUpdateCount++;
            }
        }

        return response()->json([
            'now_time' => $now,
            'message' => "Updated {$archivedStoresUpdateCount} Auction(s) as ACTIVE, and {$activeStoresUpdateCount} Auction(s) as ARCHIVED"
        ], 200);
    }

    public function archiveAllAuctionLots(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->whereNull('winning_bid_customer_id')
            ->where('is_paid', false)
            ->get();

        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($unpaidAuctionLots as $lot) {
            try {
                $isBidPlaced = $lot->is_bid_placed;
                $currentBid = $lot->getCurrentBidPrice($incrementRulesDocument);

                $product = Product::find($lot->product_id);

                if (
                    !$isBidPlaced || $lot->reserve_price >
                    $currentBid
                ) {
                    // Update Product
                    $product->update(['listing_status' => 'AVAILABLE']);

                    // Update Auction Lot
                    $lot->update([
                        'current_bid' => $currentBid,
                        'status' => 'ARCHIVED'
                    ]);

                    // Create Passed Auction
                    $lot->passedAuctionRecords()->create([
                        'customer_id' => $lot->owned_by_customer_id,
                        'product_id' => $lot->product_id,
                        'product_variant_id' => $lot->product_variant_id,
                        'auction_lot_id' => $lot->_id,
                        'remarks' => 'Not met reserved price'
                    ]);
                } else {
                    // Get final highest bidder info
                    $allBids = $lot->bids()
                        ->where('is_hidden', false)
                        ->get();
                    $highestBidValue = $allBids->pluck('bid')->max();
                    $higestBidderCustomerID = $lot->bids()
                        ->where('bid', $highestBidValue)
                        ->orderBy('created_at')
                        ->first()
                        ->customer_id;

                    // Update lot
                    $lot->update([
                        'latest_bid_customer_id' => $higestBidderCustomerID,
                        'winning_bid_customer_id' => $higestBidderCustomerID,
                        'current_bid' => $currentBid,
                        'status' => 'ARCHIVED'
                    ]);
                }
            } catch (\Throwable $th) {
                print($th);
            }
        }

        $store->update(["remarks" => "SUCCESS"]);

        return response()->json([
            'message' => 'Archived Store Successfully'
        ], 200);
    }

    public function generateAuctionOrders(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->whereNotNull('winning_bid_customer_id')
            ->get();

        $winningCustomerIDs = $unpaidAuctionLots->pluck('winning_bid_customer_id')->values()->all();

        $generatedOrderCount = 0;
        foreach ($winningCustomerIDs as $customerID) {
            try {
                $winningLots = $unpaidAuctionLots->where('winning_bid_customer_id', $customerID)->all();
                $customer = Customer::find($customerID);

                foreach ($winningLots as $lot) {
                    $attributes = [
                        'store_id' => $storeID,
                        'product_id' => $lot->product_id,
                        'product_variant_id' => $lot->product_variant_id,
                        'qty' => 1,
                        'winning_bid' => $lot->current_bid,
                        'storage_fee' => $lot->current_bid * 0.03
                    ];
                    $customer->shoppingCartItems()->create($attributes);
                }

                // Get ShoppingCartItem(s)
                $cartItems = $customer->getAllCartItemsByStore($store);

                // getShoppingCartDetails calculations
                // get subtotal Price
                $subtotalPrice = 0;
                $storageFee = 0;

                $SERVICE_CHARGE_MULTIPLIER = 0.1;
                $totalServiceCharge = 0;

                foreach ($cartItems as $item) {
                    // Add keys
                    $item->is_checkout = true;
                    $item->is_refundable = false;
                    $item->global_discount = null;

                    // Calculations
                    $winningBid = $item->winning_bid ?? 0;
                    $subtotalPrice += $winningBid;

                    // Service Charge
                    $totalServiceCharge += $winningBid *
                        $SERVICE_CHARGE_MULTIPLIER;
                }
                $totalPrice = $subtotalPrice +
                    $storageFee + $totalServiceCharge;

                // get shippingFee
                $shippingFee = 0;
                $totalPrice += $shippingFee;

                // form calculation data object
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
                    'paid_order_id' => null,
                    'is_storage' => false
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
                $variants = ProductVariant::objectIDs($variantIDs)->get();
                $customer->clearCartByStore($store, $variants);

                $generatedOrderCount++;
            } catch (\Throwable $th) {
                print($th);
            }
        }

        return response()->json([
            'message' => "Generated All {$generatedOrderCount} Auction Store Orders Successfully"
        ], 200);
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

    // public function createAuctionStore(Request $request)
    // {
    //     // Extract attributes from $request
    //     $attributes = $request->all();

    //     // Create Store
    //     /** @var Store $store */
    //     $store = Store::createOfflineStore($attributes);

    //     // Create Warehouse
    //     $warehouseTitle = 'auction_warehouse_' . $store->_id;
    //     $warehouse = $store->warehouses()->create([
    //         'type' => 'AUCTION',
    //         'slug' => Str::slug($warehouseTitle),
    //         'title' => [
    //             'en' => $warehouseTitle,
    //             'zh' => $warehouseTitle,
    //             'cn' => $warehouseTitle
    //         ],
    //         'is_system' => true,
    //     ]);

    //     // Create one default Category
    //     $categoryTitle = 'all_products' . $store->_id;;
    //     $category = $store->productCategories()->create([
    //         'slug' => Str::slug($categoryTitle),
    //         'title' => [
    //             'en' => $categoryTitle,
    //             'zh' => $categoryTitle,
    //             'cn' => $categoryTitle
    //         ],
    //         'is_system' => true,
    //     ]);

    //     // Return success message
    //     return response()->json([
    //         'message' => 'Created new Auction successfully',
    //         '_id' => $store->_id,
    //         'warehouse_id' => $warehouse->_id,
    //         'category_id' => $category->_id,
    //     ], 200);
    // }

    public function getAllUnpaidAuctionLots(Request $request)
    {
        $storeID = $request->route('store_id');

        // Query
        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->whereNotNull('winning_bid_customer_id')
            ->where('is_paid', false)
            ->with([
                'product',
                'store',
                // 'winningBidCustomer',
                // 'winningBidCustomer.account'
            ])
            ->get();

        // Return success message
        return $unpaidAuctionLots;
    }

    public function returnAuctionLotToOriginalCustomer(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'ids' => [
                'required',
                'array'
            ],
            'ids.*' => [
                'exists:StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot,_id'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $auctionLotIDs = $request->input('ids', []);

        // Query
        $unpaidAuctionLots = AuctionLot::objectIDs($auctionLotIDs)
            ->with([
                'product',
                'store',
                'winningBidCustomer',
            ])
            ->get();

        // Validate AuctionLot(s)
        foreach ($unpaidAuctionLots as $key => $lot) {
            $lotID = $lot->_id;

            if (!is_null($lot->winning_bid_customer_id)) {
                return response()->json([
                    'message' => 'Auction lot ' . $lotID . ' does not have a winning customer.'
                ]);
            }

            if ($lot->is_paid === true) {
                return response()->json([
                    'message' => 'Auction lot ' . $lotID . ' has already been paid.'
                ]);
            }
        }

        // Update Product status, and reset AuctionLot WinningCustomer
        $productIDs = $unpaidAuctionLots->pluck('product_id');

        AuctionLot::objectIDs($auctionLotIDs)->update(["winning_bid_customer_id" => null]);
        Product::objectIDs($productIDs)->update(['listing_status' => 'AVAILABLE']);

        // Return success message
        return response()->json([
            'message' => 'Updated listing_status for ' . count($auctionLotIDs) . ' Product(s).'
        ], 200);
    }
}
