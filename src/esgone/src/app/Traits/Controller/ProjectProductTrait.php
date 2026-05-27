<?php

namespace StarsNet\Project\Esgone\App\Traits\Controller;

use App\Models\Product;

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
            'wishlist_items',
        ];

        $products = Product::with([
            'variants' => function ($query) {
                $query->statusActive();
            },
            'variants.warehouseInventories' => function ($query) {
                $query->select(['product_variant_id', 'qty']);
            },
        ])->find($productIDs);

        foreach ($products as $product) {
            // Property access ($product->variants) would use eager load when present, but
            // getRelation() guarantees we never hit variants() and issue a new query.
            $variants = $product->getRelation('variants');
            $firstVariant = $variants->first();

            $product->first_product_variant_id = $firstVariant ? $firstVariant->_id : null;
            $product->price = $firstVariant ? $firstVariant->price : null;
            $product->point = $firstVariant ? $firstVariant->point : null;

            $product['local_discount_type'] = null;
            $product['global_discount'] = null;
            $product['rating'] = null;
            $product['review_count'] = 0;
            $product['inventory_count'] = 0;
            $product['wishlist_item_count'] = 0;
            $product['is_liked'] = false;
            $product['discounted_price'] = strval($product->price ?? 0);

            foreach ($hiddenKeys as $hiddenKey) {
                unset($product[$hiddenKey]);
            }

            foreach ($variants as $variant) {
                $inventories = $variant->relationLoaded('warehouseInventories')
                    ? $variant->getRelation('warehouseInventories')
                    : collect();
                $variant['inventory_count'] = $inventories->sum('qty');
                $variant->unsetRelation('warehouseInventories');
            }
        }

        return $products;
    }
}
