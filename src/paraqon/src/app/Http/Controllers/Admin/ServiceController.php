<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// MongoDB
use MongoDB\BSON\UTCDateTime;

// Models
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;
use StarsNet\Project\Paraqon\App\Models\LiveBiddingEvent;

// Controllers
use App\Http\Controllers\Customer\ProductManagementController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionLotController as AdminAuctionLotController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuctionLotController as CustomerAuctionLotController;

// Constants
use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;

// Traits
use App\Traits\Utils\RoundingTrait;

class ServiceController extends Controller
{
    use RoundingTrait;

    public function paymentCallback(Request $request)
    {
        // Extract attributes from $request
        $eventType = $request->type;

        // Validation
        $acceptableEventTypes = [
            'charge.succeeded',
            'charge.refunded',
            'charge.captured',
            'charge.expired',
            'charge.failed'
        ];

        if (!in_array($eventType, $acceptableEventTypes)) {
            return response()->json([
                'message' => 'Callback success, but event type does not belong to any of the acceptable values',
                'acceptable_values' => $acceptableEventTypes
            ],  200);
        }

        // Extract attributes from $request
        $model = $request->data['object']['metadata']['model_type'] ?? null;
        $modelID = $request->data['object']['metadata']['model_id'] ?? null;

        if (is_null($model) || is_null($modelID)) {
            return response()->json(
                ['message' => 'Callback success, but metadata contains null value for either model_type or model_id.'],
                400
            );
        }

        // Find Model and Update
        switch ($model) {
            case 'deposit': {
                    /** @var ?Deposit $deposit */
                    $deposit = Deposit::find($modelID);

                    if (is_null($deposit)) {
                        return response()->json(['message' => 'Deposit not found'], 404);
                    }

                    // Get AuctionRegistrationRequest
                    /** @var ?AuctionRegistrationRequest $auctionRegistrationRequest */
                    $auctionRegistrationRequest = $deposit->auctionRegistrationRequest;

                    // Update Deposit
                    if ($eventType == 'charge.succeeded') {
                        $deposit->updateStatus('on-hold');
                        $deposit->update(['reply_status' => ReplyStatus::APPROVED]);
                        $deposit->updateOnlineResponse($request->all());

                        // Automatically assign paddle_id if ONLINE auction
                        if (in_array($auctionRegistrationRequest->reply_status, [
                            ReplyStatus::PENDING,
                            ReplyStatus::REJECTED
                        ])) {
                            // get Paddle ID
                            $assignedPaddleId = $auctionRegistrationRequest->paddle_id;
                            $storeID = $auctionRegistrationRequest->store_id;

                            $newPaddleID = $assignedPaddleId;

                            if (is_null($assignedPaddleId)) {
                                $allPaddles = AuctionRegistrationRequest::where('store_id', $storeID)
                                    ->pluck('paddle_id')
                                    ->filter(fn($id) => is_numeric($id))
                                    ->map(fn($id) => (int) $id)
                                    ->sort()
                                    ->values();
                                $latestPaddleId = $allPaddles->last();

                                if (is_null($latestPaddleId)) {
                                    $store = Store::find($storeID);
                                    $newPaddleID = $store->paddle_number_start_from ?? 1;
                                } else {
                                    $newPaddleID = $latestPaddleId + 1;
                                }
                            }

                            $auctionRegistrationRequest->update([
                                'paddle_id' => $newPaddleID,
                                'status' => Status::ACTIVE,
                                'reply_status' => ReplyStatus::APPROVED
                            ]);
                        }

                        return response()->json([
                            'message' => 'Deposit status updated as on-hold',
                            'deposit_id' => $deposit->_id
                        ], 200);
                    } else if ($eventType == 'charge.refunded') {
                        $deposit->updateStatus('returned');

                        $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                        $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;

                        $deposit->update([
                            'amount_captured' => $amountCaptured / 100,
                            'amount_refunded' => $amountRefunded / 100,
                        ]);

                        return response()->json([
                            'message' => 'Deposit status updated as returned',
                            'deposit_id' => $deposit->_id
                        ], 200);
                    } else if ($eventType == 'charge.captured') {
                        $deposit->updateStatus('returned');

                        $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                        $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;

                        $deposit->update([
                            'amount_captured' => $amountCaptured / 100,
                            'amount_refunded' => $amountRefunded / 100,
                        ]);

                        return response()->json([
                            'message' => 'Deposit status updated as captured',
                            'deposit_id' => $deposit->_id
                        ], 200);
                    } else if ($eventType == 'charge.expired') {
                        $deposit->updateStatus('returned');

                        $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                        $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;

                        $deposit->update([
                            'amount_captured' => $amountCaptured / 100,
                            'amount_refunded' => $amountRefunded / 100,
                        ]);

                        return response()->json([
                            'message' => 'Deposit status updated as expired',
                            'deposit_id' => $deposit->_id
                        ], 200);
                    } else if ($eventType == 'charge.failed') {
                        $deposit->updateStatus('cancelled');

                        $deposit->update([
                            'reply_status' => ReplyStatus::REJECTED,
                            'stripe_api_reponse' => $request->data['object']
                        ]);

                        return response()->json([
                            'message' => 'Deposit status updated as cancelled',
                            'deposit_id' => $deposit->_id
                        ], 200);
                    }

                    return response()->json([
                        'message' => 'Invalid Stripe event type',
                        'deposit_id' => null
                    ], 400);
                }
            case 'checkout': {
                    // Get Checkout
                    /** Checkout $checkout */
                    $checkout = Checkout::find($modelID);

                    if (is_null($checkout)) {
                        return response()->json(
                            ['message' => 'Checkout not found'],
                            200
                        );
                    }

                    // Get Order
                    $order = $checkout->order;

                    if (is_null($order)) {
                        return response()->json(
                            ['message' => 'Order not found'],
                            200
                        );
                    }

                    $customEventType = $request->data['object']['metadata']['custom_event_type'] ?? null;

                    if ($customEventType === 'one_day_delay') {
                        $order->update([
                            'scheduled_payment_at' => new UTCDateTime(now()->addDay(1))
                        ]);

                        // Clear ShoppingCartItem
                        $productVariantIDs = collect($order->cart_items)
                            ->pluck('product_variant_id')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray();
                        ShoppingCartItem::where('customer_id', $order->customer_id)
                            ->where('store_id', $order->store_id)
                            ->whereIn('product_variant_id', $productVariantIDs)
                            ->delete();

                        return response()->json([
                            'message' => 'custom_event_type is one_day_delay, capture skipped',
                            'order_id' => null
                        ], 200);
                    }

                    // Update Checkout and Order
                    if ($eventType == 'charge.succeeded' && is_null($customEventType)) {
                        // Update Checkout
                        $checkout->updateOnlineResponse((object) $request->all());
                        $checkout->createApproval(
                            CheckoutApprovalStatus::APPROVED,
                            'Payment verified by Stripe'
                        );

                        // Update Order
                        if ($order->current_status !== ShipmentDeliveryStatus::PROCESSING) {
                            $order->updateStatus(ShipmentDeliveryStatus::PROCESSING);
                        }

                        // Update Product and AuctionLot
                        $storeID = $order->store_id;
                        $store = Store::find($storeID);

                        if (
                            !is_null($store) &&
                            in_array($store->auction_type, ['LIVE', 'ONLINE'])
                        ) {
                            $productIDs = collect($order->cart_items)->pluck('product_id')->all();

                            AuctionLot::where('store_id', $storeID)
                                ->whereIn('product_id', $productIDs)
                                ->update(['is_paid' => true]);

                            Product::objectIDs($productIDs)->update([
                                'owned_by_customer_id' => $order->customer_id,
                                'status' => 'ACTIVE',
                                'listing_status' => 'ALREADY_CHECKOUT'
                            ]);
                        }

                        return response()->json(
                            [
                                'message' => 'Checkout approved, and Order status updated',
                                'order_id' => $order->_id
                            ],
                            200
                        );
                    }

                    return response()->json(
                        [
                            'message' => 'Invalid Stripe event type',
                            'order_id' => null
                        ],
                        400
                    );
                }
            default: {
                    return response()->json(
                        [
                            'message' => 'Invalid model_type for metadata',
                        ],
                        400
                    );
                }
        }
    }

