<?php

namespace StarsNet\Project\Stripe\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\WarehouseInventoryHistoryType;

use App\Http\Controllers\Controller;

use App\Models\Alias;
use App\Models\DiscountCode;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Configuration;

use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WarehouseInventoryTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Events\Common\Order\OrderPaid;

use StarsNet\Project\Stripe\App\Events\Common\Payment\PaidFromStripe;

class OrderController extends Controller
{
    use ShoppingCartTrait,
        CheckoutTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait;

    public function checkOut(Request $request)
    {
        // Get store
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        if (is_null($store)) {
            $storeID = Alias::getValue($storeID);
            $store = Store::find($storeID);
        }

        // Get authenticated User information
        $customer = $this->customer();

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);
        $addedProductVariantIDs = $cartItems->pluck('product_variant_id')->all();

        // Validate Request
        $validator = Validator::make($request->all(), [
            'checkout_product_variant_ids' => [
                'array',
            ],
            'checkout_product_variant_ids.*' => [
                Rule::in($addedProductVariantIDs)
            ],
            'voucher_code' => [
                'nullable'
            ],
            'delivery_info.country_code' => [
                'nullable'
            ],
            'delivery_info.method' => [
                'nullable',
                Rule::in(OrderDeliveryMethod::$defaultTypes)
            ],
            'delivery_details.recipient_name' => [
                'nullable'
            ],
            'delivery_details.email' => [
                'nullable',
                'email'
            ],
            'delivery_details.area_code' => [
                'nullable',
                'numeric'
            ],
            'delivery_details.phone' => [
                'nullable',
                'numeric'
            ],
            'delivery_details.address' => [
                'nullable',
            ],
            'payment_method' => [
                'required',
                Rule::in(OrderPaymentMethod::$defaultTypes)
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $checkoutVariantIDs = $request->checkout_product_variant_ids;
        $voucherCode = $request->voucher_code;
        $deliveryInfo = $request->delivery_info;
        $deliveryDetails = $request->delivery_details;
        $paymentMethod = $request->payment_method;
        $successUrl = $request->success_url;
        $cancelUrl = $request->cancel_url;

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;
        $warehouseID = $deliveryInfo['method'] === OrderDeliveryMethod::SELF_PICKUP ?
            $deliveryInfo['warehouse_id'] :
            null;

        // Get Checkout information
        $checkoutDetails = $this->getShoppingCartDetailsByCustomerAndStore(
            $customer,
            $store,
            $checkoutVariantIDs,
            $voucherCode,
            $courierID
        );

        // Validate Customer membership points
        $requiredPoints = $checkoutDetails['calculations']['point']['total'];
        if (!$customer->isEnoughMembershipPoints($requiredPoints)) {
            return response()->json([
                'message' => 'Customer does not have enough membership points for this transaction',
            ], 403);
        }

        // Validate, and update attributes
        $totalPrice = $checkoutDetails['calculations']['price']['total'];
        if ($totalPrice <= 0) $paymentMethod = CheckoutType::OFFLINE;

        // Create Order
        $orderAttributes = [
            'is_paid' => $request->input('is_paid', false),
            'payment_method' => $paymentMethod,
            'discounts' => $checkoutDetails['discounts'],
            'calculations' => $checkoutDetails['calculations'],
            'delivery_info' => $this->getDeliveryInfo($deliveryInfo),
            'delivery_details' => $deliveryDetails,
            'is_voucher_applied' => $checkoutDetails['is_voucher_applied'],
        ];
        $order = $customer->createOrder($orderAttributes, $store);
        $requiredPoints = $order->getTotalPoint();

        // Create OrderCartItem(s)
        $checkoutItems = collect($checkoutDetails['cart_items'])
            ->filter(function ($item) {
                return $item->is_checkout;
            })->values();

        foreach ($checkoutItems as $item) {
            $attributes = $item->toArray();
            unset($attributes['_id'], $attributes['is_checkout']);

            // Update WarehouseInventory(s)
            $variantID = $attributes['product_variant_id'];
            $qty = $attributes['qty'];
            /** @var ProductVariant $variant */
            $variant = ProductVariant::find($variantID);
            $this->deductWarehouseInventoriesByStore(
                $store,
                $variant,
                $qty,
                WarehouseInventoryHistoryType::SALES,
                $customer->getUser()
            );

            $order->createCartItem($attributes);
        }

        // Create OrderGiftItem(s)
        /** @var array $item */
        foreach ($checkoutDetails['gift_items'] as $item) {
            $attributes = $item;
            unset($attributes['_id'], $attributes['is_checkout']);

            // Update WarehouseInventory(s)
            $variantID = $attributes['product_variant_id'];
            $qty = $attributes['qty'];
            /** @var ProductVariant $variant */
            $variant = ProductVariant::find($variantID);
            $this->deductWarehouseInventoriesByStore(
                $store,
                $variant,
                $qty,
                WarehouseInventoryHistoryType::SALES,
                $customer->getUser()
            );

            $order->createGiftItem($attributes);
        }

        // Update Order
        $status = Str::slug(ShipmentDeliveryStatus::SUBMITTED);
        $order->updateStatus($status);

        // Create Checkout
        $checkout = $this->createBasicCheckout($order, $paymentMethod);

        $orderID = $order->id;
        if ($order->getTotalPrice() > 0) {
            switch ($paymentMethod) {
                case CheckoutType::ONLINE:
                    /** @var Order $order */
                    $order = $checkout->order;
                    $amount = $order->calculations['price']['total'];

                    $slug = 'stripe-payment-credentials';
                    $config = Configuration::getLatestBySlug($slug);

                    // $stripePublicKey = 'pk_test_51PYOvHKw8pdwqpDMSSjTtHxgPefPItqK2xGiRYOz36Q0gTq9IuMRbTT8gb32CBpadoVxY5Eal30kz2nLaNVWBlPh00ObrQ5ilC' ?? $config->public_key;
                    // $stripeSecretKey = 'sk_test_51PYOvHKw8pdwqpDMrt7tpzK93BIud6ZwLYWmgVGsFOL9NahAYvtNEpsahNv5ISRQZgkBMBwWBoCAHvahy5pchqUF00IzrMD0PT' ?? $config->secret_key;

                    $stripePublicKey = $config->public_key;
                    $stripeSecretKey = $config->secret_key;

                    $url = 'https://api.stripe.com/v1/payment_intents';
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer {$stripeSecretKey}",
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ])->asForm()
                        ->post(
                            $url,
                            [
                                "amount" => $amount * 100,
                                "currency" => 'HKD'
                            ]
                        );

                    Log::info($response);

                    // Get response from PinkiePay Service
                    try {
                        $url = 'https://api.stripe.com/v1/payment_intents';
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$stripeSecretKey}",
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ])->asForm()
                            ->post(
                                $url,
                                [
                                    "amount" => $amount * 100,
                                    "currency" => 'HKD'
                                ]
                            );
                        Log::info($response);

                        $transactionID = $response["id"];
                        $clientSecret = $response["client_secret"];

                        Log::info(['transactionId' => $transactionID]);
                        Log::info(['clientSecret' => $clientSecret]);

                        $checkout->updateOnlineTransactionID($transactionID);
                    } catch (\Throwable $th) {
                        return 'Transaction failed';
                    }
                    break;
                case CheckoutType::OFFLINE:
                    $imageUrl = $request->input('image');
                    $this->updateAsOfflineCheckout($checkout, $imageUrl);
                    break;
                default:
                    return response()->json([
                        'message' => 'Invalid payment_method'
                    ], 404);
            }
        } else {
            event(new OrderPaid($order, $customer));
        }

        // Update MembershipPoint, for Offline Payment via MINI Store
        if ($paymentMethod === CheckoutType::OFFLINE && $requiredPoints > 0) {
            $history = $customer->deductMembershipPoints($requiredPoints);

            $historyAttributes = [
                // 'description' => [
                //     'en' => 'Redemption Record for Order ID: ' . $order->_id,
                //     'zh' =>
                //     '兌換記錄 訂單編號 ' . $order->_id,
                //     'cn' =>
                //     '兑换记录 订单编号:' . $order->_id,
                // ],
                'description' => [
                    'en' => 'Thank you for your redemption.',
                    'zh' => '兌換成功！謝謝！',
                    'cn' => '兑换成功！谢谢！'
                ],
                'remarks' => ''
            ];
            $history->update($historyAttributes);
        }

        // Delete ShoppingCartItem(s)
        if ($paymentMethod === CheckoutType::OFFLINE) {
            $variants = ProductVariant::objectIDs($request->checkout_product_variant_ids)->get();
            $customer->clearCartByStore($store, $variants);
        }

        // Use voucherCode
        if (!is_null($voucherCode)) {
            /** @var DiscountCode $voucher */
            $voucher = DiscountCode::whereFullCode($voucherCode)
                ->whereIsUsed(false)
                ->whereIsDisabled(false)
                ->first();

            if (!is_null($voucher)) {
                $voucher->usedByOrder($order);
            }
        }

        // Return data
        if (
            $paymentMethod === CheckoutType::ONLINE
        ) {
            $data = [
                'message' => 'Created a transaction from Stripe successfully',
                'order_id' => $orderID,
                'transaction_id' => $transactionID,
                'client_secret' => $clientSecret
            ];
            return response()->json($data);
        } else {
            $data = [
                'message' => 'Created new order',
                'order_id' => $orderID,
                'transaction_id' => null,
                'client_secret' => null
            ];
            return response()->json($data);
        }
    }

    public function onlinePaymentCallback(Request $request)
    {
        event(new PaidFromStripe($request));
        return response()->json('SUCCESS', 200);
    }
}
