<?php

namespace StarsNet\Project\Course\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\StoreType;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use CheckoutTrait,
        StoreDependentTrait;

    protected $model = Order::class;

    public function createProductReviews(Request $request)
    {
        // Extract attributes from $request
        $orderID = $request->route('order_id');
        $reviews = $request->input('reviews', []);

        // Get Order, then validate
        /** @var Order $order */
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // if ($order->hasReviews()) {
        //     return response()->json([
        //         'message' => 'Order has already been reviewed'
        //     ], 401);
        // }

        // Validate if input is valid
        $reviewVariantIDs = collect($reviews)->pluck('product_variant_id')->all();

        if (count($reviewVariantIDs) === 0) {
            return response()->json([
                'message' => 'No valid review found'
            ], 404);
        }

        // Validate if variants purchased in this order
        $boughtVariantIDs = collect($order->cart_items)->pluck('product_variant_id')->all();

        if (count(array_diff($reviewVariantIDs, $boughtVariantIDs)) !== 0) {
            return response()->json([
                'message' => 'User did not purchase this variant'
            ], 404);
        }

        // Get authenticated User information
        $user = $this->user();

        // Get Store
        /** @var Store $store */
        $store = $order->store;

        // Create ProductReview(s)
        $productReviews = [];
        /** @var Review $review */
        foreach ($reviews as $review) {
            $variantID = $review['product_variant_id'];
            /** @var ProductVariant $variant */
            $variant = ProductVariant::find($variantID);
            /** @var Product $product */
            $product = $variant->product;

            $attributes = [
                'images' => $review['images'],
                'rating' => $review['rating'],
                'comment' => $review['comment'],
            ];
            /** @var ProductReview $productReview */
            $productReview = $order->productReviews()->create($attributes);
            $productReview->associateUser($user);
            $productReview->associateStore($store);
            $productReview->associateProduct($product);
            $productReview->associateProductVariant($variant);

            $productReviews[] = $productReview;
        }

        // Update Order
        $order->reviewProducts();

        // Return response message
        return response()->json([
            'message' => 'Submitted ' . count($productReviews) . ' reviews successfully'
        ], 200);
    }
}