    public function updateAuctionStatuses(Request $requests)
    {
        $now = now();

        // ----------------------
        // Auction (Store) Starts
        // ----------------------

        // Make stores ACTIVE
        $archivedStores = Store::where('auction_type', 'ONLINE')
            ->where('status', Status::ARCHIVED)
            ->get();

        $archivedStoresUpdateCount = 0;
        foreach ($archivedStores as $store) {
            $startTime = Carbon::parse($store->start_datetime)->startOfMinute();
            $endTime = Carbon::parse($store->end_datetime)->startOfMinute();

            if ($now >= $startTime && $now <= $endTime) {
                $store->update(['status' => Status::ACTIVE]);
                $archivedStoresUpdateCount++;
            }
        }

        // Make stores ARCHIVED
        $activeStores = Store::where('auction_type', 'ONLINE')
            ->where('status', Status::ACTIVE)
            ->get();

        $activeStoresUpdateCount = 0;
        foreach ($activeStores as $store) {
            $endTime = Carbon::parse($store->end_datetime)->startOfMinute();

            if ($now >= $endTime) {
                $store->update(['status' => Status::ARCHIVED]);
                $activeStoresUpdateCount++;
            }
        }

        // ----------------------
        // Auction (Store) Ends
        // ----------------------

        // ----------------------
        // Auction Lot Starts
        // ----------------------

        // Make lots ACTIVE
        $archivedLots = AuctionLot::where('status', Status::ARCHIVED)
            ->whereHas('store', function ($query) {
                return $query->where('status', Status::ACTIVE)
                    ->where('auction_type', 'ONLINE');
            })
            ->get();

        $archivedLotsUpdateCount = 0;
        foreach ($archivedLots as $lot) {
            $startTime = Carbon::parse($lot->start_datetime)->startOfMinute();
            $endTime = Carbon::parse($lot->end_datetime)->startOfMinute();

            if ($now >= $startTime && $now < $endTime) {
                $lot->update(['status' => Status::ACTIVE]);
                $archivedLotsUpdateCount++;
            }
        }

        // Make lots ARCHIVED
        $activeLots = AuctionLot::where('status', Status::ACTIVE)
            ->whereHas('store', function ($query) {
                return $query->where('auction_type', 'ONLINE');
            })->get();

        $activeLotsUpdateCount = 0;
        foreach ($activeLots as $lot) {
            $endTime = Carbon::parse($lot->end_datetime)->startOfMinute();

            if ($now >= $endTime) {
                $lot->update(['status' => Status::ARCHIVED]);
                $activeLotsUpdateCount++;
            }
        }

        // ----------------------
        // Auction Lot Ends
        // ----------------------

        return response()->json([
            'now_time' => $now,
            'activated_store_count' => $archivedStoresUpdateCount,
            'archived_store_count' => $activeStoresUpdateCount,
            'activated_lot_count' => $archivedLotsUpdateCount,
            'archived_lot_count' => $activeLotsUpdateCount,
            'message' => "Updated Successfully"
        ], 200);
    }

