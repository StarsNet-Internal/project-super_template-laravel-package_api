<?php

namespace StarsNet\Project\Commads\App\Traits\Controller;

// Default

use App\Constants\Model\ProductVariantDiscountType;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\Store;
use App\Traits\Utils\RoundingTrait;
use App\Traits\Controller\ProductTrait as BaseProductTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait ProductTrait
{
    use RoundingTrait, BaseProductTrait;

    private function getProductsInfoByEagerLoading(array $productIDs)
    {
        $storeID = $this->store->_id;

        $products = Product::with([
            'variants' => function ($productVariant) {
                $productVariant->with([
                    'discounts' => function ($discount) {
                        $discount->applicableForCustomer()->select('product_variant_id', 'type', 'value', 'start_datetime', 'end_datetime');
                    },
                ])->statusActive()->select('product_id', 'price', 'point');
            },
            'wishlistItems',
            'reviews',
            'warehouseInventories'
        ])
            ->find($productIDs)
            ->append(['first_product_variant_id', 'price', 'point']);

        $globalDiscounts = DiscountTemplate::where('start_datetime', '<', now())
            ->where('end_datetime', '>=', now())
            ->where('status', 'ACTIVE')
            ->get()
            ->makeVisible(['x', 'y'])
            ->makeHidden(['product_variant_x', 'product_variant_y']);

        foreach ($products as $product) {
            $collectedReviews = collect($product->reviews);
            $hasVariants = count($product['variants']) > 0;
            $key = array_search(0, array_column($product['variants']->toArray(), 'price'));
            $freeVariant = $product['variants'][$key];

            $hasLocalDiscount = $hasVariants ? count($freeVariant['discounts']) > 0 : false;

            if ($hasVariants) {
                $matchingDiscounts = $globalDiscounts->filter(function ($discount) use ($storeID, $product) {
                    return in_array($storeID, $discount->store_ids) && $discount['x.product_variant_id'] === $product->first_product_variant_id;
                });
                $product['global_discount'] = count($matchingDiscounts) > 0 ? $matchingDiscounts[0] : null;
            } else {
                $product['global_discount'] = null;
            }

            $product['rating'] = $collectedReviews->avg('rating') ?? null;
            $product['review_count'] = $collectedReviews->count() ?? 0;

            $product['inventory_count'] = collect($product->warehouseInventories)->sum('qty') ?? 0;
            $product['wishlist_item_count'] = collect($product->wishlistItems)->count() ?? 0;
            $product['is_liked'] = Auth::check() ? in_array($this->customer()->_id, array_column($product->wishlistItems->toArray(), 'customer_id')) : false;

            if ($hasLocalDiscount) {
                $price = $freeVariant['price'];
                $selectedDiscount = $freeVariant['discounts'][0];

                switch ($selectedDiscount['type']) {
                    case ProductVariantDiscountType::PRICE:
                        $price -= $selectedDiscount['value'];
                        break;
                    case ProductVariantDiscountType::PERCENTAGE:
                        $price *= (1 - $selectedDiscount['value'] / 100);
                        break;
                    default:
                        break;
                }

                $product['local_discount_type'] = $selectedDiscount['type'];
                $product['discounted_price'] = strval($this->roundingValue($price, 0));
            } else {
                $product['local_discount_type'] = null;
                $product['discounted_price'] = strval($product['price'] ?? 0);
            }

            // foreach ($hiddenKeys as $hiddenKey) {
            //     unset($product[$hiddenKey]);
            // }
        }

        $hiddenKeys = [
            'discount',
            'remarks',
            'status',
            'is_system',
            'deleted_at',
            'variants',
            'reviews',
            'warehouse_inventories',
            'wishlist_items'
        ];
        $products = array_map(function ($product) use ($hiddenKeys) {
            $key = array_search(0, array_column($product['variants'], 'price'));
            if ($key != false) {
                $variant = $product['variants'][$key];
                $product['first_product_variant_id'] = $variant['_id'];
                $product['price'] = $variant['price'];
                $product['point'] = $variant['point'];
            }

            foreach ($hiddenKeys as $hiddenKey) {
                unset($product[$hiddenKey]);
            }
            return $product;
        }, $products->toArray());

        return $products;
    }
}
