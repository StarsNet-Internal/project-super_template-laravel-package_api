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

        return $products->map(function ($product) use ($hiddenKeys) {
            // getRelation('variants') guarantees we never call the relationship method variants().
            $variants = $product->getRelation('variants');
            $firstVariant = $variants->first();

            foreach ($variants as $variant) {
                $inventoryCount = 0;
                if ($variant->relationLoaded('warehouseInventories')) {
                    foreach ($variant->getRelation('warehouseInventories') as $inventory) {
                        $inventoryCount += (int) ($inventory->qty ?? 0);
                    }
                }

                // Use a plain attribute so we don't trigger any Product accessors.
                $variant->inventory_count = $inventoryCount;
                $variant->unsetRelation('warehouseInventories');
            }

            // Convert to array before adding computed keys, to avoid host Product accessors
            // (getPriceAttribute/getPointAttribute) calling $this->variants() during serialization.
            $productArray = $product->toArray();

            // Match the previous response shape but derive from eager-loaded first variant.
            $productArray['first_product_variant_id'] = $firstVariant ? $firstVariant->_id : null;
            $productArray['price'] = $firstVariant ? $firstVariant->price : null;
            $productArray['point'] = $firstVariant ? $firstVariant->point : null;
            $productArray['discounted_price'] = strval($firstVariant ? $firstVariant->price : 0);

            // Fields previously added by this trait.
            $productArray['local_discount_type'] = null;
            $productArray['global_discount'] = null;
            $productArray['rating'] = null;
            $productArray['review_count'] = 0;
            $productArray['inventory_count'] = 0;
            $productArray['wishlist_item_count'] = 0;
            $productArray['is_liked'] = false;

            foreach ($hiddenKeys as $hiddenKey) {
                unset($productArray[$hiddenKey]);
            }

            // Ensure variant inventory relation isn't present in the output.
            if (isset($productArray['variants'])) {
                foreach ($productArray['variants'] as &$variantArray) {
                    unset($variantArray['warehouse_inventories'], $variantArray['warehouseInventories']);
                }
                unset($variantArray);
            }

            return $productArray;
        })->values();
    }
}
