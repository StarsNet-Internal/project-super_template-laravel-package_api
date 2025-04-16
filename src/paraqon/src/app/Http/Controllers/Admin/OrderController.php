<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
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

    public function approveOrderOfflinePayment(Request $request)
    {
        // Validate Request
        $validator = Validator::make([
            'id' => $request->route('id')
        ], [
            'id' => [
                'required',
                'exists:App\Models\Order,_id'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Get Order
        /** @var Order $order */
        $order = Order::find($request->route('id'));

        // Get latest Checkout, then validate
        /** @var Checkout $checkout */
        $checkout = $order->checkout()->latest()->first();

        if (is_null($checkout)) {
            return response()->json([
                'message' => 'Checkout not found'
            ], 404);
        }

        if ($checkout->hasApprovedOrRejected()) {
            return response()->json([
                'message' => 'Checkout has been approved/rejected'
            ], 400);
        }

        // Validate Request
        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                Rule::in([
                    CheckoutApprovalStatus::APPROVED,
                    CheckoutApprovalStatus::REJECTED,
                    CheckoutApprovalStatus::CANCELLED
                ])
            ],
            'reason' => [
                'nullable',
                'string'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $status = $request->status;
        $reason = $request->reason;

        // Get authenticated User info
        $user = $this->user();

        // Create CheckoutApproval
        $checkout->createApproval($status, $reason, $user);

        if ($status === CheckoutApprovalStatus::APPROVED) {
            $productIDs = collect($order->cart_items)->pluck('product_id')->all();

            AuctionLot::where('store_id', $order->store_id)
                ->whereIn('product_id', $productIDs)
                ->update(['is_paid' => true]);

            Product::objectIDs($productIDs)->update([
                'owned_by_customer_id' => $order->customer_id,
                'status' => 'ACTIVE',
                'listing_status' => 'ALREADY_CHECKOUT'
            ]);
        }

        // Return success message
        return response()->json([
            'message' => 'Reviewed Order successfully'
        ], 200);
    }
}
