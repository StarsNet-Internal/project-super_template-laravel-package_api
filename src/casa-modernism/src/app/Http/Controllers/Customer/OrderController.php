<?php

namespace StarsNet\Project\CasaModernism\App\Http\Controllers\Customer;

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
use App\Models\Shipment;
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

    public function getAllWithTotalQuantity(Request $request)
    {
        $orders = parent::getAll($request);

        foreach ($orders as $order) {
            $order->total_quantity = $order->cart_items->sum('qty');
        }

        return $orders;
    }

    public function getAllOfflineOrdersWithTotalQuantity(Request $request)
    {
        $orders = parent::getAllOfflineOrders($request);

        foreach ($orders as $order) {
            $order->total_quantity = $order->cart_items->sum('qty');
        }

        return $orders;
    }

    public function getOrderAndShipmentDetails(Request $request)
    {
        $order = json_decode(json_encode(parent::getOrderDetailsAsCustomer($request)), true)['original'];

        $order['shipment'] = Shipment::where('order_id', $order['_id'])->first();

        // Return data
        return response()->json($order);
    }
}
