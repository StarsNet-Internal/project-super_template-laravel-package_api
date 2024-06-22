<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Traits\Controller\ReviewTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectOrderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;

class OrderManagementController extends AdminOrderManagementController
{
    use ReviewTrait, StoreDependentTrait, ProjectOrderTrait;

    public function getAllOrdersByStore(Request $request)
    {
        $response = parent::getAllOrdersByStore($request);
        $orders = json_decode(json_encode($response), true)['original'];

        $bookings = $this->getOfflineOrders();
        foreach ($orders as $order) {
            $order['cashier_id'] = $this->getReceiptNumber($order, $bookings);
        }

        // Return Order
        return response()->json($order, $response->getStatusCode());
    }

    public function getOrderDetails(Request $request)
    {
        $response = parent::getOrderDetails($request);
        $order = json_decode(json_encode($response), true)['original'];

        $bookings = $this->getOfflineOrders();
        $order['cashier_id'] = $this->getReceiptNumber($order, $bookings);

        // Return Order
        return response()->json($order, $response->getStatusCode());
    }

    public function updateDeliveryAddress(Request $request)
    {
        // Validate Request
        $validator = Validator::make([
            'id' => $request->route('id')
        ], [
            'id' => [
                'required',
                'exists:App\Models\Order,_id'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Get Order
        /** @var Order $order */
        $order = Order::find($request->route('id'));

        // Extract attributes from $request
        $address = $request->address;

        // Update Order
        $order->updateNestedAttributes([
            'delivery_details.address' => $address,
        ]);

        // Return success message
        return response()->json([
            'message' => 'Updated booking time successfully',
        ], 200);
    }
}
