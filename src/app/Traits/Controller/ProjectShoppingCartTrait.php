<?php

namespace StarsNet\Project\App\Traits\Controller;

// Default

use App\Constants\Model\DiscountTemplateDiscountType;
use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\Status;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use App\Models\WarehouseInventory;
use StarsNet\Project\App\Models\DealGroup;
use StarsNet\Project\App\Models\DealGroupShoppingCartItem;
use StarsNet\Project\App\Models\DealGroupOrderCartItem;
use App\Traits\Utils\RoundingTrait;
use App\Traits\Controller\ShoppingCartTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

trait ProjectShoppingCartTrait
{
    use RoundingTrait, ShoppingCartTrait;

    private function getSumByKey(Collection $cartItems, string $key)
    {
        return $cartItems->sum($key);
    }

    private function getRawCalculationByCartItemsAndDeals(
        Collection $cartItems,
        Collection $discounts,
        ?string $courierID = null
    ): array {
        // Get local discount
        $subtotalPrice = $this->getSumByKey($cartItems, 'deal_subtotal_price');
        $localPriceDiscount = $this->getLocalPriceDiscount($cartItems);
        $totalPrice = $subtotalPrice - $localPriceDiscount; // Intermediate $totalPrice value

        // Get global discount
        $globalPriceDiscount = $this->getGlobalPriceDiscount($totalPrice, $discounts);
        $totalPrice -= $globalPriceDiscount; // Final $totalPrice value

        // Get shipping fee
        $shippingFee = $this->getShippingFee($totalPrice, $discounts, $courierID);
        $totalPrice += $shippingFee;

        // Construct $rawCalculation
        $rawCalculation = [
            'currency' => 'HKD',
            'price' => [
                'subtotal' => $subtotalPrice,
                'total' => $totalPrice, // Deduct price_discount.local and .global
            ],
            'price_discount' => [
                'local' => $localPriceDiscount,
                'global' => $globalPriceDiscount,
            ],
            'point' => [
                'subtotal' => $this->getOriginalSubtotalPoint($cartItems),
                'total' => $this->getSubtotalPoint($cartItems),
            ],
            'shipping_fee' => $shippingFee
        ];

        return $rawCalculation;
    }

    private function getDiscountedPrice(DealGroup $group)
    {
        $count = $group->quantity_sold;

        $deal = $group->deal()->first();
        $tiers = $deal->tiers()->get()->toArray();

        usort($tiers, function ($a, $b) {
            return $b['user_count'] <=> $a['user_count'];
        });

        foreach ($tiers as $tier) {
            if ($count >= $tier['user_count']) {
                return $tier['discounted_price'];
            }
        }
        // If userCount is lower than any tier, return price of first active variant
        $variant = $deal->product()->first()->variants()->statusActive()->first();
        return $this->roundingValue($variant->price);
    }

    private function getShoppingCartDetailsByDeals(
        Customer $customer,
        Store $store,
        array $checkoutVariantIDs,
        ?string $voucherCode = null,
        ?string $courierID = null
    ) {
        // Get ShoppingCartItem(s), and extract checkout-required ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);
        $this->removeNonActiveCartItems($cartItems);

        // Get ShoppingCartItem(s), and extract checkout-required ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);
        // Append default key per $item
        /** @var ShoppingCartItem $item */
        foreach ($cartItems as $item) {
            $item->is_checkout = false;
            $item->is_refundable = false;
            $item->global_discount = null;
        }

        // Filter checkoutItems
        $cartItems = $this->setCartItemsIsRefundable($cartItems);
        $checkoutCartItems = $this->setCartItemsIsCheckout($cartItems, $checkoutVariantIDs);

        foreach ($checkoutCartItems as $index => $item) {
            $group = DealGroupShoppingCartItem::where('shopping_cart_item_id', $item['_id'])
                ->first()
                ->dealGroup()
                ->first();
            $price = $this->getDiscountedPrice($group);

            $item->deal_price_per_unit = $price;
            $item->deal_subtotal_price = $this->roundingValue($price * $item->qty);
            $item->is_valid_deal = $group->isDealGroupValid() ? true : false;
        }

        // Get Applied DiscountTemplate(s) (Global Discount)
        // $currentTotalPrice = $this->getSubtotalPrice($checkoutCartItems);
        $currentTotalPrice = $this->getSumByKey($checkoutCartItems, 'deal_subtotal_price');
        $purchasedProductQty = $this->getPurchasedProductQty($checkoutCartItems);

        $priceDiscounts = $this->getValidPriceOrPercentageDiscount($store, $customer, $currentTotalPrice, $purchasedProductQty);
        $giftDiscounts = $this->getAllValidBuyXGetYFreeDiscounts($store, $customer, $currentTotalPrice, $purchasedProductQty, $checkoutCartItems);
        $shippingDiscounts = $this->getFreeShippingDiscounts($store, $customer, $currentTotalPrice, $purchasedProductQty);
        $voucherDiscount = $this->getVoucherDiscount($voucherCode, $store, $customer, $currentTotalPrice, $purchasedProductQty);
        $allDiscounts = $priceDiscounts->merge($giftDiscounts)
            ->merge($voucherDiscount)
            ->merge($shippingDiscounts)
            ->filter()
            ->values();

        $mappedDiscounts = $allDiscounts->map(function ($item) {
            return [
                'code' => $item['prefix'],
                'title' => $item['title'],
                'description' => $item['description'],
            ];
        });

        // Get gift_items
        $giftItems = $this->getGiftItems($checkoutCartItems, $allDiscounts);

        // Get calculations
        $rawCalculation = $this->getRawCalculationByCartItemsAndDeals($checkoutCartItems, $allDiscounts, $courierID);
        $rationalizedCalculation = $this->rationalizeRawCalculation($rawCalculation);
        $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation); // Round off values

        // Return data
        $data = [
            'cart_items' => $cartItems,
            'gift_items' => $giftItems,
            'discounts' => $mappedDiscounts,
            'calculations' => $roundedCalculation,
            'is_voucher_applied' => !is_null($voucherDiscount),
            'is_enough_membership_points' => $customer->isEnoughMembershipPoints($rawCalculation['point']['total'])
        ];

        return $data;
    }
}
