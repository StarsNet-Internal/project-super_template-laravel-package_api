<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

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

class ProductController extends Controller
{
    use ProductTrait, ReviewTrait;

    use Flattenable, Cacheable;

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
                ])->statusActive()->select('product_id', 'price', 'point');
            },
            'reviews',
            'wishlistItems',
            'warehouseInventories'
        ])->get([
            '_id', 'title', 'images', 'status', 'updated_at', 'created_at', 'store_id'
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

            unset($product['variants'], $product['reviews'], $product['warehouseInventories'], $product['wishlistItems']);
        }

        return $products;
    }

    public function updateReviewStatus(Request $request)
    {
        // Extract attributes from $request
        $reviewIDs = $request->input('ids', []);
        $status = $request->input('status');

        $reviews = ProductReview::find($reviewIDs);

        foreach ($reviews as $review) {
            $review->updateStatus($status);
        }

        // Return success message
        return response()->json([
            'message' => 'Updated ' . $reviews->count() . ' Post(s) successfully'
        ], 200);
    }
}