    public function updateAuctionLotStatuses(Request $requests)
    {
        $now = now();

        // Make lots ACTIVE
        $archivedLots = AuctionLot::where('status', Status::ARCHIVED)
            ->whereHas('store', function ($query) {
                return $query->where('status', Status::ACTIVE);
            })
            ->get();

        $archivedLotsUpdateCount = 0;
        foreach ($archivedLots as $lot) {
            $startTime = Carbon::parse($lot->start_datetime);
            $endTime = Carbon::parse($lot->end_datetime);

            if ($now >= $startTime && $now < $endTime) {
                $lot->update(['status' => Status::ACTIVE]);
                $archivedLotsUpdateCount++;
            }
        }

        // Make lots ARCHIVED
        $activeLots = AuctionLot::where('status', Status::ACTIVE)->get();

        $activeLotsUpdateCount = 0;
        foreach ($activeLots as $lot) {
            $endTime = Carbon::parse($lot->end_datetime);

            if ($now >= $endTime) {
                $lot->update(['status' => Status::ARCHIVED]);
                $activeLotsUpdateCount++;
            }
        }

        return response()->json([
            'now_time' => $now,
            'message' => "Updated {$archivedLotsUpdateCount} AuctionLot(s) as ACTIVE, and {$activeLotsUpdateCount} AuctionLot(s) as ARCHIVED"
        ], 200);
    }

