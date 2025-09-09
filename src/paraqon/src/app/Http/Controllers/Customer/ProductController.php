<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Product;

class ProductController extends Controller
{
    public function getAllOwnedProducts(Request $request)
    {
        $products = Product::statusActive()
            ->where('owned_by_customer_id', $this->customer()->_id)
            ->whereIn('listing_status', ["AVAILABLE", "PENDING_FOR_AUCTION"])
            ->get();

        foreach ($products as $product) {
            $product->product_variant_id = optional($product->variants()->latest()->first())->_id;
            $product->passed_auction_count = 0;
        }

        return $products;
    }

    public function updateListingStatuses(Request $request)
    {
        $items = $request->items;

        foreach ($items as $item) {
            $productID = $item['product_id'];
            $listingStatus = $item['listing_status'];

            $product = Product::find($productID);

            if (is_null($product)) continue;
            $attributes = ['listing_status' => $listingStatus];
            $product->update($attributes);
        }

        return response()->json([
            'message' => 'Updated ' . count($items) . ' Product(s) listing_status successfully.'
        ]);
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
