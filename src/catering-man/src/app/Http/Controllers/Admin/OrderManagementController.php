<?php

namespace StarsNet\Project\CateringMan\App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;
use StarsNet\Project\CateringMan\App\Http\Controllers\Admin\ShoppingCartController;
use App\Models\ShoppingCartItem;

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
