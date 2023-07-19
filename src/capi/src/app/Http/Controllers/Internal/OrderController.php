<?php

namespace StarsNet\Project\Capi\App\Http\Controllers\Internal;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Events\Common\Order\OrderCreated;
use App\Events\Common\Order\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\MembershipPoint;
use App\Models\OrderCartItem;
use App\Models\ProductVariant;
use App\Models\Store;
use StarsNet\Project\Capi\App\Constants\Model\OrderCartItemStatus;
use StarsNet\Project\Capi\App\Models\Deal;
use StarsNet\Project\Capi\App\Models\DealGroup;
use StarsNet\Project\Capi\App\Models\DealGroupOrderCartItem;
use StarsNet\Project\Capi\App\Models\RefundCredit;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use StarsNet\Project\Capi\App\Traits\Controller\ProjectShoppingCartTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Carbon\Carbon;

class OrderController extends Controller
{
    use ShoppingCartTrait,
        CheckoutTrait,
        WarehouseInventoryTrait,
        ProjectShoppingCartTrait;

    public function updateOrderCartItemStatus(Request $request)
    {
        $currentMinute = Carbon::now()->startOfMinute();
        $startOfMinute = $currentMinute->copy();
        $endOfMinute = $currentMinute->copy()->addSeconds(59);

        $expiringDealIDs = Deal::statusActive()
            ->whereBetween('end_datetime', [$startOfMinute, $endOfMinute])
            ->pluck('_id')
            ->all();
        $expiringDealGroups = DealGroup::whereIn('deal_id', $expiringDealIDs)
            ->get();

        foreach ($expiringDealGroups as $group) {
            $orders = $group->orders()->get();
            $itemIDs = $group['order_cart_item_ids'];
            $isSuccess = $group->isDealGroupSuccessful();

            foreach ($orders as $index => $order) {
                $groupItem = DealGroupOrderCartItem::where('order_cart_item_id', $itemIDs[$index])->first();
                if ($isSuccess) {
                    $groupItem->updateStatus(OrderCartItemStatus::SUCCESSFUL);
                } else {
                    $groupItem->updateStatus(OrderCartItemStatus::FAILED);
                }
            }
        }

        return response()->json([
            'message' => 'Updated Cart Item Status'
        ], 200);
    }

    // TODO Modify this to run once per day for server and maintenance issues
    public function refundCartItem(Request $request)
    {
        $itemID = $request->route('id');

        $groupCartItem = DealGroupOrderCartItem::find($itemID);

        if ($groupCartItem->status == OrderCartItemStatus::PENDING) {
            return response()->json([
                'message' => 'Cart Item has not been processed'
            ], 400);
        }

        $group = $groupCartItem->dealGroup()->first();
        $order = $groupCartItem->order()->first();

        $price = $this->getDiscountedPrice($group);

        $cartItem = array_column($order['cart_items']->toArray(), null, '_id')[$groupCartItem['order_cart_item_id']];
        $checkoutSubtotal = $cartItem['deal_subtotal_price'];

        if ($group->isDealGroupSuccessful()) {
            $subtotal = $this->roundingValue($price * $cartItem['qty']);
            $pointsToAdd = $this->roundingValue($checkoutSubtotal - $subtotal) * 100;
        } else {
            $pointsToAdd = $checkoutSubtotal * 100;
        }

        $customerID = $order['customer_id'];
        $customer = Customer::find($customerID);

        $remarks = "Refund Record for Order ID: {$order['_id']}";

        $point = RefundCredit::createByCustomer(
            $customer,
            $pointsToAdd,
            'REFUND',
            now()->endOfCentury(),
            [
                'en' => $remarks,
                'zh' => $remarks,
                'cn' => $remarks
            ],
            $remarks
        );

        return response()->json([
            'message' => 'Refunded Cart Item successfully',
            '_id' => $point->_id
        ], 200);
    }
}
