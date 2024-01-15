<?php

namespace StarsNet\Project\Esgone\App\Traits\Controller;

// Default

use App\Constants\Model\ProductVariantDiscountType;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

trait ProjectProductTrait
{
    private function getProductsInfoByEagerLoading(array $productIDs)
    {
        $hiddenKeys = [
            'discount',
            'remarks',
            'status',
            'is_system',
            'deleted_at',
            'reviews',
            'warehouse_inventories',
            'wishlist_items'
        ];

        $products = Product::with([
            'variants' => function ($productVariant) {
                $productVariant
                    ->statusActive()
                    ->get();
            },
        ])
            ->find($productIDs)
            ->append(['first_product_variant_id', 'price', 'point']);

        foreach ($products as $product) {
            $product['local_discount_type'] = null;
            $product['global_discount'] = null;
            $product['rating'] = null;
            $product['review_count'] = 0;
            $product['inventory_count'] = 0;
            $product['wishlist_item_count'] = 0;
            $product['is_liked'] = false;
            $product['discounted_price'] = strval($product['price'] ?? 0);

            foreach ($hiddenKeys as $hiddenKey) {
                unset($product[$hiddenKey]);
            }

            foreach ($product['variants'] as $variant) {
                $variant['inventory_count'] = collect($variant->warehouseInventories)->sum('qty') ?? 0;
                unset($variant['warehouseInventories']);
            }
        }

        return $products;
    }
}
