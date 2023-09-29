<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Models\Product;

use App\Http\Controllers\Admin\ProductController as AdminProductController;

class ProductController extends AdminProductController
{
    public function getProductVariantsByProductID(Request $request)
    {
        $productId = $request->route('id');

        $variants = Product::find($productId)
            ->variants()
            ->statusActive()
            ->get();

        return $variants;
    }
}
