<?php

namespace StarsNet\Project\SnoreCircle\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Traits\Controller\ReviewTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\SnoreCircle\App\Traits\Controller\ProjectOrderTrait;
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
        $orders = parent::getAllOrdersByStore($request)->toArray();

        $bookings = $this->getAllOrders();
        foreach ($orders as $key => $order) {
            $orders[$key]['cashier_id'] = $this->getReceiptNumber($order, $bookings);
        }

        // Return Order
        return $orders;
    }

    public function getOrderDetails(Request $request)
    {
        $response = parent::getOrderDetails($request);
        $order = json_decode(json_encode($response), true)['original'];

        $orders = $this->getAllOrders();
        $order['cashier_id'] = $this->getReceiptNumber($order, $orders);

        // Return Order
        return response()->json($order, $response->getStatusCode());
    }
}
