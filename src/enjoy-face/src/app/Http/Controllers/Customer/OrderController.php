<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\StoreType;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Http\Controllers\Customer\OrderController as CustomerOrderController;

class OrderController extends CustomerOrderController
{
    use CheckoutTrait,
        StoreDependentTrait;

    public function getOrderDetails(Request $request)
    {
        $response = parent::getOrderDetailsAsCustomer($request);
        $order = json_decode(json_encode($response), true)['original'];

        $order['cart_items'] = array_map(function ($item) use ($order) {
            $variant = ProductVariant::find($item['product_variant_id']);
            $item['qty'] = $variant->weight;
            $item['discounted_price_per_unit'] = strval($variant->cost);
            $item['product_variant_title'] = $this->store->title;
            $item['created_at'] = Carbon::parse($order['created_at'])->addDays($variant->weight);

            // if (count($this->store->images)) {
            //     $item['image'] = $this->store->images[0];
            // }
            return $item;
        }, $order['cart_items']);

        // Return data
        return response()->json($order, $response->getStatusCode());
    }
}
