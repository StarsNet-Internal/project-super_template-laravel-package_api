<?php

namespace StarsNet\Project\SnoreCircle\App\Http\Controllers\Customer;

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
use StarsNet\Project\SnoreCircle\App\Traits\Controller\ProjectOrderTrait;
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
        StoreDependentTrait,
        ProjectOrderTrait;

    public function getAll(Request $request)
    {
        $orders = parent::getAll($request)->toArray();
        $allOrders = $this->getAllOrders();

        foreach ($orders as $key => $order) {
            $orders[$key]['cashier_id'] = $this->getReceiptNumber($order, $allOrders);
        }

        // Return data
        return $orders;
    }

    public function getOrderDetails(Request $request)
    {
        $response = parent::getOrderDetailsAsCustomer($request);
        $order = json_decode(json_encode($response), true)['original'];

        $orders = $this->getAllOrders();
        $order['cashier_id'] = $this->getReceiptNumber($order, $orders);

        // Return data
        return response()->json($order, $response->getStatusCode());
    }
}
