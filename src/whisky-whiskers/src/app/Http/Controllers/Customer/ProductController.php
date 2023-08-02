<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class ProductController extends Controller
{
    public function getAllProducts(Request $request)
    {
        $account = $this->account();

        $products = Product::statusActive()
            ->where('account_id', $account->id)
            ->get();

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

    // public function updateProductDetails(Request $request)
    // {
    //     // Extract attributes from $request
    //     $productId = $request->product_id;

    //     // get Product
    //     $product = Product::find($productId);

    //     if (is_null($product)) {
    //         return response()->json([
    //             'message' => 'Product not found'
    //         ], 404);
    //     }

    //     if (!$product->isStatusActive()) {
    //         return response()->json([
    //             'message' => 'Product is not available for public'
    //         ], 404);
    //     }

    //     $account = $this->account();

    //     if ($product->account_id != $account->_id) {
    //         return response()->json([
    //             'message' => 'Product does not belong to this account'
    //         ], 404);
    //     }

    //     return response()->json($product, 200);
    // }
}
