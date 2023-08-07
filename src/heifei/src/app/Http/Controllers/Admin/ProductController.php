<?php

namespace StarsNet\Project\HeiFei\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Product;
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
        $product = Product::create($request->all());
        $product->update(['created_by_account_id' => $account->_id]);

        // Return success message
        return response()->json([
            'message' => 'Created New Product successfully',
            '_id' => $product->_id
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
}
