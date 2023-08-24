<?php

namespace StarsNet\Project\ShoppingCart\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;
use Illuminate\Http\Request;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Events\Common\Order\OrderPaid;
use App\Models\DiscountCode;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Models\Checkout;
use App\Models\Order;
use App\Traits\StarsNet\PinkiePay;

use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WarehouseInventoryTrait;

use Illuminate\Support\Facades\Log;


class ShoppingCartController extends CustomerShoppingCartController
{

    use ShoppingCartTrait,
        CheckoutTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait;

    private function updateAsOnlineCheckout(Checkout $checkout, string $successUrl, string $cancelUrl): string
    {
        /** @var Order $order */
        $order = $checkout->order;

        Log::info($order);
        // Instantiate PinkiePay
        $pinkiePay = new PinkiePay($order, $successUrl, $cancelUrl);

        $isServiceRunning = $pinkiePay->healthCheck();
        if (!$isServiceRunning) {
            abort(
                response()->json(
                    ['message' => 'Payment Gateway is down, unable to create payment token'],
                    404
                )
            );
        }

        // Create Payment token
        $amount = $order->amount_received;
        $data = $pinkiePay->createPaymentToken($amount);

        // Update Checkout
        $transactionID = $data['transaction_id'];
        $checkout->updateOnlineTransactionID($transactionID);

        return $data['shortened_url'];
    }

    public function checkOut(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($this->store);
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
            // 'delivery_info.warehouse_id' => [
            //     Rule::requiredIf(fn () => $request->delivery_info['method'] === OrderDeliveryMethod::SELF_PICKUP),
            //     'exclude_if:delivery_info.method,' . OrderDeliveryMethod::DELIVERY,
            //     'exists:App\Models\Warehouse,_id'
            // ],
            // 'delivery_info.courier_id' => [
            //     Rule::requiredIf(fn () => $request->delivery_info['method'] === OrderDeliveryMethod::DELIVERY),
            //     'exclude_if:delivery_info.method,' . OrderDeliveryMethod::SELF_PICKUP,
            //     'exists:App\Models\Courier,_id'
            // ],
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
            'success_url' => [
                Rule::requiredIf(fn () => $request->payment_method === OrderPaymentMethod::ONLINE),
            ],
            'cancel_url' => [
                Rule::requiredIf(fn () => $request->payment_method === OrderPaymentMethod::ONLINE),
            ]
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
            $this->store,
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

        // Service Fee
        $serviceFee =  $request->input('fixed_fee', 0) + $totalPrice * $request->input('variable_fee', 0);
        $totalPricePlusServiceFee =  $totalPrice + $serviceFee;

        $serviceFee = (string) $serviceFee;
        $totalPricePlusServiceFee = (string) $totalPricePlusServiceFee;

        // Create Order
        $orderAttributes = [
            'is_paid' => $request->input('is_paid', false),
            'payment_method' => $paymentMethod,
            'discounts' => $checkoutDetails['discounts'],
            'calculations' => $checkoutDetails['calculations'],
            'delivery_info' => $this->getDeliveryInfo($deliveryInfo),
            'delivery_details' => $deliveryDetails,
            'is_voucher_applied' => $checkoutDetails['is_voucher_applied'],
            'change' => $serviceFee,
            'amount_received' => $totalPricePlusServiceFee

        ];
        Log::info($orderAttributes);
        $order = $customer->createOrder($orderAttributes, $this->store);
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
                $this->store,
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
                $this->store,
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

        if ($order->getTotalPrice() > 0) {
            switch ($paymentMethod) {
                case CheckoutType::ONLINE:
                    $returnUrl = $this->updateAsOnlineCheckout(
                        $checkout,
                        $successUrl,
                        $cancelUrl
                    );
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

            $description = 'Redemption Record for Order ID: ' . $order->_id;
            $historyAttributes = [
                'description' => [
                    'en' => $description,
                    'zh' => $description,
                    'cn' => $description,
                ],
                'remarks' => $description
            ];
            $history->update($historyAttributes);
        }

        // Delete ShoppingCartItem(s)
        if ($paymentMethod === CheckoutType::OFFLINE) {
            $variants = ProductVariant::objectIDs($request->checkout_product_variant_ids)->get();
            $customer->clearCartByStore($this->store, $variants);
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

        // TODO: Used count per discount

        // Return data
        $data = [
            'message' => 'Submitted Order successfully',
            'return_url' => $returnUrl ?? null,
            'order_id' => $order->_id
        ];

        return response()->json($data);
    }
}
