<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use Illuminate\Http\Request;
use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;
use App\Models\ProductVariant;

class ShoppingCartController extends CustomerShoppingCartController
{
    public function getAll(Request $request)
    {
        // Fetch data from previous function
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        // Append ProductVariant per cart_item
        foreach ($data['cart_items'] as $key => $item) {
            $productVariantId = $item['product_variant_id'];
            $variant = ProductVariant::find($productVariantId);

            $data['cart_items'][$key]['variant'] = $variant;
        }

        // Return modified data
        return response()->json($data);
    }

    public function getAllHere(Request $request)
    {
        // Fetch data from previous function
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        // Append ProductVariant per cart_item
        foreach ($data['cart_items'] as $key => $item) {
            $productVariantId = $item['product_variant_id'];
        }
    }
}
