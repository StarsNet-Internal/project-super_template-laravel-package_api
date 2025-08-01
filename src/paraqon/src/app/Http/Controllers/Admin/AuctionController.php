<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

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
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Models\ProductCategory;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Str;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;

class AuctionController extends Controller
{
    use RoundingTrait;

    public function syncCategoriesToProduct(Request $request)
    {
        // Extract attributes from $request
        $productID = $request->route('product_id');
        $categoryIDs = $request->input('ids', []);

        // Get Product, then validate
        /** @var Product $product */
        $product = Product::find($productID);

        if (is_null($product)) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $existingAssignedCategoryIDs = $product->category_ids;

        // Find all Categories
        $existingAssignedCategories = ProductCategory::objectIDs($existingAssignedCategoryIDs)
            ->get();

        // Detach relationships
        foreach ($existingAssignedCategories as $category) {
            $category->products()->detach([$product->_id]);
        }

        $newCategories = ProductCategory::objectIDs($categoryIDs)
            ->get();

        // Attach relationships
        foreach ($newCategories as $category) {
            $category->products()->attach([$product->_id]);
        }

        // Return success message
        return response()->json([
            'message' => 'Sync'
        ], 200);
    }

    public function getAllAuctionRegistrationRequests(Request $request)
    {
        $storeID = $request->route('store_id');

        $store = Store::find($storeID);

        if (is_null($store)) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        $auctionRegistrationRequests = AuctionRegistrationRequest::where('store_id', $storeID)
            ->where('status', '!=', Status::DELETED)
            ->with([
                'requestedCustomer',
                'requestedCustomer.account',
                'deposits' => function ($q) {
                    $q->where('status', '!=', Status::DELETED);
                }
            ])
            ->latest()
            ->get();

        return $auctionRegistrationRequests;
    }

    public function getAllRegisteredUsers(Request $request)
    {
        $storeID = $request->route('store_id');
        $replyStatus = $request->input('reply_status', ReplyStatus::APPROVED);

        $registeredCustomers = AuctionRegistrationRequest::where('store_id', $storeID)
            // ->where('status', Status::ACTIVE)
            ->whereNotNull('paddle_id')
            ->where('reply_status', $replyStatus)
            ->latest()
            ->get();

        $registeredCustomerIDs = $registeredCustomers
            ->pluck('requested_by_customer_id')
            ->all();

        $customers = Customer::objectIDs($registeredCustomerIDs)
            ->with(['account', 'account.user'])
            ->get();

        foreach ($customers as $customer) {
            $customerID = $customer->_id;
            $paddleId = optional($registeredCustomers->first(function ($item) use ($customerID) {
                return $item->requested_by_customer_id == $customerID;
            }))->paddle_id;
            $customer->paddle_id = $paddleId ?? '';
        }

        return $customers;
    }

    public function removeRegisteredUser(Request $request)
    {
        $storeID = $request->route('store_id');
        $customerID = $request->route('customer_id');

        $registeredCustomerRequest = AuctionRegistrationRequest::where('store_id', $storeID)
            ->where('requested_by_customer_id', $customerID)
            // ->where('status', Status::ACTIVE)
            ->latest()
            ->first();

        if (is_null($registeredCustomerRequest)) {
            return response()->json([
                'message' => 'Customer is not successfully registered to this Auction'
            ], 404);
        }

        $updateAttributes = [
            'reply_status' => ReplyStatus::REJECTED
        ];
        $registeredCustomerRequest->update($updateAttributes);

        return response()->json([
            'message' => 'AuctionRegistrationRequest is now updated as REJECTED'
        ], 200);
    }

