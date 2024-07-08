<?php

namespace StarsNet\Project\Stripe\App\Listeners\Common\Payment;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Models\Alias;
use StarsNet\Project\Stripe\App\Events\Common\Payment\PaidFromStripe;

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
use StarsNet\Project\Stripe\App\Models\AuctionLot;
use StarsNet\Project\Stripe\App\Models\Bid;

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
     * @param  PaidFromStripe  $event
     * @return void
     */
    public function handle(PaidFromStripe $event)
    {
        // Extract attributes from $event
        $request = $event->request;

        // Extract attributes from $request
        $transactionID = $request->data['object']['id'];
        $isPaid = $request->type == 'payment_intent.succeeded';
        $paymentMethod = 'CREDIT CARD';

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

        // Fire Event(s)
        // Deduct MembershipPoint(s)
        $requiredPoints = $order->getTotalPoint();
        if ($requiredPoints > 0) {
            $history = $customer->deductMembershipPoints($requiredPoints);
            $history->update(['remarks' => 'Redemption Record for Order ID: ' . $order->_id]);
        }

        // Delete ShoppingCartItem(s)
        /** @var Store $store */
        $store = $order->store;
        $variantIDs = collect($order->cart_items)->pluck('product_variant_id')->all();
        $variants = ProductVariant::objectIDs($variantIDs)->get();
        $customer->clearCartByStore($store, $variants);

        // Distribute MembershipPoint(s)
        event(new OrderPaid($order, $customer));

        return;
    }

    private function getCheckoutByTransactionID(string $id): ?Checkout
    {
        return Checkout::whereTransactionID($id)->first();
    }
}
