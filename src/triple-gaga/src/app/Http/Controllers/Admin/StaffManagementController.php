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
        $accountId = $request->route('id');

        $productIds = Product::when(!is_null($accountId), function ($query) use ($accountId) {
            $query->where('created_by_account_id', $accountId);
        })
            ->pluck('_id')
            ->all();
        $productVariantIds = ProductVariant::whereIn('product_id', $productIds)
            ->pluck('_id')
            ->all();

        // Get Order(s)
        $orders = Order::whereIn('cart_items.product_variant_id', $productVariantIds)->get();

        foreach ($orders as $order) {
            $filteredCartItems = $order->cart_items->filter(function ($cartItem) use ($productVariantIds) {
                return in_array($cartItem['product_variant_id'], $productVariantIds);
            });

            $order['cart_items'] = $filteredCartItems->toArray();
        }

        $orders = array_map(function ($order) {
            $order['calculations']['price']['subtotal'] = $this->roundingValue($this->getSubtotalPrice(collect($order['cart_items'])));
            return $order;
        }, $orders->toArray());

        $mainStoreOrders = array_values(array_filter($orders, function ($order) {
            return $order['store']['type'] == StoreType::MAIN;
        }));
        $miniStoreOrders = array_values(array_filter($orders, function ($order) {
            return $order['store']['type'] == StoreType::MINI;
        }));
        $offlineStoreOrders = array_values(array_filter($orders, function ($order) {
            return $order['store']['type'] == StoreType::OFFLINE;
        }));

        $data = [
            'main' => $mainStoreOrders,
            'mini' => $miniStoreOrders,
            'offline' => $offlineStoreOrders,
        ];

        // Return data
        return response()->json($data, 200);
    }
}
