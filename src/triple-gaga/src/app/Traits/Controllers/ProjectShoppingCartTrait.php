<?php

namespace StarsNet\Project\TripleGaga\Traits\Controllers;

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
use App\Traits\Utils\RoundingTrait;
use App\Traits\Controller\ShoppingCartTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;


trait ProjectShoppingCartTrait
{
    use RoundingTrait, ShoppingCartTrait;

    private function getGlobalPriceDiscountByTenant($price, Collection $discounts, Collection $cartItems)
    {
        if ($discounts->count() === 0) return 0;

        $items = $cartItems->map(function ($item) {
            $item->original_price = $item->subtotal_price;
            $item->final_price = $item->subtotal_price;
            return $item;
        });

        // Filter discounts with PRICE and PERCENTAGE discount_type only
        $discounts = $discounts->filter(function ($item) {
            return in_array($item->discount_type, [
                DiscountTemplateDiscountType::PRICE,
                DiscountTemplateDiscountType::PERCENTAGE
            ]);
        })
            ->sortByDesc('discount_value');

        // Step 1: Apply cart-level discounts proportionally
        $cartLevelDiscounts = $discounts->whereNull('created_by_account_id');

        foreach ($cartLevelDiscounts as $discount) {
            foreach ($items as $item) {
                $proportion = $item->original_price / $price;

                if ($discount->discount_type === 'PRICE') {
                    $item->final_price -= $discount->discount_value * $proportion;
                } elseif ($discount->discount_type === 'PERCENTAGE') {
                    $item->final_price *= 1 - $discount->discount_value / 100;
                }
            }
        }

        // Step 2: Apply item-level discounts
        $itemLevelDiscounts = $discounts->whereNotNull('created_by_account_id');

        foreach ($itemLevelDiscounts as $discount) {
            foreach ($items as $item) {
                if ($item->created_by_account_id == $discount->created_by_account_id) {
                    if ($discount->discount_type === 'PRICE') {
                        $item->final_price -= $discount->discount_value;
                    } elseif ($discount->discount_type === 'PERCENTAGE') {
                        $item->final_price *= 1 - $discount->discount_value / 100;
                    }
                }
            }
        }

        // Clamp final price to >= 0 and round
        $items->each(function ($item) {
            $item->final_price = max(0, round($item->final_price, 2));
        });

        // Total after discount
        $totalAfter = $items->sum('final_price');

        // Total discount amount
        $totalDiscount = round($price - $totalAfter, 2);

        return $totalDiscount;
    }

    private function getRawCalculationByCartItemsAndTenant(
        Collection $cartItems,
        Collection $discounts,
        ?string $courierID = null
    ): array {
        // Get local discount
        $subtotalPrice = $this->getOriginalSubtotalPrice($cartItems);
        $localPriceDiscount = $this->getLocalPriceDiscount($cartItems);
        $totalPrice = $subtotalPrice - $localPriceDiscount; // Intermediate $totalPrice value

        // Get global discount
        $globalPriceDiscount = $this->getGlobalPriceDiscountByTenant($totalPrice, $discounts, $cartItems);
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

    private function getShoppingCartDetailsByCustomerAndTenant(
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
            $item->created_by_account_id = $item->product->created_by_account_id;
        }

        // Filter checkoutItems
        $cartItems = $this->setCartItemsIsRefundable($cartItems);
        $checkoutCartItems = $this->setCartItemsIsCheckout($cartItems, $checkoutVariantIDs);

        // Get Applied DiscountTemplate(s) (Global Discount)
        $currentTotalPrice = $this->getSubtotalPrice($checkoutCartItems);
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
        $rawCalculation = $this->getRawCalculationByCartItemsAndTenant($checkoutCartItems, $allDiscounts, $courierID);
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