    public function addRegisteredUser(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->route('store_id');
        $customerID = $request->route('customer_id');

        // Check if there's existing AuctionRegistrationRequest
        $oldForm =
            AuctionRegistrationRequest::where('requested_by_customer_id', $customerID)
            ->where('store_id', $storeID)
            ->first();

        // Check if store exists
        $store = Store::find($storeID);
        if (is_null($store)) {
            return response()->json([
                'message' => 'Auction not found',
            ], 404);
        }

        // Auth
        $account = $this->account();
        if (!is_null($oldForm)) {
            $oldFormAttributes = [
                'approved_by_account_id' => $account->_id,
                'status' => Status::ACTIVE,
                'paddle_id' => $request->paddle_id ?? $oldForm->paddle_id,
                'reply_status' => ReplyStatus::APPROVED,
            ];
            $oldForm->update($oldFormAttributes);

            return response()->json([
                'message' => 'Re-activated previously created AuctionRegistrationRequest successfully',
                'id' => $oldForm->_id,
            ], 200);
        }

        // Auto-assign paddle_id
        $auctionType = $store->auction_type;

        if ($auctionType == 'ONLINE') {
            $paddleId = null;

            $allPaddles = AuctionRegistrationRequest::where('store_id', $storeID)
                ->pluck('paddle_id')
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->sort()
                ->values();
            $latestPaddleId = $allPaddles->last();

            if (is_null($latestPaddleId)) {
                $paddleId = $store->paddle_number_start_from ?? 1;
            } else {
                $paddleId = $latestPaddleId + 1;
            }

            $createAttributes = [
                'requested_by_customer_id' => $customerID,
                'store_id' => $storeID,
                'paddle_id' => $paddleId,
                'status' => Status::ACTIVE,
                'reply_status' => ReplyStatus::APPROVED
            ];
            $newForm = AuctionRegistrationRequest::create($createAttributes);

            // Return Auction Store
            return response()->json([
                'message' => 'Created New AuctionRegistrationRequest successfully',
                'id' => $newForm->_id,
            ], 200);
        }

        if ($auctionType == 'LIVE') {
            $createAttributes = [
                'requested_by_customer_id' => $customerID,
                'store_id' => $storeID,
                'paddle_id' => $request->paddle_id,
                'status' => Status::ACTIVE,
                'reply_status' => ReplyStatus::APPROVED
            ];
            $newForm = AuctionRegistrationRequest::create($createAttributes);

            // Return Auction Store
            return response()->json([
                'message' => 'Created New AuctionRegistrationRequest successfully',
                'id' => $newForm->_id,
            ], 200);
        }
    }

    public function getAllAuctionRegistrationRecords(Request $request)
    {
        $storeID = $request->route('store_id');

        $forms = AuctionRegistrationRequest::where('store_id', $storeID)
            ->with(['deposits'])
            ->get();

        return $forms;
    }

    public function getAllCategories(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->route('store_id');
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Store
        $store = Store::find($storeID);

        // Get all active ProductCategory(s)
        $categories = $store
            ->productCategories()
            ->statusesAllowed(
                Status::$typesForAdmin,
                $statuses
            )
            ->get();

        // Get all product ids
        $productIDs = AuctionLot::where('store_id', $storeID)
            ->get()
            ->pluck('product_id')
            ->all();

        foreach ($categories as $category) {
            $category->lot_count = count(array_intersect(
                $category->item_ids,
                $productIDs
            ));
            // $category->product_count = count($productIDs);
            // $category->item_count = count($category->item_ids);
        }

        return $categories;
    }

    public function getAllAuctions(Request $request)
    {
        $auctionType = $request->input('auction_type');
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        $stores = Store::where('auction_type', $auctionType)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->latest()
            ->get();

        foreach ($stores as $store) {
            $store->auction_lot_count = AuctionLot::where('store_id', $store->_id)
                ->statusesAllowed([Status::ACTIVE, Status::ARCHIVED])
                ->count();

            $store->registered_user_count = AuctionRegistrationRequest::where('store_id', $store->_id)
                ->where('reply_status', ReplyStatus::APPROVED)
                ->count();
        }

        return $stores;
    }

    public function updateAuctionStatuses(Request $request)
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

                // Update all AuctionLots as ACTIVE status
                $storeID = $store->_id;
                AuctionLot::where('store_id', $storeID)
                    ->where('status', Status::ARCHIVED)
                    ->update(['status' => Status::ACTIVE]);

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