    private function cancelDeposit(Deposit $deposit)
    {
        switch ($deposit->payment_method) {
            case 'ONLINE':
                $paymentIntentID = $deposit->online['payment_intent_id'];
                $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/cancel';

                try {
                    $response = Http::post(
                        $url
                    );

                    if ($response->status() === 200) {
                        return true;
                    } else {
                        return false;
                    }
                } catch (\Throwable $th) {
                    Log::error('Failed to cancel deposit, deposit_id: ' . $deposit->_id);
                    $deposit->updateStatus('return-failed');
                    return false;
                }
            case 'OFFLINE':
                $deposit->update([
                    'amount_captured' => 0,
                    'amount_refunded' => $deposit->amount,
                ]);
                return true;
            default:
                return false;
        }
    }

    private function captureDeposit(Deposit $deposit, $captureAmount)
    {
        switch ($deposit->payment_method) {
            case 'ONLINE':
                $paymentIntentID = $deposit->online['payment_intent_id'];
                $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/capture';

                try {
                    $data = [
                        'amount' => $captureAmount * 100
                    ];

                    $response = Http::post(
                        $url,
                        $data
                    );

                    if ($response->status() === 200) {
                        return true;
                    } else {
                        return false;
                    }
                } catch (\Throwable $th) {
                    Log::error('Failed to capture deposit, deposit_id: ' . $deposit->_id);
                    $deposit->updateStatus('return-failed');
                    return false;
                }
            case 'OFFLINE':
                $deposit->update([
                    'amount_captured' => $captureAmount,
                    'amount_refunded' => $deposit->amount - $captureAmount,
                ]);
                return true;
            default:
                return false;
        }
    }

    private function createAuctionOrder(
        Store $store,
        Customer $customer,
        Collection $winningLots,
        Collection $deposits
    ) {
        // Create ShoppingCartItem(s) from each AuctionLot
        foreach ($winningLots as $lot) {
            $attributes = [
                'store_id' => $store->_id,
                'product_id' => $lot->product_id,
                'product_variant_id' => $lot->product_variant_id,
                'qty' => 1,
                'lot_number' => $lot->lot_number,
                'winning_bid' => $lot->current_bid,
                'sold_price' => $lot->sold_price ?? $lot->current_bid,
                'commission' => $lot->commission ?? 0
            ];
            $customer->shoppingCartItems()->create($attributes);
        }

        // Initialize calculation variables
        $itemTotalPrice = 0;
        $shippingFee = 0;

        // Get ShoppingCartItem(s), then do calculations before creating Order
        $cartItems = $customer->getAllCartItemsByStore($store);

        foreach ($cartItems as $item) {
            // Add default keys for Order's cart_item attributes integrity
            $item->is_checkout = true;
            $item->is_refundable = false;
            $item->global_discount = null;

            // Get sold_price, update subtotal_price
            $itemTotalPrice += $item->sold_price;
        }

        // Get total price
        $orderTotalPrice = $itemTotalPrice + $shippingFee;

        // Calculate totalCapturedDeposit
        $totalCustomerOnHoldDepositAmount = $deposits->sum('amount');
        $totalCapturableDeposit = min($totalCustomerOnHoldDepositAmount, $orderTotalPrice);

        // Start capturing deposits, and refund excess
        $depositToBeDeducted = $totalCapturableDeposit;
        foreach ($deposits as $deposit) {
            if ($depositToBeDeducted <= 0) {
                $this->cancelDeposit($deposit);
            } else {
                $currentDepositAmount = $deposit->amount;
                $captureDeposit = min($currentDepositAmount, $depositToBeDeducted);
                $isCapturedSuccessfully = $this->captureDeposit($deposit, $captureDeposit);

                if ($isCapturedSuccessfully == true) $depositToBeDeducted -= $captureDeposit;
            }
        }

        // Update total price
        $orderTotalPrice -= $totalCapturableDeposit;

        // Form calculation data object
        $rawCalculation = [
            'currency' => 'HKD',
            'price' => [
                'subtotal' => $itemTotalPrice,
                'total' => $orderTotalPrice, // Deduct price_discount.local and .global
            ],
            'price_discount' => [
                'local' => 0,
                'global' => 0,
            ],
            'point' => [
                'subtotal' => 0,
                'total' => 0,
            ],
            'service_charge' => 0,
            'deposit' => $totalCapturableDeposit,
            'storage_fee' => 0,
            'shipping_fee' => $shippingFee
        ];
        $roundedCalculation = $this->roundingNestedArray($rawCalculation); // Round off values

        // Round up calculations.price.total only
        $roundedCalculation['price']['total'] = ceil($roundedCalculation['price']['total']) . '.00';

        // Create Order
        $orderAttributes = [
            'is_paid' => false,
            'payment_method' => CheckoutType::OFFLINE,
            'discounts' => [],
            'calculations' => $roundedCalculation,
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
            'is_voucher_applied' => false,
            'is_system' => true,
            'payment_information' => [
                'currency' => 'HKD',
                'conversion_rate' => 1.00
            ]
        ];
        $order = $customer->createOrder($orderAttributes, $store);

        // Create OrderCartItem(s)
        $variantIDs = [];
        foreach ($cartItems as $item) {
            $attributes = $item->toArray();
            unset($attributes['_id'], $attributes['is_checkout']);

            /** @var ProductVariant $variant */
            $order->createCartItem($attributes);
        }

        // Update Order
        $status = Str::slug(ShipmentDeliveryStatus::SUBMITTED);
        $order->updateStatus($status);

        // Create Checkout
        $attributes = [
            'payment_method' => CheckoutType::OFFLINE
        ];
        $order->checkout()->create($attributes);

        // Delete ShoppingCartItem(s)
        $variantIDs = $cartItems->map(function ($item) {
            return $item['product_variant_id'];
        })->toArray();
        $variants = ProductVariant::objectIDs($variantIDs)->get();
        $customer->clearCartByStore($store, $variants);

        return $order;
    }

