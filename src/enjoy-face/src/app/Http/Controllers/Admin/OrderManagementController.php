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
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectPostTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;
use Carbon\Carbon;

class OrderManagementController extends AdminOrderManagementController
{
    use ReviewTrait, StoreDependentTrait, ProjectOrderTrait, ProjectPostTrait;

    public function getAllOrdersByStore(Request $request)
    {
        $orders = parent::getAllOrdersByStore($request)->toArray();

        $bookings = $this->getOfflineOrders();
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

    public function cancelOrder(Request $request)
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

        if ($order->current_status === 'cancelled') {
            return response()->json([
                'message' => 'Order has already been cancelled'
            ], 400);
        }

        // Get Store
        /** @var Store $store */
        $store = $order->store;

        if ($store->type !== 'OFFLINE') {
            return response()->json([
                'message' => 'This order is not a booking'
            ], 400);
        }

        // Update Order
        $order->updateStatus(ShipmentDeliveryStatus::CANCELLED);

        // Add store quota
        $quota = $store->quota;
        $store->update(['quota' => $quota + 1]);

        // Send voucher to customer
        $customer = $order->customer;
        $personalGroup = $customer->groups()->where('slug', 'personal-customer-group')->first();
        $storeIds = Store::where('type', 'OFFLINE')->statusActive()->pluck('_id')->all();
        $totalPrice = floor($order->getTotalPrice());

        $voucher = DiscountTemplate::create([
            'store_ids' => $storeIds,
            'customer_group_id' => $personalGroup->_id,
            'title' => [
                'en' => 'Booking Cancellation',
                'zh' => '取消預約',
                'cn' => '取消预约',
            ],
            'description' => [
                'en' => null,
                'zh' => null,
                'cn' => null,
            ],
            'images' => ['https://starsnet-production.oss-cn-hongkong.aliyuncs.com/png/4fb86ec5-2b42-4824-8c05-0daa07644edf.png'],
            'status' => 'ACTIVE',
            'prefix' => strtoupper(Str::random(5)),
            'point' => 0,
            'template_type' => 'VOUCHER',
            'discount_type' => 'PRICE',
            'discount_value' => $totalPrice,
            'min_requirement' => [
                'spending' => 1,
                'product_qty' => 1
            ],
            'valid_duration' => 365,
            'quota' => 1,
            'quota_per_customer' => 1,
            'commission' => [
                'rate' => 0,
                'value' => 0
            ],
            'is_auto_apply' => false,
            'start_datetime' => Carbon::now(),
            'end_datetime' => Carbon::now()->endOfCentury(),
        ]);
        $suffix = strtoupper(Str::random(6));
        $discountCode = $voucher->createVoucher($suffix, $customer, false);

        // Inbox
        $inboxAttributes = [
            'title' => [
                'en' => 'You have received a coupon from booking cancellation',
                'zh' => '您已收到取消預約的優惠券',
                'cn' => '您已收到取消预约的优惠券',
            ],
            'short_description' => [
                'en' => 'Enter the code "' . $discountCode->full_code . '" to enjoy the offer.',
                'zh' => '輸入"' . $discountCode->full_code . '"即可享優惠。',
                'cn' => '输入"' . $discountCode->full_code . '"即可享优惠。',
            ],
            'long_description' => [
                'en' => $discountCode->full_code,
                'zh' => $discountCode->full_code,
                'cn' => $discountCode->full_code,
            ],
        ];
        $this->createInboxPost($inboxAttributes, [$customer->account_id], true);

        // Return success message
        return response()->json([
            'message' => 'Cancelled booking successfully',
        ], 200);
    }
}
