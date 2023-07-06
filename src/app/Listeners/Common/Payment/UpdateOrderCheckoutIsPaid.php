<?php

namespace StarsNet\Project\App\Listeners\Common\Payment;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Events\Common\Payment\PaidFromPinkiePay;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use StarsNet\Project\App\Models\DealGroupShoppingCartItem;
use StarsNet\Project\App\Models\DealGroupOrderCartItem;
use StarsNet\Project\App\Traits\Controller\ProjectShoppingCartTrait;

use App\Traits\StarsNet\InvoiceReceiptGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

class UpdateOrderCheckoutIsPaid
{
    use ProjectShoppingCartTrait;

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

        // Validate
        // if (!$this->isPayerKeyMatched($accessKey)) return;

        $checkout = Checkout::whereTransactionID($transactionID)->first();

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
        if ($isPaid && $order->current_status !== ShipmentDeliveryStatus::PROCESSING) {
            $order->setTransactionMethod($paymentMethod);
            $order->updateStatus(ShipmentDeliveryStatus::PROCESSING);
        }

        if (!$isPaid && $order->current_status !== ShipmentDeliveryStatus::CANCELLED) {
            $order->updateStatus(ShipmentDeliveryStatus::CANCELLED);
            return;
        }

        // Split Orders by Suppliers
        $order->parent_order_id = null;
        $order->save();

        /** @var Store $store */
        $store = $order->store;

        foreach ($order['cart_items'] as $orderCartItem) {
            // Deal Related
            $shoppingCartItem = ShoppingCartItem::where('product_variant_id', $orderCartItem['product_variant_id'])
                ->where('customer_id', $customer->_id)
                ->first();
            $dealGroupShoppingCartItem = DealGroupShoppingCartItem::where('shopping_cart_item_id', $shoppingCartItem['_id'])
                ->first();
            $group = $dealGroupShoppingCartItem->dealGroup()
                ->first();

            $group->attachOrders(collect([$order]));
            $group->attachOrderCartItems(collect([$orderCartItem]));

            $dealGroupOrderCartItem = DealGroupOrderCartItem::create([]);
            $dealGroupOrderCartItem->associateDealGroup($group);
            $dealGroupOrderCartItem->associateOrder($order);
            $dealGroupOrderCartItem->associateOrderCartItem($orderCartItem);
            $dealGroupShoppingCartItem->delete();

            // // Calculations
            $rawCalculation = $this->getRawCalculationByCartItemsAndDeals(collect([$orderCartItem]), new Collection(), null);
            $rationalizedCalculation = $this->rationalizeRawCalculation($rawCalculation);
            $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation);

            $newOrder = new Order($order->toArray());
            $newOrder->cart_items = collect([$orderCartItem])->toArray();
            $newOrder->calculations = $roundedCalculation;
            $newOrder->parent_order_id = $order->_id;
            unset($newOrder->store, $newOrder->image, $newOrder->customer, $newOrder->order_statuses);
            $newOrder->save();
        }
        return;

        // Fire Event(s)
        // Deduct MembershipPoint(s)
        $requiredPoints = $order->getTotalPoint();
        if ($requiredPoints > 0) {
            $history = $customer->deductMembershipPoints($requiredPoints);
            $history->update(['remarks' => 'Redemption Record for Order ID: ' . $order->_id]);
        }

        // Delete ShoppingCartItem(s)
        $variantIDs = collect($order->cart_items)->pluck('product_variant_id')->all();
        $variants = ProductVariant::objectIDs($variantIDs)->get();
        $customer->clearCartByStore($store, $variants);

        // Distribute MembershipPoint(s)
        event(new OrderPaid($order, $customer));

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
