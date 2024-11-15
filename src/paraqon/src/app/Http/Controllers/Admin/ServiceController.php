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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;
use StarsNet\Project\Paraqon\App\Models\PassedAuctionRecord;

use StarsNet\Project\Paraqon\App\Http\Controllers\Admin\AuctionLotController as AdminAuctionLotController;
use StarsNet\Project\Paraqon\App\Http\Controllers\Customer\AuctionLotController as CustomerAuctionLotController;

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
            'payment_intent.canceled'
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

                    $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                    $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;
                    $deposit->update([
                        'amount_captured' => $amountCaptured / 100,
                        'amount_refunded' => $amountRefunded / 100,
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

                    $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                    $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;

                    $deposit->update([
                        'amount_captured' => $amountCaptured / 100,
                        'amount_refunded' => $amountRefunded / 100,
                    ]);

                    return response()->json(
                        [
                            'message' => 'Deposit status updated as returned',
                            'deposit_id' => $deposit->_id
                        ],
                        200
                    );
                } else if (in_array($eventType, ['charge.expired', 'payment_intent.canceled'])) {
                    $deposit->updateStatus('returned');

                    $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                    $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;

                    $deposit->update([
                        'amount_captured' => $amountCaptured / 100,
                        'amount_refunded' => $amountRefunded / 100,
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

    private function cancelDeposit(Deposit $deposit)
    {
        switch ($deposit->payment_method) {
            case 'ONLINE':
                $paymentIntentID = $deposit->online['payment_intent_id'];
                $url = "https://payment.paraqon.starsnet.hk/payment-intents/{$paymentIntentID}/capture";

                try {
                    $response = Http::post(
                        $url,
                    );

                    Log::info('This is response for full refund deposit id: ' . $deposit->_id);
                    Log::info($response);
                    Log::info('---');

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
                $captureUrl = "https://payment.paraqon.starsnet.hk/payment-intents/{$paymentIntentID}/cancel";

                try {
                    $data = [
                        'amount' => $captureAmount * 100
                    ];

                    $response = Http::post(
                        $captureUrl,
                        $data
                    );

                    Log::info('This is response for capture deposit id: ' . $deposit->_id);
                    Log::info($data);
                    Log::info($response);
                    Log::info('---');

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

            // Get winning_bid, update subtotal_price
            $itemTotalPrice += $item->winning_bid;
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
        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->where('status', Status::ARCHIVED)
            ->whereNotNull('winning_bid_customer_id')
            ->get()
            ->filter(function ($item) {
                return $item->current_bid >= $item->reserve_price;
            });

        // Get unique winning_bid_customer_id from all AuctionLot(s)
        $winningCustomerIDs = $unpaidAuctionLots
            ->pluck('winning_bid_customer_id')
            ->unique()
            ->values()
            ->all();

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

        foreach ($winningCustomerIDs as $customerID) {
            try {
                $customer = Customer::find($customerID);

                // Find all winning Auction Lots
                $winningLots = $unpaidAuctionLots->where('winning_bid_customer_id', $customerID);

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

        $request->route()->setParameter('auction_lot_id', $currentLot->_id);
        $customerAuctionLotController = new CustomerAuctionLotController();
        $histories = $customerAuctionLotController->getBiddingHistory($request);

        $data = [
            'lots' => $lots,
            'current_lot_id' => $currentLot->_id,
            'histories' => $histories,
            'time' => now(),
        ];

        try {
            $response = Http::post('http://192.168.0.101:8881/api/publish', [
                'site' => 'paraqon',
                'room' => 'live-' . $storeId,
                'data' => $data,
                'event' => 'liveBidding',
            ]);
            return $response;
        } catch (\Throwable $th) {
            return null;
        }
    }

    public function getCurrentLot(Collection $lots)
    {
        // Try to find a lot with `is_disabled = true` and `status = ARCHIVED`
        $archivedLot = $lots->first(function ($lot) {
            return $lot->is_disabled && $lot->status === 'ARCHIVED';
        });

        if ($archivedLot) {
            return $archivedLot;
        }

        // If no archived lot found, find the active lot with the largest `lot_number`
        $largestActiveLot = $lots->filter(function ($lot) {
            return $lot->status === 'ACTIVE';
        })->sortByDesc('lot_number')->first();

        if ($largestActiveLot) {
            return $largestActiveLot;
        }

        return $lots->sortBy('lot_number')->first();
    }
}
