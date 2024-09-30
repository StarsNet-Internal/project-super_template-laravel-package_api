<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\PassedAuctionRecord;

class ProductController extends Controller
{
    public function getAllOwnedProducts(Request $request)
    {
        $customer = $this->customer();

        $products = Product::statusActive()
            ->where('owned_by_customer_id', $customer->_id)
            ->whereIn('listing_status', ["AVAILABLE", "PENDING_FOR_AUCTION"])
            ->get();

        foreach ($products as $product) {
            $product->product_variant_id = optional($product->variants()->latest()->first())->_id;

            $passedAuctionCount = PassedAuctionRecord::where(
                'customer_id',
                $customer->_id
            )->where(
                'product_id',
                $product->_id
            )->count();
            $product->passed_auction_count = $passedAuctionCount;
        }

        return $products;
    }

    public function getProductDetails(Request $request)
    {
        // Extract attributes from $request
        $productId = $request->route('product_id');

        // get Product
        $product = Product::find($productId);

        if (is_null($product)) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        if (!$product->isStatusActive()) {
            return response()->json([
                'message' => 'Product is not available for public'
            ], 404);
        }

        return response()->json($product, 200);
    }
}
