<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Listeners\Common\Payment;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Models\Alias;
use StarsNet\Project\WhiskyWhiskers\App\Events\Common\Payment\PaidFromPinkiePay;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;

use App\Traits\StarsNet\InvoiceReceiptGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use StarsNet\Project\WhiskyWhiskers\App\Models\ProductStorageRecord;

class UpdateOrderCheckoutIsPaid
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PaidFromPinkiePay  $event
     * @return void
     */
    public function handle(PaidFromPinkiePay $event)
    {
        // Extract attributes from $event
        $request = $event->request;

        // Extract attributes from $request
        $accessKey = $request->access_key;
        $transactionID = $request->transaction_id;
        $isPaid = $request->boolean('paid');
        $paymentMethod = $request->payment_method;

        // Get Checkout
        $checkout = $this->getCheckoutByTransactionID($transactionID);
        // if (is_null($checkout)) return;

        // Save response
        $allResponse = (object) $request->all();
        $checkout->updateOnlineResponse($allResponse);

        // Update Checkout and Order
        $status = $isPaid ? CheckoutApprovalStatus::APPROVED : CheckoutApprovalStatus::REJECTED;
        $reason = $isPaid ? 'Payment verified by System' : 'Payment failed';
        $checkout->createApproval($status, $reason);

        // Get Order and Customer
        /** @var Order $order */
        $order = $checkout->order;
        /** @var Customer $customer */
        $customer = $order->customer;

        // Update Order status
        if (
            $isPaid && $order->current_status !== ShipmentDeliveryStatus::PROCESSING
        ) {
            $order->setTransactionMethod($paymentMethod);
            $order->updateStatus(ShipmentDeliveryStatus::PROCESSING);
        }

        if (
            !$isPaid && $order->current_status !== ShipmentDeliveryStatus::CANCELLED
        ) {
            $order->updateStatus(ShipmentDeliveryStatus::CANCELLED);
            return;
        }

        // Delete ShoppingCartItem(s)
        /** @var Store $store */
        $store = $order->store;
        $variantIDs = collect($order->cart_items)->pluck('product_variant_id')->all();
        $variants = ProductVariant::objectIDs($variantIDs)->get();
        $customer->clearCartByStore($store, $variants);

        // Get Product(s)
        $cartItems = $order->cart_items;

        $productIDs = [];
        foreach ($cartItems as $item) {
            $productIDs[] = $item['product_id'];
        }

        // Get Store
        $defaultMainStore = Alias::where('key', 'default-main-store')->latest()->first();

        if ($store->_id == optional($defaultMainStore)->value) {
            // Main Store logic
            // Log::info(['message' => "This is main store order"]);

            if (count($productIDs) > 0) {
                Product::objectIDs($productIDs)->update(
                    ['listing_status' => 'ALREADY_CHECKOUT']
                );
            }
        } else {
            // Auction Store logic
            if (count($productIDs) > 0) {
                // Update Product ownership and listing_status
                $listingStatus = $order->is_storage ? 'AVAILABLE' : 'ALREADY_CHECKOUT';
                Product::objectIDs($productIDs)->update(
                    [
                        'owned_by_customer_id' => $order->customer_id,
                        'listing_status' => $listingStatus
                    ]
                );

                // Update AuctionLot paid status
                AuctionLot::whereIn('product_id', $productIDs)->update([
                    'winning_bid_customer_id' => $order->customer_id,
                    'is_paid' => true
                ]);

                foreach ($productIDs as $productID) {
                    $winningAuctionLot = AuctionLot::where('product_id', $productID)
                        ->where('store_id', $store->_id)
                        ->first();
                    if (!is_null($winningAuctionLot)) {
                        $winningBid = $winningAuctionLot->current_bid;
                        ProductStorageRecord::create([
                            // Relationships
                            'customer_id' => $order->customer_id,
                            'product_id' => $productID,

                            // Default
                            'start_datetime' => now(),
                            'winning_bid' => $winningBid
                        ]);
                    }
                }
            }

            // Attach relationship with previous system-generated order
            $previousGeneratedOrder = Order::where('customer_id', $order->customer_id)
                ->where('store_id', $order->store_id)
                ->where('_id', '!=', $order->_id)
                ->orderBy('created_at', 'asc')
                ->first();

            if (!is_null($previousGeneratedOrder)) {
                $previousGeneratedOrder->update([
                    'paid_order_id' => $order->_id
                ]);
            }
        }

        return;
    }

    // ! Deprecated, but can be improved after integrating it with database set-up
    // private function isPayerKeyMatched(string $accessKey): bool
    // {
    //     return $accessKey === env('PINKIEPAY_ACCESS_KEYS_PAYER');
    // }

    private function getCheckoutByTransactionID(string $id): ?Checkout
    {
        return Checkout::whereTransactionID($id)->first();
    }
}
