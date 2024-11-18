<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Traits\Controller\CheckoutTrait;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;

class OrderController extends Controller
{
    use CheckoutTrait;

    public function getOrdersByStoreID(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->route('store_id');

        // Get Customer
        $customer = $this->customer();

        // Get Orders
        $orders = Order::where('store_id', $storeID)
            ->where('customer_id', $customer->_id)
            ->get();

        foreach ($orders as $order) {
            $order->checkout = $order->checkout()->latest()->first();
        }

        return $orders;
    }

    public function uploadPaymentProofAsCustomer(Request $request)
    {
        // Validate Request
        $orderID = $request->route('order_id');

        // Get Order
        /** @var Order $order */
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        $customer = $this->customer();

        if ($order->customer_id != $customer->_id) {
            return response()->json([
                'message' => 'Order does not belong to this Customer'
            ], 401);
        }

        // Get Checkout
        /** @var Checkout $checkout */
        $checkout = $order->checkout()->latest()->first();

        if ($checkout->payment_method != CheckoutType::OFFLINE) {
            return response()->json([
                'message' => 'Order does not accept OFFLINE payment'
            ], 403);
        }

        // Update Checkout
        $order = $checkout->order;
        $checkout->updateOfflineImage($request->image);

        // Update Order
        if ($order->current_status !== ShipmentDeliveryStatus::PENDING) {
            $order->updateStatus(ShipmentDeliveryStatus::PENDING);
        }

        // Return data
        return response()->json([
            'message' => 'Uploaded image successfully'
        ], 200);
    }

    public function payPendingOrderByOnlineMethod(Request $request)
    {
        // Extract attributes from $request
        $orderId = $request->route('order_id');
        $successUrl = $request->success_url;
        $cancelUrl = $request->cancel_url;

        // Validate
        $customer = $this->customer();

        $order = Order::find($orderId);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->customer_id != $customer->_id) {
            return response()->json([
                'message' => 'This Order does not belong to this user'
            ], 404);
        }

        $checkout = $order->checkout()->latest()->first();

        // Generate payment url
        $returnUrl = $this->updateAsOnlineCheckout($checkout, $successUrl, $cancelUrl);

        // Return data
        $data = [
            'message' => 'Generated new payment url successfully',
            'return_url' => $returnUrl ?? null,
            'order_id' => $order->_id
        ];

        return response()->json($data);
    }

    public function getAllOfflineOrders(Request $request)
    {
        // Get Store(s)
        /** @var Store $store */
        $stores = Store::whereType(StoreType::OFFLINE)
            ->get();

        // Get authenticated User information
        $customer = $this->customer();

        // Get Order(s)
        /** @var Collection $orders */
        $orders = Order::byStores($stores)
            ->byCustomer($customer)
            ->get();

        foreach ($orders as $order) {
            $order->checkout = $order->checkout()->latest()->first();
        }

        // Return data
        return $orders;
    }
}
