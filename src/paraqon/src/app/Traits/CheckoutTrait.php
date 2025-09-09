<?php

namespace StarsNet\Project\Paraqon\Traits;

use App\Constants\Model\DiscountTemplateDiscountType;
use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\Status;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\DiscountTemplate;
use App\Models\ProductVariant;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;

trait CheckoutTrait
{
    public function getCartItems(Customer $customer, array $checkoutVariantIDs, ?Store $store = null)
    {
        return $customer->shoppingCartItems()
            ->where('store_id', $this->store->id ?? $store->id)
            ->get()
            ->append([
                // Product information related
                'product_title',
                'product_variant_title',
                'image',
                // Calculation-related
                'local_discount_type',
                'original_price_per_unit',
                'discounted_price_per_unit',
                'original_subtotal_price',
                'subtotal_price',
                'original_point_per_unit',
                'discounted_point_per_unit',
                'original_subtotal_point',
                'subtotal_point',
            ])
            ->each(function ($item) use ($checkoutVariantIDs) {
                $item->is_checkout = in_array($item->product_variant_id, $checkoutVariantIDs);
                $item->is_refundable = false;
                $item->global_discount = null;
            });
    }

    public function calculatePriceDetails(Collection $checkoutItems): array
    {
        $subtotalPrice = $checkoutItems->sum('subtotal_price');
        $localPriceDiscount = $checkoutItems->sum(fn($item) => $item['original_subtotal_price'] - $item['subtotal_price']);
        $totalPrice = $subtotalPrice - $localPriceDiscount;
        $productQty = $checkoutItems->sum('qty');

        return [
            'subtotalPrice' => $subtotalPrice,
            'localPriceDiscount' => $localPriceDiscount,
            'totalPrice' => $totalPrice,
            'productQty' => $productQty
        ];
    }

    public function getValidDiscounts(
        string $storeID,
        Customer $customer,
        float $totalPrice,
        int $productQty,
        ?Carbon $now = null
    ): Collection {
        $now = $now ?? now();
        return DiscountTemplate::where('store_ids', $storeID)
            ->where([
                ['start_datetime', '<=', $now],
                ['end_datetime', '>=', $now]
            ])
            ->where('is_auto_apply', true)
            ->where('status', Status::ACTIVE)
            ->whereIn('customer_group_id', $customer->groups()->pluck('id')->all())
            ->where(function ($query) use ($totalPrice) {
                $query->where('min_requirement.spending', '<=', $totalPrice)
                    ->orWhereNull('min_requirement.spending');
            })
            ->where(function ($query) use ($productQty) {
                $query->where('min_requirement.product_qty', '<=', $productQty)
                    ->orWhereNull('min_requirement.product_qty');
            })
            ->get();
    }

    public function processDiscounts(
        Collection $validDiscounts,
        float $totalPrice,
        Collection $checkoutItems,
        ?string $voucherCode,
        ?Carbon $now  = null
    ): array {
        return [
            'bestPrice' => $this->getBestPriceDiscount($validDiscounts, $totalPrice),
            'buyXGetYFree' => $this->getBuyXGetYFreeDiscounts($validDiscounts, $checkoutItems),
            'freeShipping' => $this->getFreeShippingDiscount($validDiscounts),
            'voucher' => $this->getVoucherDiscount($voucherCode, $now ?? now())
        ];
    }

    private function getBestPriceDiscount(Collection $validDiscounts, float $totalPrice): ?DiscountTemplate
    {
        return $validDiscounts
            ->filter(fn($d) => in_array($d->discount_type, [
                DiscountTemplateDiscountType::PERCENTAGE,
                DiscountTemplateDiscountType::PRICE
            ]))
            ->map(function ($discount) use ($totalPrice) {
                switch ($discount->discount_type) {
                    case DiscountTemplateDiscountType::PRICE:
                        $discount->discounted_value = $discount->discount_value;
                        break;
                    case DiscountTemplateDiscountType::PERCENTAGE:
                        $discount->discounted_value = $totalPrice * $discount->discount_value / 100;
                        break;
                    default:
                        $discount->discounted_value = 0;
                        break;
                }
                return $discount;
            })
            ->sortByDesc('discounted_value')
            ->first();
    }

    private function getBuyXGetYFreeDiscounts(Collection $validDiscounts, Collection $checkoutItems): Collection
    {
        return $validDiscounts
            ->filter(function ($discount) use ($checkoutItems) {
                if ($discount->discount_type !== DiscountTemplateDiscountType::BUY_X_GET_Y_FREE) return false;

                $requiredX = $discount->x['qty'];
                $freeY = $discount->y['qty'];
                if (is_null($requiredX) || is_null($freeY) || $freeY == 0) return false;

                $matchingItem = $checkoutItems->firstWhere('product_variant_id', $discount->x['product_variant_id']);
                if (!$matchingItem) return false;

                $boughtQty = $matchingItem['qty'];
                $calculatedGiftYQty = floor($boughtQty / $requiredX) * $freeY;
                $maxFreeYQtyPerCustomer = $freeY * $discount->quota_per_customer;
                $discount->gift_y_qty = min($maxFreeYQtyPerCustomer, $calculatedGiftYQty);
                return $discount->gift_y_qty > 0;
            });
    }

