<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\DiscountTemplateDiscountType;
use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Events\Common\Order\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Cashier;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Http\Controllers\Admin\ShoppingCartController as AdminShoppingCartController;
use StarsNet\Project\ClsPackaging\App\Models\CustomerGroupOrder;
use StarsNet\Project\ClsPackaging\App\Constants\Model\OrderType;

class ShoppingCartController extends AdminShoppingCartController
{
    use ShoppingCartTrait,
        CheckoutTrait,
        WarehouseInventoryTrait;

    public function checkOut(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'customer_id' => [
                'required',
                'exists:App\Models\Customer,_id'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Get Customer
        /** @var Customer $customer */
        $customer = Customer::find($request->customer_id);

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
            ],
            'cashier_id' => [
                'required',
                'exists:App\Models\Cashier,_id'
            ],
            'user_id' => [
                'required',
                'exists:App\Models\User,id'
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
        $cashierID = $request->cashier_id;
        $userID = $request->user_id;

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

        // Create Order
        $orderAttributes = [
            'payment_method' => $paymentMethod,
            'discounts' => $checkoutDetails['discounts'],
            'calculations' => $checkoutDetails['calculations'],
            'delivery_info' => $this->getDeliveryInfo($deliveryInfo),
            'delivery_details' => $deliveryDetails,
            'is_voucher_applied' => $checkoutDetails['is_voucher_applied'],
        ];
        $order = $customer->createOrder($orderAttributes, $this->store);

        // Attach relationships
        $cashier = Cashier::find($request->cashier_id);
        $order->associateCashier($cashier);

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
            $attributes['sku'] = $variant->sku;
            // $this->deductWarehouseInventoriesByStore(
            //     $this->store,
            //     $variant,
            //     $qty,
            //     WarehouseInventoryHistoryType::SALES,
            //     $customer->getUser()
            // );

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
            // $this->deductWarehouseInventoriesByStore(
            //     $this->store,
            //     $variant,
            //     $qty,
            //     WarehouseInventoryHistoryType::SALES,
            //     $customer->getUser()
            // );

            $order->createGiftItem($attributes);
        }

        // Update Order
        $status = Str::slug(ShipmentDeliveryStatus::SUBMITTED);
        $order->updateStatus($status);

        // Create Checkout
        $checkout = $this->createBasicCheckout($order);
        switch ($paymentMethod) {
            case CheckoutType::ONLINE:
                $returnUrl = $this->updateAsOnlineCheckout(
                    $checkout,
                    $successUrl,
                    $cancelUrl
                );
                break;
            case CheckoutType::OFFLINE:
                $imageUrl = $request->input('image', '');
                $this->updateAsOfflineCheckout($checkout, $imageUrl);
                break;
            default:
                return response()->json([
                    'message' => 'Invalid payment_method'
                ], 404);
        }

        // Create CheckoutApproval
        // $user = User::find($request->user_id);
        // $checkout->createApproval(
        //     CheckoutApprovalStatus::APPROVED,
        //     null,
        //     $user
        // );

        // TODO: Uncomment in production
        // Update MembershipPoint, for Offline Payment via MINI Store
        if ($paymentMethod === CheckoutType::OFFLINE && $requiredPoints > 0) {
            $history = $customer->deductMembershipPoints($requiredPoints);
            $history->update(['remarks' => 'Redemption Record for Order ID: ' . $order->_id]);
        }

        // Differentiate 3 types of order
        $orderType = $request->order_type;
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        $access = CustomerGroupOrder::create(['type' => $orderType]);
        $access->associateOrder($order);
        $access->syncCustomerGroups($groups);

        // Fire event
        event(new OrderCreated($order));

        // Return data
        $data = [
            'message' => 'Submitted Order successfully',
            'return_url' => $returnUrl ?? null,
            'order_id' => $order->_id
        ];

        return response()->json($data);
    }
}
