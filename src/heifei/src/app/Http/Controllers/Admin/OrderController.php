<?php

namespace StarsNet\Project\HeiFei\App\Http\Controllers\Admin;

use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;


class OrderController extends Controller
{
    use ShoppingCartTrait;

    public function createOrder(Request $request)
    {
        // Extract attributes from $request
        $storeId = $request->store_id;
        $productVariantID = $request->product_variant_id;
        $qty = $request->input('qty', 1);
        $newTotalPrice = $request->input('amount_received', 0);

        // Validate
        $account = $this->account();
        $customer = $this->customer();

        // Get Model(s)
        $store = Store::find($storeId);
        $variant = ProductVariant::find($productVariantID);

        // Add to cart
        $customer->addToCartByStore($variant, $store, $qty);

        // Get Checkout calculation
        $cartItems = $customer->getAllCartItemsByStore($store);
        $rawCalculation = $this->getRawCalculationByCartItems($cartItems, collect());

        $originalTotal = $rawCalculation['price']['total'];
        $priceDifference = floatval($newTotalPrice) - $originalTotal;

        // Create Order
        $checkoutDetails = $this->getShoppingCartDetailsByCustomerAndStore(
            $customer,
            $store,
            [$productVariantID]
        );

        $orderAttributes = [
            'payment_method' => OrderPaymentMethod::ONLINE,
            'discounts' => $checkoutDetails['discounts'],
            'calculations' => $checkoutDetails['calculations'],
            'amount_received' => $newTotalPrice,
            'change' => $priceDifference
        ];
        $order = $customer->createOrder($orderAttributes, $store);
        $order->update(['created_by_account_id' => $account->_id]);

        // Return success message
        return response()->json([
            'message' => 'Created New Order successfully',
            '_id' => $order->_id
        ], 200);
    }

    public function getAllOrders(Request $request)
    {
        // Extract attributes from $request
        $accountId = $request->account_id;
        $startDateTime = $request->input('start_datetime');
        if (!is_null($startDateTime)) $startDateTime = Carbon::parse($startDateTime)->startOfDay();
        $endDateTime = $request->input('end_datetime');
        if (!is_null($endDateTime)) $endDateTime = Carbon::parse($endDateTime)->endOfDay();

        // Get Order(s)
        $orders = Order::when($accountId, function ($query, $accountId) {
            $query->where('created_by_account_id', $accountId);
        })->when($startDateTime, function ($query, $startDateTime) {
            $query->where([['created_at', '>=', $startDateTime]]);
        })->when($endDateTime, function ($query, $endDateTime) {
            $query->where([['created_at', '<=', $endDateTime]]);
        })->get();

        // Return Order(s)
        return $orders;
    }
}
