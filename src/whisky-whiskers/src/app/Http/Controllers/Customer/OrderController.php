<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Traits\Controller\CheckoutTrait;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class OrderController extends Controller
{
    use CheckoutTrait;

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
}
