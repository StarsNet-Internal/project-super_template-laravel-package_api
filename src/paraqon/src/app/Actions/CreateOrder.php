<?php

namespace StarsNet\Project\Paraqon\App\Actions;

use App\Constants\Model\DiscountTemplateDiscountType;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Courier;
use App\Models\ShoppingCartItem;
use App\Models\Product;
use App\Models\ProductVariant;

use Carbon\Carbon;

use App\Constants\Model\Status;
use App\Constants\Model\OrderDeliveryMethod;
use Illuminate\Support\Collection;

class CreateOrder
{
    // Properties
    public $customer;
    public $store;
    public $deliveryInfo;
    public $createTime;

    function __construct(
        Customer $customer,
        Store $store,
        array $deliveryInfo,
    ) {
        $this->store = $store;
        $this->customer = $customer;
        $this->deliveryInfo = $deliveryInfo;
        $this->createTime = now();
    }

    public function getAllCartItems(): Collection
    {
        return $this->customer
            ->shoppingCartItems()
            ->where('store_id', $this->store->id)
            ->get();
    }

    public function deleteInactiveCartItems(): void
    {
        $cartItems = $this->getAllCartItems();

        foreach ($cartItems as $item) {
            $variantID = $item->product_variant_id;
            /** @var ProductVariant $variant */
            $variant = ProductVariant::find($variantID);

            if (is_null($variant)) continue;
            if ($variant->status !== Status::ACTIVE) {
                $item->delete();
            }
        }

        return;
    }

    public function getFormattedCartItems(array $checkoutVariantIDs)
    {
        $cartItems = $this->getAllCartItems();

        $formattedCartItems =
            $cartItems->map(
                function ($item)
                use ($checkoutVariantIDs) {
                    // Add default keys
                    $item->is_refundable = false;
                    $item->global_discount = null;

                    // Add checkout status
                    $item->is_checkout = in_array(
                        $item->product_variant_id,
                        $checkoutVariantIDs
                    );
                }
            );
    }

    public function getDeliveryServiceShippingFee(float $orderTotalPrice): float
    {
        // Validation
        if (!is_numeric($orderTotalPrice)) return 0;

        // If method does not require fee
        $deliveryMethod = $this->deliveryInfo['method'] ?? null;
        if ($deliveryMethod !== OrderDeliveryMethod::DELIVERY) return 0;

        // Find Courier
        $courierID = $this->deliveryInfo['courier_id'] ?? null;
        if ($courierID == null) return 0;

        /** @var Courier $courier */
        $courier = Courier::find($courierID);

        $range = $courier->shipping_fee_range;
        foreach ($range as $interval) {
            if ($orderTotalPrice < $interval['from']) continue;
            if ($orderTotalPrice >= $interval['to']) continue;
            return $interval['fee'];
        }
        return 0;
    }

    public function getRawCalculations(
        array $checkoutVariantIDs,
        ?string $voucherCode
    ) {
        $checkoutCartItems = $this->getAllCartItems()
            ->filter(
                function ($item)
                use ($checkoutVariantIDs) {
                    return in_array(
                        $item->product_variant_id,
                        $checkoutVariantIDs
                    );
                }
            );

        // Get Applied DiscountTemplate(s) (Global Discount)
        $customerGroups = $this->customer->groups()->get();
        $currentTotalPrice = $checkoutCartItems->sum('subtotal_price');
        $purchasedProductQty = $checkoutCartItems->sum('qty');

        $priceDiscounts = $this->getAllDiscounts(
            $this->store,
            $this->customer,
            $customerGroups,
            $checkoutCartItems
        );

        $giftDiscounts = $this->getAllValidBuyXGetYFreeDiscounts($store, $customer, $currentTotalPrice, $purchasedProductQty, $checkoutCartItems);
        $shippingDiscounts = $this->getFreeShippingDiscounts($store, $customer, $currentTotalPrice, $purchasedProductQty);
        $voucherDiscount = $this->getVoucherDiscount($voucherCode, $store, $customer, $currentTotalPrice, $purchasedProductQty);
        $allDiscounts = $priceDiscounts->merge($giftDiscounts)
            ->merge($voucherDiscount)
            ->merge($shippingDiscounts)
            ->filter()
            ->values();
    }

