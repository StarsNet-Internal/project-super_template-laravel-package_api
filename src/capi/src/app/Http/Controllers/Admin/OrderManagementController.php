<?php

namespace StarsNet\Project\Capi\App\Http\Controllers\Admin;

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
use StarsNet\Project\Capi\App\Models\AccountProduct;
use StarsNet\Project\Capi\App\Models\DealGroupOrderCartItem;
use App\Traits\Controller\ReviewTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\Capi\App\Traits\Controller\ProjectAccountTrait;

use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;

class OrderManagementController extends AdminOrderManagementController
{
    use ReviewTrait, StoreDependentTrait, ProjectAccountTrait;

    public function getAllOrdersByStore(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'current_status' => [
                'nullable',
                'string'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $statuses = (array) $request->input('current_status', []);
        $storeIds = $request->store_id;
        if (is_array($storeIds) && count($storeIds) == 0) return new Collection();
        if (!is_array($storeIds)) $storeIds = [$request->store_id];

        // Get Store(s)
        /** @var Store $store */
        $stores = [];
        foreach ($storeIds as $storeId) {
            $store = $this->getStoreByValue($storeId);
            if (!is_null($store)) $stores[] = $store;
        }
        if (count($stores) == 0) return new Collection();
        $stores = collect($stores);

        // Get Order(s)
        $orders = Order::byStores($stores)
            ->when($statuses, function ($query, $statuses) {
                return $query->whereCurrentStatuses($statuses);
            })
            ->whereNotNull('parent_order_id')
            ->get();

        $account = $this->account();
        if ((bool) $this->checkIfAccountIsSuperAdminOrAdmin($account)) {
            return $orders;
        }
        $access = AccountProduct::where('account_id', $account->_id)->get();
        if ($access) {
            $ids = $access->pluck('product_id')->all();

            return array_filter($orders->toArray(), function ($order) use ($ids) {
                return in_array($order['cart_items'][0]['product_id'], $ids);
            });
        }
        return new Collection();

        // Return Order(s)
        return $orders;
    }

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
