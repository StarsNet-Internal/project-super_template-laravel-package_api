<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Store;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\ProductVariantDiscount;
use App\Models\ProductVariantOption;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\ReviewTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use App\Traits\Utils\Flattenable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Admin\ProductController as AdminProductController;

class ProductController extends AdminProductController
{
    use ProductTrait, ReviewTrait;

    use Flattenable, Cacheable;

    protected $model = Product::class;

    public function getAllProducts(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Retrieve required models
        $products = Product::statusesAllowed(Status::$typesForAdmin, $statuses)->with([
            'variants' => function ($productVariant) {
                $productVariant->with([
                    'discounts' => function ($discount) {
                        $discount->applicableForCustomer()->select('product_variant_id', 'type', 'value', 'start_datetime', 'end_datetime');
                    },
                ])->select('product_id', 'price', 'point', 'sku');
            },
            'reviews',
            'wishlistItems',
            'warehouseInventories'
        ])->get([
            '_id', 'title', 'images', 'status', 'updated_at', 'created_at', 'store_id', 'cloned_from_product_id'
        ]);

        foreach ($products as $key => $product) {
            // Collect ProductVariants, and calculate the discountedPrice
            $collectedVariants = collect($product->variants->map(
                function ($variant) {
                    $discountedPrice = $variant->price;

                    if ($variant->discounts->count() > 0) {
                        $selectedDiscount = $variant->discounts[0];

                        switch ($selectedDiscount['type']) {
                            case ProductVariantDiscountType::PRICE:
                                $discountedPrice -= $selectedDiscount['value'];
                                break;
                            case ProductVariantDiscountType::PERCENTAGE:
                                $discountedPrice *= (1 - $selectedDiscount['value'] / 100);
                                break;
                            default:
                                break;
                        }
                    }

                    $variant['discounted_price'] = $discountedPrice;
                    return $variant;
                }

            ));
            $collectedReviews = collect($product->reviews);
            $firstVariant = $collectedVariants->first();

            // Append attributes
            $product['min_original_price'] = max($collectedVariants->min('price'), 0) ?? 0;
            $product['max_original_price'] = max($collectedVariants->max('price'), 0) ?? 0;
            $product['min_discounted_price'] = (string) max($collectedVariants->min('discounted_price'), 0) ?? 0;
            $product['max_discounted_price'] = (string) max($collectedVariants->max('discounted_price'), 0) ?? 0;
            $product['min_point'] = max($collectedVariants->min('point'), 0) ?? 0;
            $product['max_point'] = max($collectedVariants->max('point'), 0) ?? 0;
            $product['rating'] = $collectedReviews->avg('rating') ?? 0;
            $product['review_count'] = $collectedReviews->count() ?? 0;
            $product['inventory_count'] = collect($product->warehouseInventories)->sum('qty') ?? 0;
            $product['wishlist_item_count'] = collect($product->wishlistItems)->count() ?? 0;
            $product['sku'] = $firstVariant ? $firstVariant['sku'] : null;
            $product['product_variant_title'] = $firstVariant ? $firstVariant['title'] : null;

            unset($product['variants'], $product['reviews'], $product['warehouseInventories'], $product['wishlistItems']);
        }

        return $products;
    }

    public function copyProducts(Request $request)
    {
        $productIds = $request->input('product_ids', []);
        $storeIds = $request->input('store_ids', []);

        foreach ($productIds as $productId) {
            foreach ($storeIds as $storeId) {
                $originalProduct = Product::find($productId);
                $variants = $originalProduct->variants()->get();
                foreach ($variants as $variant) {
                    $variant->appendTotalWarehouseInventoryAttributes();
                    $variant->appendLatestDiscount();
                }
                $originalProduct->variants = $variants;

                $originalVariants = $originalProduct->variants->toArray();
                $originalProduct = $originalProduct->toArray();

                $clonedProduct = Product::create();
                $clonedVariant = $clonedProduct->createVariant([
                    'status' => Status::DRAFT,
                ]);

                unset($originalProduct['category_ids']);
                unset($originalProduct['variants']);
                $productAttributes = [];
                foreach ($originalProduct as $key => $value) {
                    $productAttributes[$key] = $value;
                }
                $clonedProduct->update($productAttributes);

                foreach ($originalVariants as $input) {
                    // Extract attributes
                    $variantAttributes = [];
                    foreach ($input as $key => $value) {
                        $variantAttributes[$key] = $value;
                    }
                    $variantAttributes['product_id'] = $clonedProduct->_id;

                    $clonedVariant->update($variantAttributes);

                    // Validate if updating ProductVariantDiscount is required
                    if (!array_key_exists('discount', $input)) continue;
                    $discountInput = $input['discount'];
                    if (is_null($discountInput)) continue;

                    // Extract attributes
                    $discountAttributes = [];
                    foreach ($discountInput as $key => $value) {
                        $discountAttributes[$key] = $value;
                    }

                    $discount = $clonedVariant->createDiscount($discountAttributes);
                }

                $clonedProduct->update([
                    'store_id' => $storeId,
                    'cloned_from_product_id' => $productId,
                ]);

                $store = Store::find($storeId);
                $category = $store->productCategories()
                    ->where('slug', 'all-products')
                    ->first();
                $category->attachProducts(collect([$clonedProduct]));
            }
        }

        return response()->json([
            'message' => 'Copied Products successfully'
        ], 200);
    }
}
