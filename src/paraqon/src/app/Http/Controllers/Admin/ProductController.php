<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
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
                ])
                    ->statuses([
                        Status::DRAFT,
                        Status::ACTIVE,
                        Status::ARCHIVED
                    ])
                    ->select(
                        'product_id',
                        'price',
                        'point'
                    );
            },
            'reviews',
            'wishlistItems',
            'warehouseInventories'
        ])->get([
            '_id',
            'title',
            'images',
            'status',
            'updated_at',
            'created_at',
            'product_interface',
            'prefix',
            'stock_no'
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
            $product['first_variant_id'] = optional($collectedVariants->first())->_id;
            $product['product_interface'] = $product->product_interface;

            $product['prefix'] = $product->prefix;
            $product['stock_no'] = $product->stock_no;

            unset($product['variants'], $product['reviews'], $product['warehouseInventories'], $product['wishlistItems']);
        }

        return $products;
    }
}
