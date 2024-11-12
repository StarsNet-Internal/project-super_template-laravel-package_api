<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Utils\RoundingTrait;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;
use StarsNet\Project\Paraqon\App\Models\PassedAuctionRecord;

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
            'charge.captured'
        ];

        if (!in_array($eventType, $acceptableEventTypes)) {
            return response()->json(
                [
                    'message' => 'Callback success, but event type does not belong to any of the acceptable values',
                    'acceptable_values' => $acceptableEventTypes
                ],
                200
            );
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
            case 'deposit':
                // Get Deposit
                $deposit = Deposit::find($modelID);

                if (is_null($deposit)) {
                    return response()->json(
                        ['message' => 'Deposit not found'],
                        404
                    );
                }

                // Update AuctionRegistrationRequest
                $auctionRegistrationRequest = $deposit->auctionRegistrationRequest;

                // Update Deposit
                if ($eventType == 'charge.succeeded') {
                    $deposit->updateStatus('on-hold');
                    $deposit->update([
                        'reply_status' => ReplyStatus::APPROVED
                    ]);
                    $deposit->updateOnlineResponse($request->all());

                    if (
                        $auctionRegistrationRequest->reply_status == ReplyStatus::PENDING
                    ) {
                        // get Paddle ID
                        $assignedPaddleID = $auctionRegistrationRequest->paddle_id;
                        $storeID = $auctionRegistrationRequest->store_id;

                        if (is_null($assignedPaddleID)) {
                            $highestPaddleID = AuctionRegistrationRequest::where('store_id', $storeID)
                                ->get()
                                ->max('paddle_id')
                                ?? 0;
                            $assignedPaddleID = $highestPaddleID + 1;
                        }

                        $requestUpdateAttributes = [
                            'paddle_id' => $assignedPaddleID,
                            'status' => Status::ACTIVE,
                            'reply_status' => ReplyStatus::APPROVED
                        ];
                        $auctionRegistrationRequest->update($requestUpdateAttributes);
                    }

                    return response()->json(
                        [
                            'message' => 'Deposit status updated as on-hold',
                            'deposit_id' => $deposit->_id
                        ],
                        200
                    );
                } else if ($eventType == 'charge.refunded') {
                    $deposit->updateStatus('cancelled');

                    $amountCaptured = $request->data['object']['amount_captured'] ?? null;
                    $amountRefunded = $request->data['object']['amount_refunded'] ?? null;
                    $deposit->update([
                        'amount_captured' => $amountCaptured,
                        'amount_refunded' => $amountRefunded,
                        // 'reply_status' => 'CANCELLED'
                    ]);

                    return response()->json(
                        [
                            'message' => 'Deposit status updated as cancelled',
                            'deposit_id' => $deposit->_id
                        ],
                        200
                    );
                } else if ($eventType == 'charge.captured') {
                    $deposit->updateStatus('returned');

                    $amountCaptured = $request->data['object']['amount_captured'] ?? null;
                    $amountRefunded = $request->data['object']['amount_refunded'] ?? null;

                    $deposit->update([
                        'amount_captured' => $amountCaptured,
                        'amount_refunded' => $amountRefunded,
                    ]);

                    return response()->json(
                        [
                            'message' => 'Deposit status updated as returned',
                            'deposit_id' => $deposit->_id
                        ],
                        200
                    );
                }

                return response()->json(
                    [
                        'message' => 'Invalid Stripe event type',
                        'deposit_id' => null
                    ],
                    400
                );
            case 'checkout':
                // Get Checkout
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

                // Update Checkout and Order
                if ($eventType == 'charge.succeeded') {
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
            default:
                return response()->json(
                    [
                        'message' => 'Invalid model_type for metadata',
                    ],
                    400
                );
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
            $startTime = Carbon::parse($store->start_datetime);
            $endTime = Carbon::parse($store->end_datetime);

            if ($now >= $startTime && $now < $endTime) {
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
            $endTime = Carbon::parse($store->end_datetime);

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
            $startTime = Carbon::parse($lot->start_datetime);
            $endTime = Carbon::parse($lot->end_datetime);

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
            $endTime = Carbon::parse($lot->end_datetime);

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

    public function generateAuctionOrdersAndRefundDeposits(Request $request)
    {
        // Get Store
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        // Check Store status
        if ($store->status === Status::ACTIVE) {
            return response()->json([
                'message' => "Store is still ACTIVE. Skipping generating auction order sequences."
            ], 200);
        }

        if ($store->status === Status::DELETED) {
            return response()->json([
                'message' => "Store is DELETED. Skipping generating auction order sequences."
            ], 200);
        }

        // Get Auction Lots
        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            // ->where('status', Status::ARCHIVED)
            ->whereNotNull('winning_bid_customer_id')
            ->get();
        $unpaidAuctionLots = $unpaidAuctionLots->filter(function ($item) {
            return $item->current_bid >= $item->reserve_price;
        });

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
                        'lot_number' => $lot->lot_number,
                        'winning_bid' => $lot->current_bid,
                        // 'storage_fee' => $lot->current_bid * 0.03
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
                    // $totalServiceCharge += $winningBid *
                    //     $SERVICE_CHARGE_MULTIPLIER;
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

                    /** @var ProductVariant $variant */
                    $order->createCartItem($attributes);
                }

                // Update Order
                $status = Str::slug(ShipmentDeliveryStatus::SUBMITTED);
                $order->updateStatus($status);

                // Create Checkout
                $this->createBasicCheckout($order, $paymentMethod);

                // Delete ShoppingCartItem(s)
                $variants = ProductVariant::objectIDs($variantIDs)->get();
                $customer->clearCartByStore($store, $variants);

                $generatedOrderCount++;
            } catch (\Throwable $th) {
                print($th);
            }
        }

        // Get All fullRefundDeposits
        $allFullRefundDeposits = Deposit::whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
            $query->whereHas('store', function ($query2) use ($storeID) {
                $query2->where('_id', $storeID);
            });
        })
            ->whereNotIn('requested_by_customer_id', $winningCustomerIDs)
            ->get();

        // Full Refund
        $refundUrl = 'https://payment.paraqon.starsnet.hk/refunds';
        foreach ($allFullRefundDeposits as $deposit) {
            try {
                $data = [
                    'payment_intent' => $deposit->online['payment_intent_id'],
                    'amount' => $deposit->amount
                ];

                $response = Http::post(
                    $refundUrl,
                    $data
                );

                if ($response->status() === 200) {
                    $deposit->update([
                        'refund_id' => $response['id']
                    ]);
                    $deposit->updateStatus('returned');
                } else {
                    $deposit->updateStatus('return-failed');
                }
            } catch (\Throwable $th) {
                $deposit->updateStatus('return-failed');
            }
        }

        // Do Partial or Full Refund for winning customers
        // $winningCustomerDeposits = Deposit::whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
        //     $query->whereHas('store', function ($query2) use ($storeID) {
        //         $query2->where('_id', $storeID);
        //     });
        // })
        //     ->whereIn('requested_by_customer_id', $winningCustomerIDs)
        //     ->get();

        // foreach ($winningCustomerIDs as $customerID) {
        //     $winningCustomerOrder = Order::where('customer_id', $customerID)
        //         ->where('store_id', $storeID)
        //         ->where('is_system', true)
        //         ->first();

        //     $currentChargeAmount = 0;
        // }

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
