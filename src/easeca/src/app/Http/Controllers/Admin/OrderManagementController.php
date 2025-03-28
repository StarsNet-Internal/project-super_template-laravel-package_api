<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

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
            ->get()
            ->makeHidden(
                [
                    'cart_items',
                    'gift_items',
                ]
            );
        $reviews = ProductReview::all()
            ->unique('order_id')
            ->makeHidden(
                [
                    'user',
                    'product_title',
                    'product_variant_title',
                    'image',
                ]
            );
        $accounts = Account::all();

        foreach ($orders as $order) {
            $filtered = $reviews->filter(function ($review) use ($order) {
                return $review->order_id == $order->_id;
            });
            $orderReviews = $filtered->values()->toArray();

            foreach ($orderReviews as $key => $orderReview) {
                $user = [
                    'username' => $accounts->first(function ($account) use ($orderReview) {
                        return $account->user_id == $orderReview['user_id'];
                    })->username,
                    'avatar' => null,
                ];
                $orderReviews[$key]['user'] = $user;
            }
            $order->product_reviews = $orderReviews;
        }
        return $orders;
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
            'message' => 'Updated delivery address successfully',
        ], 200);
    }
}
