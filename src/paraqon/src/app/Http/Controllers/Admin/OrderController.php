<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function getAllAuctionOrders(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->store_id;
        $customerID = $request->customer_id;
        $isSystem = $request->boolean('is_system', true);

        // Get Order
        $orders = Order::where('is_system', $isSystem)
            ->when($storeID, function ($query, $storeID) {
                return $query->where('store_id', $storeID);
            })
            ->when($customerID, function ($query, $customerID) {
                return $query->where('customer_id', $customerID);
            })
            ->get();

        foreach ($orders as $order) {
            $order->checkout = $order->checkout()->latest()->first();
        }

        return $orders;
    }

    public function updateOrderDetails(Request $request)
    {
        // Extract attributes from $request
        $orderID = $request->route('order_id');

        // Get Order
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // Update Order
        $order->update($request->all());

        return response()->json([
            'message' => "Updated Order Successfully"
        ], 200);
    }
}
