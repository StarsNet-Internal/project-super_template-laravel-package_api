<?php

namespace StarsNet\Project\CasaModernism\App\Traits\Controller;

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use App\Traits\Controller\ShoppingCartTrait as BaseShoppingCartTrait;

trait ProjectShoppingCartTrait
{
    use BaseShoppingCartTrait;

    private function getProjectRawCalculationByCartItems(
        Collection $cartItems,
        Collection $discounts,
        ?string $courierID = null,
        ?int $depositPercentage = 100
    ): array {
        // Get local discount
        $subtotalPrice = $this->getOriginalSubtotalPrice($cartItems);
        $localPriceDiscount = $this->getLocalPriceDiscount($cartItems);
        $totalPrice = $subtotalPrice - $localPriceDiscount; // Intermediate $totalPrice value

        // Get global discount
        $globalPriceDiscount = $this->getGlobalPriceDiscount($totalPrice, $discounts);
        $totalPrice -= $globalPriceDiscount; // Final $totalPrice value

        // Get shipping fee
        $shippingFee = $this->getShippingFee($totalPrice, $discounts, $courierID);
        $totalPrice += $shippingFee;

        // Deposit
        $deposit = $totalPrice * ($depositPercentage / 100);

        // Construct $rawCalculation
        $rawCalculation = [
            'currency' => 'HKD',
            'price' => [
                'subtotal' => $subtotalPrice,
                'full_total' => $totalPrice, // Deduct price_discount.local and .global
                'total' => $deposit,
            ],
            'price_discount' => [
                'local' => $localPriceDiscount,
                'global' => $globalPriceDiscount,
            ],
            'point' => [
                'subtotal' => $this->getOriginalSubtotalPoint($cartItems),
                'total' => $this->getSubtotalPoint($cartItems),
            ],
            'shipping_fee' => $shippingFee,
            'deposit_percentage' => $depositPercentage
        ];

        return $rawCalculation;
    }

    private function rationalizeProjectRawCalculation(array $rawCalculation)
    {
        return [
            'currency' => $rawCalculation['currency'],
            'price' => [
                'subtotal' => max(0, $rawCalculation['price']['subtotal']),
                'full_total' => max(0, $rawCalculation['price']['full_total']), // Deduct price_discount.local and .global
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
            'shipping_fee' => max(0, $rawCalculation['shipping_fee']),
            'deposit_percentage' => max(0, $rawCalculation['deposit_percentage'])
        ];
    }

    private function getProjectShoppingCartDetailsByCustomerAndStore(
        Customer $customer,
        Store $store,
        array $checkoutVariantIDs,
        ?string $voucherCode = null,
        ?string $courierID = null,
        ?int $depositPercentage = 100
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
        $rawCalculation = $this->getProjectRawCalculationByCartItems($checkoutCartItems, $allDiscounts, $courierID, $depositPercentage);
        $rationalizedCalculation = $this->rationalizeProjectRawCalculation($rawCalculation);
        $roundedCalculation = $this->roundingNestedArray($rationalizedCalculation); // Round off values

        // Return data
        $data = [
            'cart_items' => $cartItems,
            'gift_items' => $giftItems,
            'discounts' => $mappedDiscounts,
            'calculations' => $roundedCalculation,
            'is_voucher_applied' => !is_null($voucherDiscount),
            'is_enough_membership_points' => $customer->isEnoughMembershipPoints($rawCalculation['point']['total']),
        ];

        return $data;
    }
}
