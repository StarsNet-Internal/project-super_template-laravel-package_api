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
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Http\Controllers\Customer\OrderController as CustomerOrderController;

class OrderController extends CustomerOrderController
{
    use CheckoutTrait;

    public function getDetails(Request $request)
    {
        $order = json_decode(json_encode(parent::getDetails($request)), true)['original'];

        foreach ($order['cart_items'] as $index => $item) {
            $groupCartItem = DealGroupOrderCartItem::where('order_cart_item_id', $item['_id'])->first();

            $order['cart_items'][$index]['status'] = $groupCartItem->status;
            $order['cart_items'][$index]['deal'] = $groupCartItem->dealGroup()->first()->deal()->first();
        }

        return $order;
    }
}
