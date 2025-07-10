<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Account;
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
use StarsNet\Project\Paraqon\App\Models\Deposit;

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
            'setup_intent.succeeded', // Bind card
            'payment_method.attached'
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

        // Extract metadata from $request
        $model = $request->data['object']['metadata']['model_type'] ?? null;
        $modelID = $request->data['object']['metadata']['model_id'] ?? null;

        // ===============
        // Handle Events
        // ===============
        switch ($eventType) {
            case 'setup_intent.succeeded': {
                    // ---------------------
                    // If bind card success
                    // ---------------------
                    if ($model == 'customer') {
                        // Validate Customer
                        $customer = Customer::find($modelID);
                        if (is_null($customer)) {
                            return response()->json(['message' => 'Customer not found'], 200);
                        }

                        // Update Customer
                        $paymentMethodID = $request->data['object']['payment_method'] ?? null;
                        $customer->update(['stripe_payment_method_id' => $paymentMethodID]);

                        return [
                            'message' => 'Customer updated',
                            'customer_id' => $customer->_id,
                        ];
                    }
                    break;
                }
            case 'payment_method.attached': {
                    // ---------------------
                    // When Stripe DB created a new customer
                    // ---------------------
                    $paymentMethodID = $request->data['object']['id'] ?? null;

                    // Find Customer via stripe_payment_method_id
                    $customer = Customer::where('stripe_payment_method_id', $paymentMethodID)
                        ->latest()
                        ->first();
                    if (is_null($customer)) {
                        return response()->json(['message' => 'Customer not found'], 200);
                    }

                    // Update Customer
                    $stripeCustomerID = $request->data['object']['customer'] ?? null;
                    $cardData = $request->data['object']['card'];

                    $customer->update([
                        'stripe_customer_id' => $stripeCustomerID,
                        'stripe_card_binded_at' => now()->toISOString(),
                        'stripe_card_data' => $cardData
                    ]);

                    return [
                        'message' => 'Customer updated',
                        'customer_id' => $customer->_id,
                    ];
                }
            case 'charge.succeeded': {
                    if ($model === 'deposit') {
                        // Validate Deposit, then AuctionRegistrationRequest
                        $deposit = Deposit::find($modelID);
                        if (is_null($deposit)) {
                            return response()->json(['message' => 'Deposit not found'], 404);
                        }

                        $auctionRegistrationRequest = $deposit->auctionRegistrationRequest;
                        if (is_null($auctionRegistrationRequest)) {
                            return response()->json(['message' => 'AuctionRegistrationRequest not found'], 404);
                        }

                        // Update Deposit
                        $deposit->updateStatus('on-hold');
                        $deposit->update(['reply_status' => ReplyStatus::APPROVED]);
                        $deposit->updateOnlineResponse($request->all());

                        if ($auctionRegistrationRequest->reply_status === ReplyStatus::APPROVED) {
                            return ['message' => 'Deposit approved'];
                        }

                        // Update AuctionRegistrationRequest
                        $assignedPaddleId = $auctionRegistrationRequest->paddle_id;

                        if (is_null($assignedPaddleId)) {
                            $storeID = $auctionRegistrationRequest->store_id;
                            $allPaddles = AuctionRegistrationRequest::where('store_id', $storeID)
                                ->pluck('paddle_id')
                                ->filter(fn($id) => is_numeric($id))
                                ->map(fn($id) => (int) $id)
                                ->sort()
                                ->values();
                            $latestPaddleId = $allPaddles->last();

                            $assignedPaddleId = is_null($latestPaddleId) ?
                                $store->paddle_number_start_from ?? 1 :
                                $latestPaddleId + 1;
                        }

                        $requestUpdateAttributes = [
                            'paddle_id' => $assignedPaddleId,
                            'status' => Status::ACTIVE,
                            'reply_status' => ReplyStatus::APPROVED
                        ];
                        $auctionRegistrationRequest->update($requestUpdateAttributes);

                        return [
                            'message' => 'Deposit status updated as on-hold',
                            'deposit_id' => $deposit->_id
                        ];
                    }

                    if ($model === 'checkout') {
                        // Validate Checkout, then Order
                        $checkout = Checkout::find($modelID);
                        if (is_null($checkout)) {
                            return response()->json(['message' => 'Checkout not found'], 404);
                        }

                        $order = $checkout->order;
                        if (is_null($order)) {
                            return response()->json(['message' => 'Order not found'], 404);
                        }

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

                        if (!is_null($store) && in_array($store->auction_type, ['LIVE', 'ONLINE'])) {
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

                        return [
                            'message' => 'Checkout approved, and Order status updated',
                            'order_id' => $order->_id
                        ];
                    }
                }
            case 'charge.refunded': {
                    if ($model === 'deposit') {
                        // Validate Deposit
                        $deposit = Deposit::find($modelID);
                        if (is_null($deposit)) {
                            return response()->json(['message' => 'Deposit not found'], 404);
                        }

                        // Update Deposit
                        $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                        $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;
                        $deposit->update([
                            'amount_captured' => $amountCaptured / 100,
                            'amount_refunded' => $amountRefunded / 100,
                        ]);

                        $deposit->updateStatus('returned');

                        return [
                            'message' => 'Deposit status updated as returned',
                            'deposit_id' => $deposit->_id
                        ];
                    }
                }
            case 'charge.captured': {
                    if ($model === 'deposit') {
                        // Validate Deposit
                        $deposit = Deposit::find($modelID);
                        if (is_null($deposit)) {
                            return response()->json(['message' => 'Deposit not found'], 404);
                        }

                        // Update Deposit
                        $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                        $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;
                        $deposit->update([
                            'amount_captured' => $amountCaptured / 100,
                            'amount_refunded' => $amountRefunded / 100,
                        ]);

                        $deposit->updateStatus('returned');

                        return [
                            'message' => 'Deposit status updated as returned',
                            'deposit_id' => $deposit->_id
                        ];
                    }
                }
            case 'charge.expired': {
                    if ($model === 'deposit') {
                        // Validate Deposit
                        $deposit = Deposit::find($modelID);
                        if (is_null($deposit)) {
                            return response()->json(['message' => 'Deposit not found'], 404);
                        }

                        // Update Deposit
                        $amountCaptured = $request->data['object']['amount_captured'] ?? 0;
                        $amountRefunded = $request->data['object']['amount_refunded'] ?? 0;
                        $deposit->update([
                            'amount_captured' => $amountCaptured / 100,
                            'amount_refunded' => $amountRefunded / 100,
                        ]);

                        $deposit->updateStatus('returned');

                        return [
                            'message' => 'Deposit status updated as returned',
                            'deposit_id' => $deposit->_id
                        ];
                    }
                }
            default: {
                    return response()->json(
                        [
                            'message' => "Invalid eventType given: $eventType",
                            'acceptable_event_types' => $acceptableEventTypes
                        ],
                        400
                    );
                }
        }
    }

    public function createAuctionOrder(Request $request)
    {
        $originalOrder = Order::find($request->order_id);
        if (is_null($originalOrder)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $customer = Customer::find($originalOrder->customer_id);
        if (is_null($customer)) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $store = Store::find($originalOrder->store_id);
        if (is_null($store)) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        // Validate Stripe payment info
        $stripeCustomerID = $customer->stripe_customer_id;
        $stripePaymentMethodID = $customer->stripe_payment_method_id;
        $stripeCardData = $customer->stripe_card_data;

        if (
            is_null($stripeCustomerID) ||
            is_null($stripeCustomerID) ||
            is_null($stripeCardData)
        ) {
            return response()->json([
                'message' => 'Customer stripe payment info not found'
            ], 404);
        }

        // Validate card
        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');

        $expYear = (int) $stripeCardData['exp_year'];
        $expMonth = (int) $stripeCardData['exp_month'];

        if (!($expYear > $currentYear ||
            ($expYear === $currentYear && $expMonth >= $currentMonth)
        )) {
            return response()->json([
                'message' => 'Customer stripe payment info expired'
            ], 404);
        }

        // Create Order
        $newOrderAttributes = [
            'payment_method' => CheckoutType::ONLINE,
            'cart_items' => $originalOrder['cart_items']->toArray(),
            'gift_items' => $originalOrder['gift_items']->toArray(),
            'discounts' => $originalOrder['discounts'],
            'calculations' => $originalOrder['calculations'],
            'delivery_info' => $originalOrder['delivery_info'],
            'delivery_details' => $originalOrder['delivery_details'],
            'is_paid' => false,
            'is_voucher_applied' => false,
            'is_system' => false,
            'payment_information' => [
                'currency' => 'HKD',
                'conversion_rate' => 1
            ],
        ];
        $newOrder = $customer->createOrder($newOrderAttributes, $store);

        // Update Order
        $status = Str::slug(ShipmentDeliveryStatus::SUBMITTED);
        $newOrder->updateStatus($status);

        // Create Checkout
        $attributes = [
            'payment_method' => CheckoutType::ONLINE
        ];
        $checkout = $newOrder->checkout()->create($attributes);

        // Validate charge
        $totalPrice = $originalOrder['calculations']['price']['total'];
        $stripeAmount = (int) $totalPrice * 100;

        if ($stripeAmount < 400) {
            return response()->json([
                'message' => "Given stripe amount is $stripeAmount (\$$totalPrice), which is lower than the min of 400 ($4.00)"
            ], 404);
        }

        // Create and force payment via Stripe
        try {
            $stripeData = [
                "amount" => $stripeAmount,
                "currency" => 'hkd',
                "customer_id" => $stripeCustomerID,
                "payment_method_id" => $stripePaymentMethodID,
                "metadata" => [
                    "model_type" => 'checkout',
                    "model_id" => $checkout->_id
                ]
            ];

            $url = env('TCG_BID_STRIPE_BASE_URL', 'http://192.168.0.83:8082') . '/bind-card/charge';
            $response = Http::post(
                $url,
                $stripeData
            );

            if ($response->failed()) {
                $error = $response->json()['error'] ?? 'Stripe API request failed';
                throw new \Exception(json_encode($error));
            }

            $paymentIntentID = $response['id'];
            $clientSecret = $response['clientSecret'];

            $checkout->update([
                'amount' => $totalPrice,
                'currency' => 'hkd',
                'online' => [
                    'payment_intent_id' => $paymentIntentID,
                    'client_secret' => $clientSecret,
                    'api_response' => null
                ],
            ]);

            return [
                'message' => 'Submitted Order successfully',
                'checkout' => $checkout,
                'order_id' => $newOrder->_id
            ];
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment processing failed',
                'error' => json_decode($e->getMessage(), true) ?: $e->getMessage()
            ], 400);
        }
    }
}
