<?php

namespace StarsNet\Project\HeiFei\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\Controller\ProductTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;


class ProductController extends Controller
{
    use ProductTrait;

    public function createProduct(Request $request)
    {
        // Validate
        $account = $this->account();

        // Create Product
        $productAttributes = [
            'title' => $request->title,
            'purchase_type' => $request->purchase_type,
            'status' => Status::ACTIVE,
            'created_by_account_id' => $account->_id
        ];
        $product = Product::create($productAttributes);

        // Create ProductVariant
        $variantAttributes = [
            'title' => $request->title,
            'purchase_type' => $request->purchase_type,
            'price' => $request->price,
            'status' => Status::ACTIVE,
            'is_mobile' => $request->boolean('is_mobile'),
            'created_by_account_id' => $account->_id
        ];
        $variant = $product->variants()->create($variantAttributes);

        // Return success message
        return response()->json([
            'message' => 'Created New Product and ProductVariant successfully',
            'product_id' => $product->_id,
            'product_variant_id' => $variant->_id,
        ], 200);
    }

    public function getAllProducts(Request $request)
    {
        // Extract attributes from $request
        $accountId = $request->account_id;
        $startDateTime = $request->input('start_datetime');
        if (!is_null($startDateTime)) $startDateTime = Carbon::parse($startDateTime)->startOfDay();
        $endDateTime = $request->input('end_datetime');
        if (!is_null($endDateTime)) $endDateTime = Carbon::parse($endDateTime)->endOfDay();

        // Get Product(s)
        $products = Product::when($accountId, function ($query, $accountId) {
            $query->where('created_by_account_id', $accountId);
        })->when($startDateTime, function ($query, $startDateTime) {
            $query->where([['created_at', '>=', $startDateTime]]);
        })->when($endDateTime, function ($query, $endDateTime) {
            $query->where([['created_at', '<=', $endDateTime]]);
        })->get();

        // Return Product(s)
        return $products;
    }

    public function getAllProductVariants(Request $request)
    {
        // Extract attributes from $request
        $accountId = $request->account_id;
        $startDateTime = $request->input('start_datetime');
        if (!is_null($startDateTime)) $startDateTime = Carbon::parse($startDateTime)->startOfDay();
        $endDateTime = $request->input('end_datetime');
        if (!is_null($endDateTime)) $endDateTime = Carbon::parse($endDateTime)->endOfDay();

        // Get ProductVariant(s)
        $variants = ProductVariant::when($accountId, function ($query, $accountId) {
            $query->where('created_by_account_id', $accountId);
        })->when($startDateTime, function ($query, $startDateTime) {
            $query->where([['created_at', '>=', $startDateTime]]);
        })->when($endDateTime, function ($query, $endDateTime) {
            $query->where([['created_at', '<=', $endDateTime]]);
        })->get();

        // Return ProductVariant(s)
        return $variants;
    }
}
