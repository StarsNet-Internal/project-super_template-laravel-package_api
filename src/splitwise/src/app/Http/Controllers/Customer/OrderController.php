<?php

namespace StarsNet\Project\Splitwise\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\StoreType;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\Splitwise\App\Traits\Controller\ProjectCheckoutTrait;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;

class OrderController extends CustomerOrderController
{
    use CheckoutTrait,
        StoreDependentTrait,
        ProjectCheckoutTrait;

    protected $model = Order::class;

    public function getAll(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->input('store_id');

        // Get Store
        /** @var Store $store */
        $store = $this->getStoreByValue($storeID);

        $customer = $this->customer();

        $orders = Order::with([
            'store',
            'checkout'
        ])->get();

        $orders = array_map(function ($order) {
            $order['image'] = count($order['checkout']) > 0 ? $order['checkout'][0]['offline']['image'] ?? null : null;
            unset($order['cart_items'], $order['gift_items'], $order['checkout']);
            return $order;
        }, $orders->toArray());

        $orders = array_filter($orders, function ($order) use ($store, $customer) {
            return $order['store_id'] == $store->_id && $order['customer_id'] == $customer->_id;
        });

        return $orders;
    }

    private function canCustomerViewOrder(Order $order, Customer $customer)
    {
        return $order->customer_id === $customer->_id;
    }

    public function uploadPaymentProofAsCustomer(Request $request)
    {
        // Validate Request
        $validatableData = array_merge($request->all(), [
            'order_id' => $request->route('order_id')
        ]);

        $validator = Validator::make($validatableData, [
            'order_id' => [
                'required',
                'exists:App\Models\Order,_id'
            ],
            'image' => [
                'required',
                'url'
            ]
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

        // Get Checkout
        /** @var Checkout $checkout */
        $checkout = $order->checkout()->latest()->first();

        if (!$checkout->isMatchingPaymentMethod(CheckoutType::OFFLINE)) {
            return response()->json([
                'message' => 'Order does not accept OFFLINE payment'
            ], 403);
        }

        // Update Checkout
        $this->updateAsOfflineCheckoutWithoutBankInSlipApprover($checkout, $validatedData['image']);

        // Update Order
        if ($order->current_status !== ShipmentDeliveryStatus::PENDING) {
            $order->updateStatus(ShipmentDeliveryStatus::PENDING);
        }

        // Return data
        return response()->json([
            'message' => 'Uploaded image successfully'
        ], 200);
    }
}