    private function createPaidAuctionOrder(Order $originalOrder)
    {
        // Replicate new Order
        $newOrder = $originalOrder->replicate();

        // Get Customer's deliveryDetails
        $customerID = $originalOrder->customer_id;
        $customer = Customer::find($customerID);
        $account = optional($customer)->account;

        $deliveryDetails = [
            'recipient_name' => [
                'first_name' => null,
                'last_name' => null,
            ],
            'email' => optional($account)->email,
            'area_code' => optional($account)->area_code,
            'phone' => optional($account)->phone,
            'address' => [
                'country' => null,
                'city' => null,
                'address_line_1' => null,
                'address_line_2' => null,
                'building' => null,
                'state' => null,
                'postal_code' => null,
                'company_name' => null,
            ],
            'remarks' => null,
        ];

        // Update new Order
        $newOrder->fill([
            'is_system' => false,
            'is_paid' => true,
            'payment_information' => [
                'currency' => 'HKD',
                'conversion_rate' => '1.00000'
            ],
            'delivery_details' => $deliveryDetails
        ]);
        $newOrder->save();

        // Update original Order is_paid
        $originalOrder->update(['is_paid' => true]);

        // Update new Order's status
        $newOrder->updateStatus('processing');

        return $newOrder;
    }

    public function generateAuctionOrdersAndRefundDeposits(Request $request)
    {
        // Extract attributes from request
        $storeID = $request->route('store_id');

        // Check Store status, for validation before mass-generating Order(s)
        $store = Store::find($storeID);

        if ($store->status === Status::ACTIVE) {
            return response()->json([
                'message' => "Store is still ACTIVE. Skipping generating auction order sequences."
            ], 200);
        }

        if ($store->status === Status::DELETED) {
            return response()->json([
                'message' => "Store is already DELETED. Skipping generating auction order sequences."
            ], 200);
        }

        // Get AuctionLot(s) from Store
        // $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
        //     ->where('status', Status::ARCHIVED)
        //     ->whereNotNull('winning_bid_customer_id')
        //     ->get()
        //     ->filter(function ($item) {
        //         return $item->current_bid >= $item->reserve_price;
        //     });

        // // Get unique winning_bid_customer_id from all AuctionLot(s)
        // $winningCustomerIDs = $unpaidAuctionLots
        //     ->pluck('winning_bid_customer_id')
        //     ->unique()
        //     ->values()
        //     ->all();

        // Get all winning customer ids
        $winningCustomerIDs = array_map(function ($result) {
            return $result['customer_id'];
        }, $request->results);


        // Get all Deposit(s), with on-hold current_deposit_status, from non-winning Customer(s)
        $allFullRefundDeposits = Deposit::whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
            $query->whereHas('store', function ($query2) use ($storeID) {
                $query2->where('_id', $storeID);
            });
        })
            ->whereNotIn('requested_by_customer_id', $winningCustomerIDs)
            ->where('current_deposit_status', 'on-hold')
            ->get();

        // Full-refund all Deposit(s) from all non-winning Customer(s)
        foreach ($allFullRefundDeposits as $deposit) {
            $this->cancelDeposit($deposit);
        }

        // Generate OFFLINE order by system
        $generatedOrderCount = 0;

        // Update auction lots with inputted price
        foreach ($request->results as $result) {
            foreach ($result['lots'] as $lot) {
                AuctionLot::where('_id', $lot['lot_id'])
                    ->update([
                        'winning_bid_customer_id' => $result['customer_id'],
                        'current_bid' => $lot['price'],
                        'sold_price' => $lot['sold_price'],
                        'commission' => $lot['commission'],
                    ]);
            }
        }

        foreach ($request->results as $result) {
            try {
                // Extract attributes from $result
                $customerID = $result['customer_id'];
                $confirmedLots = collect($result['lots']);

                // Get Customer
                $customer = Customer::find($customerID);

                // Find all winning Auction Lots
                $winningLotIds = $confirmedLots->map(function ($lot) {
                    return $lot['lot_id'];
                })->all();
                $winningLots = AuctionLot::find($winningLotIds);

                // Get all Deposit(s), with on-hold current_deposit_status, from this Customer
                $customerOnHoldDeposits = Deposit::whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
                    $query->whereHas('store', function ($query2) use ($storeID) {
                        $query2->where('_id', $storeID);
                    });
                })
                    ->where('requested_by_customer_id', $customer->_id)
                    ->where('current_deposit_status', 'on-hold')
                    ->get();

                // Create Order, and capture/refund deposits
                $order = $this->createAuctionOrder(
                    $store,
                    $customer,
                    $winningLots,
                    $customerOnHoldDeposits
                );

                // Create non-system Order is total price is 0
                // if ($order->calculations['price']['total'] == 0) {
                //     $this->createPaidAuctionOrder($order);
                // }

                $generatedOrderCount++;
            } catch (\Throwable $th) {
                print($th);
            }
        }

        return response()->json([
            'message' => "Generated {$generatedOrderCount} Auction Store Orders Successfully"
        ], 200);
    }

    public function generateLiveAuctionOrdersAndRefundDeposits(Request $request)
    {
        // Extract attributes from request
        $storeID = $request->route('store_id');

        // Check Store status, for validation before mass-generating Order(s)
        $store = Store::find($storeID);

        if ($store->status === Status::ACTIVE) {
            return response()->json([
                'message' => "Store is still ACTIVE. Skipping generating auction order sequences."
            ], 200);
        }

        if ($store->status === Status::DELETED) {
            return response()->json([
                'message' => "Store is already DELETED. Skipping generating auction order sequences."
            ], 200);
        }

        // // Get AuctionLot(s) from Store
        // $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
        //     ->where('status', Status::ARCHIVED)
        //     ->whereNotNull('winning_bid_customer_id')
        //     ->get()
        //     ->filter(function ($item) {
        //         return $item->current_bid >= $item->reserve_price;
        //     });

        // // Get unique winning_bid_customer_id from all AuctionLot(s)
        // $winningCustomerIDs = $unpaidAuctionLots
        //     ->pluck('winning_bid_customer_id')
        //     ->unique()
        //     ->values()
        //     ->all();
        $winningCustomerIDs = array_map(function ($result) {
            return $result['customer_id'];
        }, $request->results);

        // Get all Deposit(s), with on-hold current_deposit_status, from non-winning Customer(s)
        $allFullRefundDeposits = Deposit::whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
            $query->whereHas('store', function ($query2) use ($storeID) {
                $query2->where('_id', $storeID);
            });
        })
            ->whereNotIn('requested_by_customer_id', $winningCustomerIDs)
            ->where('current_deposit_status', 'on-hold')
            ->get();

        // Full-refund all Deposit(s) from all non-winning Customer(s)
        foreach ($allFullRefundDeposits as $deposit) {
            $this->cancelDeposit($deposit);
        }

        // Generate OFFLINE order by system
        $generatedOrderCount = 0;

        // Update auction lots with inputted price
        foreach ($request->results as $result) {
            foreach ($result['lots'] as $lot) {
                AuctionLot::where('_id', $lot['lot_id'])
                    ->update([
                        'winning_bid_customer_id' => $result['customer_id'],
                        'current_bid' => $lot['price']
                    ]);
            }
        }

        // foreach ($winningCustomerIDs as $customerID) {
        foreach ($request->results as $result) {
            try {
                // $customer = Customer::find($customerID);
                $customer = Customer::find($result['customer_id']);
                $confirmedLots = collect($result['lots']);

                // Find all winning Auction Lots
                // $winningLots = $unpaidAuctionLots->where('winning_bid_customer_id', $customerID);
                $winningLotIds = $confirmedLots->map(function ($lot) {
                    return $lot['lot_id'];
                })->all();
                $winningLots = AuctionLot::find($winningLotIds);
                // $winningLots = $winningLots->map(function ($winningLot) use ($confirmedLots) {
                //     $confirmedLot = $confirmedLots->first(function ($lot) use ($winningLot) {
                //         return $lot['lot_id'] === $winningLot->_id;
                //     });
                //     $winningLot->current_bid = $confirmedLot['price'];
                //     return $winningLot;
                // });

                // Get all Deposit(s), with on-hold current_deposit_status, from this Customer
                $customerOnHoldDeposits = Deposit::whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
                    $query->whereHas('store', function ($query2) use ($storeID) {
                        $query2->where('_id', $storeID);
                    });
                })
                    ->where('requested_by_customer_id', $customer->_id)
                    ->where('current_deposit_status', 'on-hold')
                    ->get();

                // Create Order, and capture/refund deposits
                $this->createAuctionOrder(
                    $store,
                    $customer,
                    $winningLots,
                    $customerOnHoldDeposits
                );

                $generatedOrderCount++;
            } catch (\Throwable $th) {
                print($th);
            }
        }

        return response()->json([
            'message' => "Generated {$generatedOrderCount} Auction Store Orders Successfully"
        ], 200);
    }

    public function returnDeposit(Request $request)
    {
        $paymentIntentID = $request->data['object']['id'];

        $deposit = Deposit::where(
            'online.payment_intent_id',
            $paymentIntentID
        )
            ->first();

        $deposit->updateStatus('returned');

        return response()->json([
            'message' => 'Updated Deposit as returned Successfully',
            'deposit_id' => $deposit->id
        ]);
    }

    public function confirmOrderPaid(Request $request)
    {
        $paymentIntentID = $request->data['object']['id'];
        $isPaid = true;

        // Find Checkout
        $checkout = Checkout::where(
            'online.payment_intent_id',
            $paymentIntentID
        )
            ->first();

        $checkout->updateOnlineResponse(
            (object) $request->all()
        );

        // Update Checkout and Order
        $status = $isPaid ?
            CheckoutApprovalStatus::APPROVED :
            CheckoutApprovalStatus::REJECTED;
        $reason = $isPaid ?
            'Payment verified by System' :
            'Payment failed';

        $checkout->createApproval($status, $reason);

        // Get Order and Customer
        /** @var Order $order */
        $order = $checkout->order;

        // Update Order status
        if ($isPaid && $order->current_status !== ShipmentDeliveryStatus::PROCESSING) {
            // $order->setTransactionMethod($paymentMethod);
            $order->updateStatus(ShipmentDeliveryStatus::PROCESSING);
        }

        if (!$isPaid && $order->current_status !== ShipmentDeliveryStatus::CANCELLED) {
            $order->updateStatus(ShipmentDeliveryStatus::CANCELLED);
            return;
        }

        return response()->json([
            'message' => 'Updated Order as paid Successfully',
            'order_id' => $order->id
        ]);
    }

    public function getAuctionCurrentState(Request $request)
    {
        $storeId = $request->route('store_id');
        $request->merge(['store_id' => $storeId]);

        $adminAuctionLotController = new AdminAuctionLotController();
        $lots = $adminAuctionLotController->getAllAuctionLots($request);

        $currentLot = $this->getCurrentLot($lots);
        $currentLotId = $currentLot->_id;

        $highestAdvancedBid = $currentLot->bids()
            ->where('is_hidden',  false)
            ->where('type', 'ADVANCED')
            ->orderBy('bid', 'desc')
            ->first();

        $request->route()->setParameter('auction_lot_id', $currentLotId);
        $customerAuctionLotController = new CustomerAuctionLotController();
        $histories = $customerAuctionLotController->getBiddingHistory($request);

        $events = LiveBiddingEvent::where('store_id', $storeId)
            ->where('value_1', $currentLotId)
            ->get();

        $data = [
            'lots' => $lots,
            'current_lot_id' => $currentLotId,
            'highest_advanced_bid' => $highestAdvancedBid,
            'histories' => $histories,
            'events' => $events,
            'time' => now(),
        ];

        try {
            $url = env('PARAQON_SOCKET_BASE_URL', 'https://socket.paraqon.starsnet.hk') . '/api/publish';
            $response = Http::post(
                $url,
                [
                    'site' => 'paraqon',
                    'room' => 'live-' . $storeId,
                    'data' => $data,
                    'event' => 'liveBidding',
                ]
            );
            return $response;
        } catch (\Throwable $th) {
            return null;
        }
    }

    public function getCurrentLot(Collection $lots)
    {
        // Find PREPARING lot
        $preparingLot = $lots->first(function ($lot) {
            return $lot->status === 'ARCHIVED' && !$lot->is_disabled && $lot->is_closed;
        });
        if ($preparingLot) {
            return $preparingLot;
        }

        // Find OPEN lot
        $openedLot = $lots->first(function ($lot) {
            return $lot->status === 'ACTIVE' && !$lot->is_disabled && !$lot->is_closed;
        });
        if ($openedLot) {
            return $openedLot;
        }

        $sortedLots = $lots->sortBy('lot_number')->values();

        // Return last lot_number if all SOLD / CLOSE
        $isAllLotsDisabled = $lots->every(function ($lot) {
            return $lot->is_disabled;
        });
        if ($isAllLotsDisabled) {
            return $sortedLots->last();
        }

        // Return first lot_number if all UPCOMING
        $isAllLotsUpcoming = $lots->every(function ($lot) {
            return $lot->status == 'ARCHIVED' && !$lot->is_disabled && !$lot->is_closed;
        });
        if ($isAllLotsUpcoming) {
            return $sortedLots->first();
        }

        // Find last updated SOLD or CLOSE lot
        $latestActiveLot = $lots->filter(function ($lot) {
            return $lot->status === 'ACTIVE';
        })->sortByDesc('updated_at')->first();

        return $latestActiveLot;
    }

    public function captureOrderPayment(Request $request)
    {
        $orders = Order::where('scheduled_payment_at', '<=', now())
            ->whereNull('scheduled_payment_received_at')
            ->get();

        $orderIDs = [];
        foreach ($orders as $order) {
            try {
                $checkout = $order->checkout()->latest()->first();
                $paymentIntentID = $checkout->online['payment_intent_id'];
                $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/capture';
                $response = Http::post($url, ['amount' => null]);
                if ($response->status() == 200) {
                    $orderIDs[] = $order->_id;
                    $order->update([
                        'is_paid' => true,
                        'scheduled_payment_received_at' => new UTCDateTime(now())
                    ]);

                    if ($order->current_status !== ShipmentDeliveryStatus::PROCESSING) {
                        $order->updateStatus(ShipmentDeliveryStatus::PROCESSING);
                    }
                }
            } catch (\Throwable $th) {
                Log::error('Failed to capture order, order_id: ' . $order->_id);
            }
        }

        return response()->json([
            'message' => 'Approved order count: ' . count($orderIDs),
            'order_ids' => $orderIDs
        ], 200);
    }

    public function synchronizeAllProductsWithAlgolia(Request $request)
    {
        $controller = new ProductManagementController($request);
        $data = $controller->filterProductsByCategories($request);
        // $data = $data->map(function ($datum) {
        //     $dateFields = ['created_at', 'updated_at', 'scheduled_at', 'published_at'];
        //     foreach ($dateFields as $field) {
        //         if (isset($datum[$field])) {
        //             $datum[$field] = Carbon::parse($datum[$field])->timestamp;

        //             echo $field . "\n";
        //             echo $datum[$field] . "\n";
        //             echo Carbon::parse($datum[$field])->timestamp . "\n";
        //             echo $datum[$field] . "\n";
        //             echo "\n";
        //         }
        //     }
        //     return $datum;
        // });

        $url = env('PARAQON_ALGOLIA_NODE_BASE_URL') . '/algolia/mass-update';
        $res = Http::post($url, [
            'index_name' => 'test_products',
            'data' => $data
        ]);
        return $res;
    }
}