    private function getAllDiscounts(
        Store $store,
        Customer $customer,
        Collection $customerGroups,
        Collection $cartItems
    ): Collection {
        $bestPriceDiscount = $this->getBestPriceDiscount(
            $this->store,
            $this->customer,
            $customerGroups,
            $cartItems,
        );

        $giftDiscounts = $this->getAllValidBuyXGetYFreeDiscounts($store, $customer, $currentTotalPrice, $purchasedProductQty, $checkoutCartItems);
        $shippingDiscounts = $this->getFreeShippingDiscounts($store, $customer, $currentTotalPrice, $purchasedProductQty);
        $voucherDiscount = $this->getVoucherDiscount($voucherCode, $store, $customer, $currentTotalPrice, $purchasedProductQty);

        $allDiscounts = $bestPriceDiscount
            ->merge($giftDiscounts)
            ->merge($voucherDiscount)
            ->merge($shippingDiscounts)
            ->filter()
            ->values();
        return $allDiscounts;
    }

    private function getBestPriceDiscount(
        Store $store,
        Customer $customer,
        Collection $customerGroups,
        Collection $cartItems
    ): Collection {
        $customerGroupIDs = $customerGroups->pluck('_id')->all();
        $orderTotalPrice = $cartItems->sum('subtotal_price');
        $orderTotalProductQuantity = $cartItems->sum('qty');

        // Get DiscountTemplate(s)
        $timestamp = $this->createTime;
        /** @var Collection $discounts */
        $discounts = $store->discountTemplates()
            ->where([
                ['start_datetime', '<=', $timestamp],
                ['end_datetime', '>=', $timestamp]
            ]) // Check the discount's date
            ->where('is_auto_apply', true) // Check if it should be auto applied
            ->where('status', Status::ACTIVE) // Check if it's status ACTIVE
            ->whereIn('customer_group_id', $customerGroupIDs) // Check if this customer belongs to the group
            ->where(
                function ($query)
                use ($orderTotalPrice) {
                    $query->where('min_requirement.spending', '<=', $orderTotalPrice)
                        ->orWhereNull('min_requirement.spending');
                }
            ) // Check if fulfilled minimum requirement for spending amount
            ->where(
                function ($query)
                use ($orderTotalProductQuantity) {
                    $query->where('min_requirement.product_qty', '<=', $orderTotalProductQuantity)
                        ->orWhereNull('min_requirement.product_qty');
                }
            ) // Check if fulfilled minimum requirement for total product quantity
            ->whereIn('discount_type', [
                DiscountTemplateDiscountType::PERCENTAGE,
                DiscountTemplateDiscountType::PRICE
            ]) // Filter discount_type
            ->get()
            ->filter(
                function ($discount)
                use ($customer) {
                    /** @var DiscountTemplate $discount */
                    if (!$discount->isEnoughQuota()) return false;

                    // Validate quota_per_customer
                    $quota = $discount['quota_per_customer'];
                    $code = $discount['prefix'];
                    $orderCount = $customer->orders()->whereDiscountsContainFullCode($code)->count();
                    if (!is_null($quota) && $quota <= $orderCount) return false;

                    return true;
                }
            )->map(
                function ($item)
                use ($orderTotalPrice) {
                    /** @var DiscountTemplate $item */
                    $item->deducted_price = $item->calculateDeductedPrice($orderTotalPrice);
                    return $item;
                }
            );

        $maxDeductedPrice = $discounts->max('deducted_price');

        // Find best DiscountTemplate
        $bestDiscount = $discounts->first(
            function ($item)
            use ($maxDeductedPrice) {
                return $item['deducted_price'] === $maxDeductedPrice;
            }
        );

        return $bestDiscount;
    }
}