    private function getFreeShippingDiscount(Collection $validDiscounts): ?DiscountTemplate
    {
        return $validDiscounts->firstWhere('template_type', DiscountTemplateType::FREE_SHIPPING);
    }

    private function getVoucherDiscount(?string $voucherCode, ?Carbon $now = null): ?DiscountTemplate
    {
        if (!$voucherCode) return null;

        $now = $now ?? now();
        $redeemedVoucher = DiscountCode::where('full_code', $voucherCode)
            ->where('is_used', false)
            ->where('is_disabled', false)
            ->where('expires_at', '>=', $now)
            ->first();

        if (is_null($redeemedVoucher)) {
            return DiscountTemplate::where('prefix', $voucherCode)
                ->where([
                    ['start_datetime', '<=', $now],
                    ['end_datetime', '>=', $now]
                ])
                ->where('is_auto_apply', false)
                ->where('status', Status::ACTIVE)
                ->whereTemplateTypes([
                    DiscountTemplateType::PROMOTION_CODE,
                    DiscountTemplateType::FREE_SHIPPING
                ])
                ->first();
        }

        return $redeemedVoucher->discountTemplate;
    }

    private function processGiftItems(Collection $buyXGetYFreeDiscounts): array
    {
        return $buyXGetYFreeDiscounts
            ->map(function ($discount) {
                $variantY = ProductVariant::find($discount->y['product_variant_id']);
                if (!$variantY) return null;

                $product = $variantY->product;
                return [
                    '_id' => null,
                    'product_variant_id' => $variantY->id,
                    'qty' => $discount->gift_y_qty,
                    'product_title' => $product->title,
                    'product_variant_title' => $variantY->title,
                    'short_description' => $variantY->short_description ?? $product->short_description,
                    'image' => $variantY->images[0] ?? $product->images[0] ?? null,
                    'original_price_per_unit' => 0,
                    'discounted_price_per_unit' => 0,
                    'original_subtotal_price' => 0,
                    'subtotal_price' => 0,
                    'original_point_per_unit' => 0,
                    'discounted_point_per_unit' => 0,
                    'original_subtotal_point' => 0,
                    'subtotal_point' => 0,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function calculateTotals(
        float $subtotalPrice,
        float $localPriceDiscount,
        float $globalPriceDiscount,
        Collection $checkoutItems,
        ?string $courierID,
        ?DiscountTemplate $freeShippingDiscount,
        ?DiscountTemplate $voucherDiscount
    ): array {
        $totalPrice = $subtotalPrice - $localPriceDiscount - $globalPriceDiscount;
        $isFreeShipping = !is_null($freeShippingDiscount) || ($voucherDiscount && $voucherDiscount->template_type === DiscountTemplateType::FREE_SHIPPING);
        $shippingFee = $isFreeShipping ? 0 : $this->calculateShippingFee($courierID, $totalPrice);
        $totalPrice += $shippingFee;

        return [
            'currency' => 'HKD',
            'price' => [
                'subtotal' => number_format($subtotalPrice, 2, '.', ''),
                'total' => number_format($totalPrice, 2, '.', ''),
            ],
            'price_discount' => [
                'local' => number_format($localPriceDiscount, 2, '.', ''),
                'global' => number_format($globalPriceDiscount, 2, '.', ''),
            ],
            'point' => [
                'subtotal' => number_format($checkoutItems->sum('original_subtotal_point'), 2, '.', ''),
                'total' => number_format($checkoutItems->sum('subtotal_point'), 2, '.', ''),
            ],
            'shipping_fee' => number_format($shippingFee, 2, '.', '')
        ];
    }

    private function calculateShippingFee(?string $courierID, float $totalPrice): float
    {
        if (is_null($courierID)) return 0;

        $courier = Courier::find($courierID);
        if (!$courier || empty($courier->shipping_fee_range)) return 0;

        foreach ($courier->shipping_fee_range as $interval) {
            if ($totalPrice >= $interval['from'] && $totalPrice < $interval['to']) return $interval['fee'];
        }

        return 0;
    }

    private function checkMembershipPoints(Customer $customer, float $requiredPoints, ?Carbon $now = null): bool
    {
        $availablePoints = $customer->membershipPoints()
            ->where('is_disabled', false)
            ->where('expires_at', '>=', $now ?? now())
            ->get()
            ->sum(fn($point) => $point->earned - $point->used);

        return $availablePoints >= $requiredPoints;
    }

    private function formatDiscounts(array $discounts): array
    {
        return collect([$discounts['bestPrice'],  $discounts['freeShipping'],  $discounts['voucher']])
            ->merge($discounts['buyXGetYFree'])
            ->filter()
            ->map(fn($d) => [
                'code' => $d->prefix ?? null,
                'title' => $d->title,
                'description' => $d->description
            ])
            ->values()
            ->all();
    }
}
