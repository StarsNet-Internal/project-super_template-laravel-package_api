<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\Checkout;
use App\Models\Courier;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Traits\Utils\RoundingTrait;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Traits\StarsNet\PinkiePay;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use Illuminate\Support\Str;

class ShoppingCartController extends Controller
{
    use RoundingTrait;

    private function getStore(string $storeID): ?Store
    {
        // Get Store via id
        $store = Store::find($storeID);
        if (!is_null($store)) return $store;

        // Get Store via alias
        $storeID = Alias::getValue($storeID);
        $store = Store::find($storeID);
        return $store;
    }

    public function getAllAuctionCartItems(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = self::getStore($storeID);

        // Get authenticated User information
        $customer = $this->customer();

        // Clear Store first
        $customer->clearCartByStore($store);

        // Winning auction lots by Customer
        $wonLots = AuctionLot::where('store_id', $store->_id)
            ->where('winning_bid_customer_id', $customer->_id)
            ->get();

        foreach ($wonLots as $key => $lot) {
            $attributes = [
                'store_id' => $store->_id,
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

        // Extract attributes from $request
        $isStorage = $request->boolean('is_storage', false);
        $deliveryInfo = $request->delivery_info;

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;

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

            if ($isStorage == true) {
                $storageFee += $item->storage_fee ?? 0;
            }
        }
        $totalPrice = $subtotalPrice + $storageFee + $totalServiceCharge;

        // get shippingFee
        $courier = Courier::find($courierID);
        $shippingFee =
            !is_null($courier) ?
            $courier->getShippingFeeByTotalFee($totalPrice) :
            0;
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
        $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation, 2); // Round off values

        // Round down calculations.price.total only
        $roundedCalculation['price']['total'] = floor($roundedCalculation['price']['total']);
        $roundedCalculation['price']['total'] .= '.00';

        // Return data
        $data = [
            'cart_items' => $cartItems,
            'gift_items' => [],
            'discounts' => [],
            'calculations' => $roundedCalculation,
            'is_voucher_applied' => false,
            'is_enough_membership_points' => true
        ];

        return $data;
    }

    public function getAllMainStoreCartItems(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = self::getStore($storeID);

        // Extract attributes from $request
        $checkoutVariantIDs = $request->checkout_product_variant_ids;
        $voucherCode = $request->voucher_code;
        $deliveryInfo = $request->delivery_info;

        // Get authenticated User information
        $customer = $this->customer();

        // Winning auction lots by Customer
        $selectedItems = ShoppingCartItem::where('store_id', $store->_id)->get();

        foreach ($selectedItems as $key => $item) {
            $item->update([
                'winning_bid' => 0,
                'storage_fee' => 0
            ]);
        }

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);

        // Extract attributes from $request
        $deliveryInfo = $request->delivery_info;

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;

        // getShoppingCartDetails calculations
        // get subtotal Price
        $subtotalPrice = 0;
        $storageFee = 0;

        foreach ($cartItems as $item) {
            // Add keys
            $item->is_checkout = true;
            $item->is_refundable = false;
            $item->global_discount = null;

            // Calculations
            $winningBid = $item->winning_bid ?? 0;
            $subtotalPrice += $winningBid;

            $storageFee += $item->storage_fee ?? 0;
        }
        $totalPrice = $subtotalPrice + $storageFee;

