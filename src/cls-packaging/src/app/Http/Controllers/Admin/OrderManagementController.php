<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;
use StarsNet\Project\App\Models\CustomerGroupOrder;

class OrderManagementController extends AdminOrderManagementController
{
    public function getAllCustomerGroupOrdersByStore(Request $request)
    {
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $orderType = $request->order_type;

        if (count($customerGroupIDs) == 0) {
            $orderIDs = CustomerGroupOrder::where('type', $orderType)
                ->pluck('order_id')
                ->toArray();
        } else {
            $orderIDs = CustomerGroupOrder::whereIn('customer_group_ids', $customerGroupIDs)
                ->where('type', $orderType)
                ->pluck('order_id')
                ->toArray();
        }

        $orders = Order::find($orderIDs);

        return $orders;
    }

    public function getOrderType(Request $request)
    {
        $order = CustomerGroupOrder::where('order_id', $request->route('id'))->first();

        return $order;
    }
}
