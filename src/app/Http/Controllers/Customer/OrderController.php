<?php

namespace StarsNet\Project\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\StoreType;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Checkout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use StarsNet\Project\App\Models\DealGroupOrderCartItem;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Http\Controllers\Customer\OrderController as CustomerOrderController;

class OrderController extends CustomerOrderController
{
    use CheckoutTrait,
        StoreDependentTrait;

    public function getAll(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->input('store_id');

        // Get Store
        /** @var Store $store */
        $store = $this->getStoreByValue($storeID);

        if (is_null($store)) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        // Get authenticated User information
        $customer = $this->customer();

        // Get Order(s)
        /** @var Collection $orders */
        $orders = Order::byStore($store)
            ->byCustomer($customer)
            ->whereNotNull('parent_order_id')
            ->get()
            ->makeHidden(['cart_items', 'gift_items']);

        // Return data
        return $orders;
    }

    public function getOrderAndDealDetailsAsCustomer(Request $request)
    {
        $order = json_decode(json_encode(parent::getOrderDetailsAsCustomer($request)), true)['original'];

        $order['cart_items'] = array_map(function ($item) {
            $deal = DealGroupOrderCartItem::with([
                'dealGroup', 'dealGroup.deal'
            ])->where('order_cart_item_id', $item['_id'])
                ->first();
            if ($deal) {
                $item['product_title'] = $deal['dealGroup']['deal']['title'];
            }
            $item['discounted_price_per_unit'] = $item['deal_price_per_unit'];
            $item['subtotal_price'] = $item['deal_subtotal_price'];
            return $item;
        },  $order['cart_items']);

        return $order;
    }
}