                // Update all AuctionLots as ARCHIVED status
                // $storeID = $store->_id;
                // AuctionLot::where('store_id', $storeID)
                //     ->update(['status' => Status::ARCHIVED]);

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

        $allAuctionLots = AuctionLot::where('store_id', $storeID)
            ->where('status', Status::ACTIVE)
            ->get();

        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($allAuctionLots as $lot) {
            try {
                $isBidPlaced = $lot->is_bid_placed;
                $currentBid = $lot->getCurrentBidPrice();

                $product = Product::find($lot->product_id);
                // Reject auction lot
                if ($isBidPlaced == false || $lot->reserve_price > $currentBid) {
                    // Update Product
                    $product->update(['listing_status' => 'AVAILABLE']);

                    // Update Auction Lot
                    $lot->update([
                        'winning_bid_customer_id' => null,
                        'current_bid' => $currentBid,
                        'status' => Status::ARCHIVED,
                        'is_paid' => false
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
                        'winning_bid_customer_id' => $higestBidderCustomerID,
                        'current_bid' => $currentBid,
                        'status' => Status::ARCHIVED,
                    ]);
                }
            } catch (\Throwable $th) {
                print($th);
            }
        }

        $store->update(["remarks" => "SUCCESS"]);

        return response()->json([
            'message' => 'Archived Store Successfully, updated ' . $allAuctionLots->count() . ' Auction Lots.'
        ], 200);
    }

    public function generateAuctionOrders(Request $request)
    {
        // Get Store
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        // Get Auction Lots
        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->where('status', Status::ARCHIVED)
            ->whereNotNull('winning_bid_customer_id')
            ->get();

        // Get unique winning_bid_customer_id
        $winningCustomerIDs = $unpaidAuctionLots->pluck('winning_bid_customer_id')
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
                        'storage_fee' => $lot->current_bid * 0.03
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
            'id' => [
                'required',
                'exists:App\Models\Order,_id'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $orderID = $request->input('id');

        // Get Order
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order ID ' . $orderID . ' not found.'
            ], 404);
        }

        if ($order->payment_method != OrderPaymentMethod::OFFLINE) {
            return response()->json([
                'message' => 'This order ID ' . $orderID . ' is an Online Payment Order, items cannot be returned.'
            ], 404);
        }

        // Get variant IDs
        $storeID = $order->store_id;
        $variantIDs = collect($order->cart_items)->pluck('product_variant_id')->all();

        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->whereIn('product_variant_id', $variantIDs)
            ->where('is_paid', false)
            ->get();

        foreach ($unpaidAuctionLots as $lot) {
            $lot->update(["winning_bid_customer_id" => null]);
        }


        // Validate AuctionLot(s)
        // foreach ($unpaidAuctionLots as $key => $lot) {
        //     $lotID = $lot->_id;

        //     if (!is_null($lot->winning_bid_customer_id)) {
        //         return response()->json([
        //             'message' => 'Auction lot ' . $lotID . ' does not have a winning customer.'
        //         ]);
        //     }

        //     if ($lot->is_paid === true) {
        //         return response()->json([
        //             'message' => 'Auction lot ' . $lotID . ' has already been paid.'
        //         ]);
        //     }
        // }

        // Update Product status, and reset AuctionLot WinningCustomer
        $productIDs = $unpaidAuctionLots->pluck('product_id');
        Product::objectIDs($productIDs)->update(['listing_status' => 'AVAILABLE']);

        // Return success message
        return response()->json([
            'message' => 'Updated listing_status for ' . count($productIDs) . ' Product(s).'
        ], 200);
    }

    public function closeAllNonDisabledLots(Request $request)
    {
        $storeId = $request->route('store_id');

        AuctionLot::where('store_id', $storeId)
            ->where('status', '!=', Status::DELETED)
            ->whereNotNull('lot_number')
            ->where('is_disabled', false)
            ->update([
                'status' => 'ACTIVE',
                'is_disabled' => true,
                'is_closed' => true
            ]);

        return response()->json([
            'message' => 'Close all Lots successfully'
        ], 200);
    }
}
