<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

// Models
use App\Models\Order;
use App\Models\Product;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;

// Constants
use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;

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

        // Get OrderShipmentDeliveryStatus
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

    public function uploadPaymentProofAsCustomer(Request $request)
    {
        // Validate RequestShipmentDeliveryStatus
        $orderID = $request->route('order_id');

        // Get Order
        /** @var Order $order */
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // Get Checkout
        /** @var Checkout $checkout */
        $checkout = $order->checkout()->latest()->first();

        if ($checkout->payment_method != CheckoutType::OFFLINE) {
            return response()->json([
                'message' => 'Order does not accept OFFLINE payment'
            ], 403);
        }

        // Update Checkout
        $checkout->updateOfflineImage($request->image);

        // // Update Order
        // if ($order->current_status !== ShipmentDeliveryStatus::PENDING) {
        //     $order->updateStatus(ShipmentDeliveryStatus::PENDING);
        // }

        // Return data
        return response()->json([
            'message' => 'Uploaded image successfully'
        ], 200);
    }

    public function getInvoiceData(Request $request)
    {
        $orderId = $request->route('id');
        $language = $request->route('language');

        $document = Order::find($orderId);
        if (!$document) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $storeId = $document->store_id;
        $customerId = $document->customer_id;

        $store = $document->store;
        $storeName = $store->title[$language];
        $invoicePrefix = $store->invoice_prefix ?? 'OA1';

        $customer = $document->customer;
        $account = $customer->account;

        // Get AuctionRegistrationRequest
        $auctionRegistrationRequest = AuctionRegistrationRequest::where('store_id', $storeId)
            ->where('requested_by_customer_id', $customerId)
            ->first();
        $paddleId = $auctionRegistrationRequest->paddle_id;

        // Construct Store Name
        $dateText = $this->formatDateRange($store->start_datetime, $store->display_end_datetime);
        $storeNameText = "{$storeName} {$dateText}";

        // Get buyerName
        $buyerName = $account->username;

        if (!empty($account->legal_name_verification->name)) {
            $buyerName = $account->legal_name_verification->name;
        } else {
            if (!$document->is_system) {
                $firstName = optional($document->delivery_details)->recipient_name->first_name;
                $lastName = optional($document->delivery_details)->recipient_name->last_name;
                if ($lastName) {
                    $buyerName = "{$lastName}, {$firstName}";
                }
            }
        }

        // Format data
        $issueDate = Carbon::parse($document->created_at)->addHours(8);
        $formattedIssueDate = $issueDate->format('d/m/Y');

        $itemsData = collect($document->cart_items)->map(function ($item) use ($language) {
            $hammerPrice = number_format($item->winning_bid, 2, '.', ',');
            $commission = number_format($item->commission ?? 0, 2, '.', ',');
            $otherFees = number_format(0, 2, '.', ',');
            $totalOrSumValue = $item->sold_price ?? $item->winning_bid;
            $totalOrSum = number_format($totalOrSumValue, 2, '.', ',');

            return [
                'lotNo' => $item->lot_number,
                'lotImage' => $item->image,
                'description' => $item->product_title[$language],
                'hammerPrice' => $hammerPrice,
                'commission' => $commission,
                'otherFees' => $otherFees,
                'totalOrSum' => $totalOrSum,
            ];
        });

        $total = (float) $document->calculations['price']['total'];
        $deposit = (float) $document->calculations['deposit'];
        $totalPrice = is_nan($total) || is_nan($deposit) ? NAN : number_format($total + $deposit, 2, '.', ',');
        $totalPriceText = ($document->payment_method == "ONLINE" && $invoicePrefix == 'OA1')
            ? "{$totalPrice} (includes credit card charge of 3.5%)"
            : $totalPrice;

        // Construct entire data
        $newCustomerId = substr($customerId, -6);
        $invoiceId = "{$invoicePrefix}-{$paddleId}";

        $data = [
            'lang' => $language,
            'buyerName' => $buyerName,
            'date' => $formattedIssueDate,
            'clientNo' => $newCustomerId,
            'paddleNo' => "#{$paddleId}",
            'auctionTitle' => $storeNameText,
            'shipTo' => "In-store pick up",
            'invoiceNum' => $invoiceId,
            'items' => $itemsData,
            'tableTotal' => $totalPriceText,
        ];

        // Return
        return $data;
    }

    private function formatDateRange($startDateTime, $endDateTime)
    {
        if (!$startDateTime || !$endDateTime) return "";

        $start = Carbon::parse($startDateTime)->utc();
        $end = Carbon::parse($endDateTime)->utc();

        if ($start->format('M') === $end->format('M') && $start->year === $end->year) {
            return $start->format('d') . '-' . $end->format('d') . ' ' . $start->format('M Y');
        } elseif ($start->year === $end->year) {
            return $start->format('d M') . ' - ' . $end->format('d M Y');
        } else {
            return $start->format('d M Y') . ' - ' . $end->format('d M Y');
        }
    }

    public function cancelOrderPayment(Request $request)
    {
        /** @var ?Order $order */
        $order = Order::find($request->route('order_id'));

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        if (is_null($order->scheduled_payment_at)) {
            return response()->json([
                'message' => 'Order does not have scheduled_payment_at'
            ], 400);
        }

        if (now()->gt($order->scheduled_payment_at)) {
            return response()->json([
                'message' => 'The scheduled payment time has already passed.'
            ], 403);
        }

        /** @var ?Checkout $checkout */
        $checkout = $order->checkout()->latest()->first();
        $paymentIntentID = $checkout->online['payment_intent_id'];

        $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/cancel';
        $response = Http::post($url);

        if ($response->status() === 200) {
            $order->updateStatus(ShipmentDeliveryStatus::CANCELLED);
            $checkout->updateApprovalStatus(CheckoutApprovalStatus::REJECTED);

            return response()->json([
                'message' => 'Update Order status as cancelled'
            ]);
        } else {
            return response()->json([
                'message' => 'Unable to cancel payment from Stripe, paymentIntent might have been closed'
            ]);
        }
    }
}
