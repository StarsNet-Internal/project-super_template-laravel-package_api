<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Traits\Utils\RoundingTrait;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;

class ShoppingCartController extends Controller
{
    use RoundingTrait;

    public function getAllAuctionCartItems(Request $request)
    {
        $storeID = $request->route('store_id');
        $store = Store::find($storeID);

        // Get authenticated User information
        $customer = $this->customer();

        // Clear Store first
        $customer->clearCartByStore($store);

        // Winning auction lots by Customer
        $wonLots = AuctionLot::where('store_id', $store->_id)
            ->where('winning_bid_customer_id', $customer->_id)
            ->get();

        foreach ($wonLots as $key => $lot) {
            $attributes = [
                'store_id' => $store->_id,
                'product_id' => $lot->product_id,
                'product_variant_id' => $lot->product_variant_id,
                'qty' => 1,
                'winning_bid' => $lot->current_bid,
                'storage_fee' => $lot->current_bid * 0.03
            ];
            $customer->shoppingCartItems()->create($attributes);
        }

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);

        // Extract attributes from $request
        $isStorage = $request->boolean('is_storage', false);
        $deliveryInfo = $request->delivery_info;

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;

        // getShoppingCartDetails calculations
        // get subtotal Price
        $subtotalPrice = 0;
        $storageFee = 0;

        foreach ($cartItems as $item) {
            // Add keys
            $item->is_checkout = true;
            $item->is_refundable = false;
            $item->global_discount = null;

            // Calculations
            $winningBid = $item->winning_bid ?? 0;
            $subtotalPrice += $winningBid;

            if ($isStorage == true) {
                $storageFee += $item->storage_fee ?? 0;
            }
        }
        $totalPrice = $subtotalPrice;

        // get shippingFee
        $courier = Courier::find($courierID);
        $shippingFee =
            !is_null($courier) ?
            $courier->getShippingFeeByTotalFee($totalPrice) :
            0;

        // form calculation data object
        $rawCalculation = [
            'currency' => 'HKD',
            'price' => [
                'subtotal' => $subtotalPrice,
                'total' => $totalPrice, // Deduct price_discount.local and .global
            ],
            'price_discount' => [
                'local' => 0,
                'global' => 0,
            ],
            'point' => [
                'subtotal' => 0,
                'total' => 0,
            ],
            'storage_fee' => $storageFee,
            'shipping_fee' => $shippingFee
        ];

        $rationalizedCalculation = $this->rationalizeRawCalculation($rawCalculation);
        $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation); // Round off values

        // Return data
        $data = [
            'cart_items' => $cartItems,
            'gift_items' => [],
            'discounts' => [],
            'calculations' => $roundedCalculation,
            'is_voucher_applied' => false,
            'is_enough_membership_points' => true
        ];

        return $data;
    }

    private function rationalizeRawCalculation(array $rawCalculation)
    {
        return [
            'currency' => $rawCalculation['currency'],
            'price' => [
                'subtotal' => max(0, $rawCalculation['price']['subtotal']),
                'total' => max(0, $rawCalculation['price']['total']),
            ],
            'price_discount' => [
                'local' => $rawCalculation['price_discount']['local'],
                'global' => $rawCalculation['price_discount']['global'],
            ],
            'point' => [
                'subtotal' => max(0, $rawCalculation['point']['subtotal']),
                'total' => max(0, $rawCalculation['point']['total']),
            ],
            'shipping_fee' => max(0, $rawCalculation['shipping_fee'])
        ];
    }
}
