<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Product;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;
use StarsNet\Project\TripleGaga\Traits\Controllers\RefillInventoryRequestTrait;

use App\Models\Warehouse;
use App\Traits\Controller\ProductTrait;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ProductTrait;

    public function createProduct(Request $request)
    {
        // Validate
        $account = $this->account();
        $skuCount = $account->sku_count ?? 0;
        $productCount = Product::where('created_by_account_id', $account->_id)->where('status', '!=', Status::DELETED)->count();

        if ($skuCount <= $productCount) {
            return response()->json([
                'message' => 'User can not create any more products',
            ], 403);
        }

        // Create Product
        $product = Product::create($request->all());
        $product->update(['created_by_account_id' => $this->account()->_id]);

        // Return success message
        return response()->json([
            'message' => 'Created New Product successfully',
            '_id' => $product->_id
        ], 200);
    }

    public function getTenantProducts(Request $request)
    {
        // Extract attributes from $request
        $accountId = $request->account_id;
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Retrieve required models
        $products = Product::when(!is_null($accountId), function ($query) use ($accountId) {
            $query->where('created_by_account_id', $accountId);
        })
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->with([
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
                '_id', 'title', 'images', 'status', 'updated_at', 'created_at'
            ]);

        $this->appendProductFullInformation($products);

        return $products;
    }

    public function getTenantProductVariants(Request $request)
    {
        // Extract attributes from $request
        $accountId = $request->account_id;
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Retrieve required models
        $productIds = Product::when(!is_null($accountId), function ($query) use ($accountId) {
            $query->where('created_by_account_id', $accountId);
        })
            ->pluck('_id')
            ->all();

        $variants = ProductVariant::whereIn('product_id', $productIds)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->get();

        return $variants;
    }
}
