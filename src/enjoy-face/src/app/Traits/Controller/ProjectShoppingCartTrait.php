<?php

namespace StarsNet\Project\EnjoyFace\App\Traits\Controller;

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
use Illuminate\Support\Facades\Log;

trait ProjectShoppingCartTrait
{
    use RoundingTrait, ShoppingCartTrait;

    private function removeNonActiveOrOutOfStockCartItems(Collection $cartItems)
    {
        // Remove non ACTIVE ProductVariant(s) from Cart
        /** @var ShoppingCartItem $item */
        foreach ($cartItems as $item) {
            $variantID = $item->product_variant_id;
            /** @var ProductVariant $variant */
            $variant = ProductVariant::find($variantID);

            if (is_null($variant)) continue;
            if ($variant->status !== Status::ACTIVE || $variant->getTotalInventory() == 0) {
                $item->delete();
            }
        }
        return;
    }

    private function getInStockShoppingCartDetailsByCustomerAndStore(
        Customer $customer,
        Store $store,
        array $checkoutVariantIDs,
        ?string $voucherCode = null,
        ?string $courierID = null
    ) {
        // Get ShoppingCartItem(s), and extract checkout-required ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($store);
        $this->removeNonActiveOrOutOfStockCartItems($cartItems);

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
        $rawCalculation = $this->getRawCalculationByCartItems($checkoutCartItems, $allDiscounts, $courierID);
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
