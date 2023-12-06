<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OrderManagementController extends Controller
{
    use ReviewTrait, StoreDependentTrait;

    protected $model = Order::class;

    public function getAllOrdersByStore(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('current_status', []);

        $orders = Order::whereIn('store_id', $request->store_id)
            ->when($statuses, function ($query, $statuses) {
                return $query->whereCurrentStatuses($statuses);
            })
            ->with(['productReviews'])
            ->get();

        return $orders;
    }
}
