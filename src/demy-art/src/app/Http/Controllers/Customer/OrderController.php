<?php

namespace StarsNet\Project\DemyArt\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;
use StarsNet\Project\DemyArt\App\Models\Calendar;
use StarsNet\Project\DemyArt\App\Models\OrderReview;

class OrderController extends Controller
{
    public function createOrderReview(Request $request)
    {
        // Extract attributes from $request
        $orderID = $request->route('order_id');
        $variantID = $request->product_variant_id;
        $review = $request->review;

        // Get Order, then validate
        /** @var Order $order */
        $order = Order::find($orderID);

        if (is_null($order)) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // Get ProductVariant and Product
        /** @var ProductVariant $variant */
        $variant = ProductVariant::find($variantID);

        if (is_null($variant)) {
            return response()->json([
                'message' => 'ProductVariant not found'
            ], 404);
        }

        /** @var Product $product */
        $product = $variant->product;

        // Get authenticated User information
        $user = $this->user();

        $attributes = [
            'images' => $review['images'] ?? [],
            'rating' => $review['rating'] ?? 0,
            'comment' => $review['comment'] ?? '',
        ];

        /** @var OrderReview $orderReview */
        $orderReview = OrderReview::create($attributes);
        $orderReview->associateOrder($order);
        $orderReview->associateUser($user);
        $orderReview->associateProduct($product);
        $orderReview->associateProductVariant($variant);

        // Return response message
        return response()->json([
            'message' => 'Submitted OrderReview successfully'
        ], 200);
    }

    public function getOrderReviews(Request $request)
    {
        // Extract attributes from $request
        $orderID = $request->route('order_id');
        $variantID = $request->product_variant_id;

        // Get OrderReview(s)
        $review = OrderReview::where('model_type_id', $orderID)
            ->where('product_variant_id', $variantID)
            ->get();

        return $review;
    }
}
