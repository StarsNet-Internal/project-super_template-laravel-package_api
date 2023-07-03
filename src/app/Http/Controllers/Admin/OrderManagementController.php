<?php

namespace StarsNet\Project\App\Http\Controllers\Admin;

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
use StarsNet\Project\App\Models\DealGroupOrderCartItem;
use App\Traits\Controller\ReviewTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;

class OrderManagementController extends AdminOrderManagementController
{
    use ReviewTrait;

    public function getOrderDetails(Request $request)
    {
        $order = json_decode(json_encode(parent::getOrderDetails($request)), true)['original'];

        foreach ($order['cart_items'] as $index => $item) {
            $groupCartItem = DealGroupOrderCartItem::where('order_cart_item_id', $item['_id'])->first();

            $order['cart_items'][$index]['status'] = $groupCartItem->status;
            $order['cart_items'][$index]['deal'] = $groupCartItem->dealGroup()->first()->deal()->first();
        }

        return $order;
    }
}
