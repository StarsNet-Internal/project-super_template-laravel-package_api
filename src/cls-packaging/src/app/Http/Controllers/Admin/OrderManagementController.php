<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\ShoppingCartItem;

use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;
use StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin\ShoppingCartController;

class OrderManagementController extends AdminOrderManagementController
{
    public function getOrderDetails(Request $request)
    {
        $order = json_decode(json_encode(parent::getOrderDetails($request)), true)['original'];

        $shoppingCartItems = ShoppingCartItem::where('customer_id', $order['customer_id'])
            ->where('store_id', $order['store_id'])
            ->get();

        $controller = new ShoppingCartController($request);
        $order['grouped_cart_items'] = $controller->getGroupedCartItems($shoppingCartItems->toArray());

        return response()->json($order, 200);
    }
}
