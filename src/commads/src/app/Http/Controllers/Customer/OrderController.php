<?php

namespace StarsNet\Project\Commads\App\Http\Controllers\Customer;

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
use StarsNet\Project\Commads\App\Traits\Controller\OrderTrait;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use StarsNet\Project\Commads\App\Models\CustomOrderImage;
use StarsNet\Project\Commads\App\Models\CustomStoreQuote;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;

class OrderController extends CustomerOrderController
{
    use CheckoutTrait,
        StoreDependentTrait,
        OrderTrait;

    protected $model = Order::class;

    public function getAllWithQuoteDetails(Request $request)
    {
        $orders = $this->getAll($request);

        foreach ($orders as $key => $order) {
            $orders[$key] = array_merge($order->toArray(), $this->getQuoteDetails($order));
        }

        return $orders;
    }

    public function getOrderAndQuoteDetailsAsCustomer(Request $request)
    {
        $response = $this->getOrderDetailsAsCustomer($request);
        $order = json_decode($response->getContent(), true);

        $order = array_merge($order, $this->getQuoteDetails($order));

        return response()->json($order, $response->getStatusCode());
    }

    public function uploadCustomOrderImage(Request $request)
    {
        // Extract attributes from $request
        $orderID = $request->route('order_id');
        $imageUrl = $request->images;

        if (is_null($imageUrl)) {
            return response()->json([
                'message' => 'Invalid image url'
            ], 403);
        }

        // Get Order, then validate
        /** @var Order $order */
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->store()->first()->slug != 'custom-main-store') {
            return response()->json([
                'message' => 'Order not customizable'
            ], 400);
        }

        // Get authenticated User information, then validate
        $customer = $this->customer();

        if ($order->customer_id !== $customer->_id) {
            return response()->json([
                'message' => 'Order does not belong to this Customer'
            ], 401);
        }

        $image = CustomOrderImage::create([
            'images' => $imageUrl
        ]);
        $image->associateOrder($order);

        // Return data
        return response()->json([
            'message' => 'Uploaded Image successfully'
        ], 200);
    }
}