        // get shippingFee
        $courier = Courier::find($courierID);
        $shippingFee =
            !is_null($courier) ?
            $courier->getShippingFeeByTotalFee($totalPrice) :
            0;
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
            'service_charge' => 0,
            'storage_fee' => $storageFee,
            'shipping_fee' => $shippingFee
        ];

        $rationalizedCalculation = $this->rationalizeRawCalculation($rawCalculation);
        $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation, 2); // Round off values

        // Round down calculations.price.total only
        $roundedCalculation['price']['total'] = floor($roundedCalculation['price']['total']);
        $roundedCalculation['price']['total'] .= '.00';

        // Return data
        $data = [
            'cart_items' => $cartItems,
            'gift_items' => [],
            'discounts' => [],
            'calculations' => $roundedCalculation,
            'is_voucher_applied' => false,
            'is_enough_membership_points' => true
        ];

        return $data;
    }

    public function checkOutAuctionStore(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = self::getStore($storeID);

        // Get authenticated User information
        $customer = $this->customer();

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);

        // Extract attributes from $request
        $checkoutVariantIDs = $request->checkout_product_variant_ids;
        $voucherCode = $request->voucher_code;
        $deliveryInfo = $request->delivery_info;
        $deliveryDetails = $request->delivery_details;
        $paymentMethod = $request->payment_method;
        $successUrl = $request->success_url;
        $cancelUrl = $request->cancel_url;

        $isStorage = $request->boolean('is_storage', false);

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;
        $warehouseID = $deliveryInfo['method'] === OrderDeliveryMethod::SELF_PICKUP ?
            $deliveryInfo['warehouse_id'] :
            null;

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

            if ($isStorage == true) {
                $storageFee += $item->storage_fee ?? 0;
            } else {
                $item->storage_fee == 0;
            }
        }
        $totalPrice = $subtotalPrice +
            $storageFee + $totalServiceCharge;

        // get shippingFee
        $courier = Courier::find($courierID);
        $shippingFee =
            !is_null($courier) ?
            $courier->getShippingFeeByTotalFee($totalPrice) :
            0;
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

            'paid_order_id' => null,
            'is_storage' => $isStorage
        ];
        $order = $customer->createOrder($orderAttributes, $store);

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
        }

        // if ($paymentMethod === CheckoutType::OFFLINE) {
        //     // Delete ShoppingCartItem(s)
        //     $variants = ProductVariant::objectIDs($request->checkout_product_variant_ids)->get();
        //     $customer->clearCartByStore($store, $variants);

        //     // Update product
        //     foreach ($variants as $variant) {
        //         $product = $variant->product;
        //         $product->update([
        //             'owned_by_customer_id' => $this->customer()->_id,
        //             'listing_status' => 'AVAILABLE'
        //         ]);
        //     }
        // }

        // Return data
        $data = [
            'message' => 'Submitted Order successfully',
            'return_url' => $returnUrl ?? null,
            'order_id' => $order->_id
        ];

        return response()->json($data);
    }

    public function checkOutMainStore(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = self::getStore($storeID);

        // Get authenticated User information
        $customer = $this->customer();

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);

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

        // getShoppingCartDetails calculations
        // get subtotal Price
        $subtotalPrice = 0;
        $storageFee = 0;

        foreach ($cartItems as $item) {
            // Add keys
            $item->is_checkout = true;
            $item->is_refundable = false;
            $item->global_discount = null;

            // Calculations
            $winningBid = $item->winning_bid ?? 0;
            $subtotalPrice += $winningBid;

            $storageFee += $item->storage_fee ?? 0;
        }
        $totalPrice = $subtotalPrice + $storageFee;

        // get shippingFee
        $courier = Courier::find($courierID);
        $shippingFee =
            !is_null($courier) ?
            $courier->getShippingFeeByTotalFee($totalPrice) :
            0;
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
            'storage_fee' => $storageFee,
            'shipping_fee' => $shippingFee
        ];

        $rationalizedCalculation = $this->rationalizeRawCalculation($rawCalculation);
        $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation); // Round off values

        // Return data
        $checkoutDetails = [
            'cart_items' => $cartItems,
            'gift_items' => [],
            'discounts' => [],
            'calculations' => $roundedCalculation,
            'is_voucher_applied' => false,
            'is_enough_membership_points' => true,

            'paid_order_id' => null,
            'is_storage' => false
        ];

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
        }

        if ($paymentMethod === CheckoutType::OFFLINE) {
            // Delete ShoppingCartItem(s)
            $variants = ProductVariant::objectIDs($request->checkout_product_variant_ids)->get();
            $customer->clearCartByStore($store, $variants);

            // Update product
            foreach ($variants as $variant) {
                $product = $variant->product;
                $product->update([
                    // 'status' => Status::ACTIVE,
                    'listing_status' => 'ALREADY_CHECKOUT'
                ]);
            }
        }

        // Return data
        $data = [
            'message' => 'Submitted Order successfully',
            'return_url' => $returnUrl ?? null,
            'order_id' => $order->_id
        ];

        return response()->json($data);
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

    /*
    * ========================
    * Delivery Info Functions
    * =======================
    */

    private function getDeliveryInfo(array $rawInfo)
    {
        if ($rawInfo['method'] === OrderDeliveryMethod::DELIVERY) {
            $courierID = $rawInfo['courier_id'];
            /** @var Courier $courier */
            $courier = Courier::find($courierID);
            $courierInfo = [
                'title' => optional($courier)->title ?? null,
                'image' => $courier->images[0] ?? null,
            ];
            $rawInfo['courier'] = $courierInfo;
        }

        if ($rawInfo['method'] === OrderDeliveryMethod::SELF_PICKUP) {
            $warehouseID = $rawInfo['warehouse_id'];
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::find($warehouseID);
            $warehouseInfo = [
                'title' => optional($warehouse)->title ?? null,
                'image' => $warehouse->images[0] ?? null,
                'location' => $warehouse->location
            ];
            $rawInfo['warehouse'] = $warehouseInfo;
        }

        return $rawInfo;
    }

    /*
    * ===================
    * Warehouse Functions
    * ===================
    */

    private function deductWarehouseInventoriesByStore(
        Store $store,
        ProductVariant $variant,
        int $qtyChange,
        string $changeType = WarehouseInventoryHistoryType::OTHERS,
        User $user
    ) {
        if ($qtyChange === 0) return false;

        $inventories = $this->getActiveWarehouseInventoriesByStore($store, $variant);

        $remainder = $qtyChange;

        if ($inventories->count() > 0) {
            /** @var WarehouseInventory $inventory */
            foreach ($inventories as $inventory) {
                // Terminate condition
                if ($remainder <= 0) break;

                // Get available quantity per WarehouseInventory
                $availableInventoryQty = $inventory->qty;

                // Get deductable quantity
                $deductableQty = $remainder > $availableInventoryQty ?
                    $availableInventoryQty :
                    $remainder;

                // Update WarehouseInventory
                $inventory->decrementQty($deductableQty);

                // Update remainder
                $remainder -= $deductableQty;
            }
        }

        return true;
    }

    private function getActiveWarehouseInventoriesByStore(Store $store, ProductVariant $variant)
    {
        $warehouses = $this->getActiveWarehousesByStore($store);

        return $variant->warehouseInventories()
            ->byWarehouses($warehouses)
            ->orderByDesc('qty')
            ->get();
    }

    private function getActiveWarehousesByStore(Store $store)
    {
        return $store->warehouses()->statusActive()->get();
    }

    /*
    * ===================
    * Checkout Functions
    * ===================
    */

    private function createBasicCheckout(Order $order, string $paymentMethod = CheckoutType::ONLINE)
    {
        $attributes = [
            'payment_method' => $paymentMethod
        ];
        /** @var Checkout $checkout */
        $checkout = $order->checkout()->create($attributes);
        return $checkout;
    }

    private function updateAsOnlineCheckout(Checkout $checkout, string $successUrl, string $cancelUrl): string
    {
        /** @var Order $order */
        $order = $checkout->order;

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
        $amount = $order->getTotalPrice();
        $data = $pinkiePay->createPaymentToken($amount);

        // Update Checkout
        $transactionID = $data['transaction_id'];
        $checkout->updateOnlineTransactionID($transactionID);

        return $data['shortened_url'];
    }

    private function updateAsOfflineCheckout(Checkout $checkout, ?string $imageUrl): void
    {
        if (is_null($imageUrl)) return;

        /** @var Order $order */
        $order = $checkout->order;
        $checkout->updateOfflineImage($imageUrl);

        // Fire event
        event(new OfflineCheckoutImageUploaded($order));
        return;
    }
}
