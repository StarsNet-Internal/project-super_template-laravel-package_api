<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Traits\Controller\StoreDependentTrait;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Customer\OrderController as CustomerOrderController;

class OrderManagementController extends CustomerOrderController
{
    use StoreDependentTrait;

    protected $model = Order::class;

    public function getAllOrdersByStore(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'current_status' => [
                'nullable',
                'string'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $statuses = (array) $request->input('current_status', []);
        $storeIds = $request->store_id;
        if (is_array($storeIds) && count($storeIds) == 0) return new Collection();
        if (!is_array($storeIds)) $storeIds = [$request->store_id];

        // Get Store(s)
        /** @var Store $store */
        $stores = [];
        foreach ($storeIds as $storeId) {
            $store = $this->getStoreByValue($storeId);
            if (!is_null($store)) $stores[] = $store;
        }
        if (count($stores) == 0) return new Collection();
        $stores = collect($stores);

        // Get authenticated User information
        $customer = $this->customer();

        // Get Order(s)
        $orders = Order::byStores($stores)
            ->byCustomer($customer)
            ->when($statuses, function ($query, $statuses) {
                return $query->whereCurrentStatuses($statuses);
            })
            ->get();

        $orders = array_map(function ($order) {
            $cartItems = $order['cart_items'];
            foreach ($cartItems as $itemIndex => $item) {
                $productVariantId = $item['product_variant_id'];
                $variant = ProductVariant::find($productVariantId);

                $order['cart_items'][$itemIndex]['variant'] = $variant;
            }
            return $order;
        }, $orders->toArray());

        // Return Order(s)
        return $orders;
    }

    public function getOrderDetailsAsCustomer(Request $request)
    {
        // Validate Request
        $validatableData = [
            'order_id' => $request->route('order_id')
        ];

        $validator = Validator::make($validatableData, [
            'order_id' => [
                'required',
                'exists:App\Models\Order,_id'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $validatedData = $validator->validated();

        // Get Order
        /** @var Order $order */
        $order = Order::find($validatedData['order_id']);

        // Validate Customer
        if (!$this->canCustomerViewOrder($order, $this->customer())) {
            return response()->json([
                'message' => 'Order does not belong to this Customer'
            ], 401);
        }

        $cartItems = $order['cart_items'];
        foreach ($cartItems as $itemIndex => $item) {
            $productVariantId = $item['product_variant_id'];
            $variant = ProductVariant::find($productVariantId);

            $order['cart_items'][$itemIndex]['variant'] = $variant;
        }

        // Get Checkout
        /** @var Checkout $order->checkout */
        $order->checkout = $order->checkout()->latest()->first();

        // Return data
        return response()->json($order);
    }
}
