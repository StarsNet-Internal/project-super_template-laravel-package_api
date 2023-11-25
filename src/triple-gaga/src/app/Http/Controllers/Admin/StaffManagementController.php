<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\LoginType;
use App\Constants\Model\StoreType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StaffManagementController extends Controller
{
    use AuthenticationTrait, ShoppingCartTrait, RoundingTrait;

    public function updateTenantDetails(Request $request)
    {
        $accountID = $request->route('account_id');
        $account = Account::find($accountID);

        $account->update($request->all());

        return response()->json([
            'message' => 'Updated Tenant successfully'
        ], 200);
    }

    public function getOrdersByAllStores(Request $request)
    {
        // Extract attributes from $request
        $accountIds = $request->input('account_ids', []);
        $start = Carbon::create($request->start_datetime);
        $end = Carbon::create($request->end_datetime);

        $productIds = Product::whereIn('created_by_account_id', $accountIds)
            ->pluck('_id')
            ->all();
        $productVariantIds = ProductVariant::whereIn('product_id', $productIds)
            ->pluck('_id')
            ->all();

        // Get Order(s)
        $orders = Order::whereIn('cart_items.product_variant_id', $productVariantIds)
            ->whereBetween('created_at', [$start, $end])
            ->where('is_paid', true)
            ->get();

        $orders = array_map(function ($order) {
            $orders['cart_items'] = array_map(function ($cartItem) {
                $product = Product::find($cartItem['product_id']);
                $cartItem['account_id'] = $product['created_by_account_id'] ?
                    $product['created_by_account_id']
                    : null;

                return $cartItem;
            }, $order['cart_items']);

            return $orders;
        }, $orders->toArray());

        return $orders;
    }
}
