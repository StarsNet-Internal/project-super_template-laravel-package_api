<?php

namespace StarsNet\Project\CateringMan\App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Http\Request;

use App\Http\Controllers\Admin\ShoppingCartController as AdminShoppingCartController;
use App\Models\Category;
use StarsNet\Project\CateringMan\App\Models\CategoryShoppingCartItem;

class ShoppingCartController extends AdminShoppingCartController
{
    public function addToCartByCategory(Request $request)
    {
        // Extract attributes from $request
        $data = $request->all();
        $customer = Customer::find($request->customer_id);
        $store = Store::find($request->store_id);
        $category = Category::find($request->category_id);
        $hash = $request->hash;
        $qty = $request->qty;

        $existingCartItem = $customer
            ->shoppingCartItems()
            ->byStore($store)
            ->where('product_variant_id', $request->keyword)
            ->first();

        if (!is_null($existingCartItem)) {
            $existingCategoryShoppingCartItem = CategoryShoppingCartItem::where('shopping_cart_item_id', $existingCartItem->_id)
                ->where('hash', $hash)->first();
            if (!is_null($existingCategoryShoppingCartItem)) {
                if ($qty == 0) {
                    $data['qty'] = $existingCartItem['qty'] - $existingCategoryShoppingCartItem['qty'];
                    $res = $this->addToCart($request->replace($data));
                    $existingCategoryShoppingCartItem->delete();
                } else {
                    $data['qty'] = $existingCartItem['qty'] - $existingCategoryShoppingCartItem['qty'] + $qty;
                    $res = $this->addToCart($request->replace($data));
                    $existingCategoryShoppingCartItem->qty = $qty;
                    $existingCategoryShoppingCartItem->save();
                }
            } else {
                $data['qty'] = $existingCartItem['qty'] + $qty;
                $res = $this->addToCart($request->replace($data));
                $categoryShoppingCartItem = CategoryShoppingCartItem::create([
                    'hash' => $hash,
                    'qty' => $qty
                ]);
                $categoryShoppingCartItem->associateShoppingCartItem($existingCartItem);
                $categoryShoppingCartItem->associateCategory($category);
            }
        } else {
            $res = $this->addToCart($request);
            $cartItem = $customer
                ->shoppingCartItems()
                ->byStore($store)
                ->where('product_variant_id', $request->keyword)
                ->first();
            $categoryShoppingCartItem = CategoryShoppingCartItem::create([
                'hash' => $hash,
                'qty' => $qty
            ]);
            $categoryShoppingCartItem->associateShoppingCartItem($cartItem);
            $categoryShoppingCartItem->associateCategory($category);
        }

        return $res;
    }

    public function getAll(Request $request)
    {
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        $data['grouped_cart_items'] = $this->getGroupedCartItems($data['cart_items']);

        // Return data
        return response()->json($data);
    }

    public function clearCart(Request $request)
    {
        // Extract attributes from $request
        $customer = Customer::find($request->customer_id);
        $store = Store::find($request->store_id);

        $cartItems = $customer
            ->shoppingCartItems()
            ->byStore($store)
            ->pluck('_id');

        // Find all ids in new collection by shopping_cart_item_id
        $categoryShoppingCartItems = CategoryShoppingCartItem::whereIn('shopping_cart_item_id', $cartItems);

        $res = parent::clearCart($request);

        // Delete all ids in new collection
        $categoryShoppingCartItems->delete();

        return $res;
    }

    // public function checkOut(Request $request)
    // {
    //     $data = json_decode(json_encode(parent::checkOut($request)), true)['original'];

    //     $order_id = $data['order_id'];
    //     $order = Order::find($order_id)->first();
    // }

    public function getGroupedCartItems($cartItems)
    {
        $getID = function ($value): string {
            return $value['_id'];
        };

        $cartItemIDs = array_map($getID, $cartItems);
        $categoryCartItems = CategoryShoppingCartItem::whereIn('shopping_cart_item_id', $cartItemIDs)->get();

        // Group cart items by hash
        $groups = array_reduce($categoryCartItems->toArray(), function ($result, $categoryCartItem) {
            $hash = $categoryCartItem['hash'];
            if (!isset($result[$hash])) {
                $result[$hash] = [];
            }
            array_push($result[$hash], $categoryCartItem);
            return $result;
        }, []);

        $groupedCartItems = [];

        // Put grouped items in a new array
        foreach ($groups as $hash => $items) {
            $array = [];
            $category = CategoryShoppingCartItem::find($items[0]['_id'])->category;
            foreach ($items as $item) {
                $categoryShoppingCartItem = CategoryShoppingCartItem::find($item['_id']);
                $cartItem = $categoryShoppingCartItem->shoppingCartItem;
                $cartItem['qty'] = $categoryShoppingCartItem['qty'];
                array_push($array, $cartItem);
            }
            array_push($groupedCartItems, [
                'hash' => $hash,
                'category_id' => $category['_id'],
                'category_title' => $category['title'],
                'cart_items' => $array
            ]);
        }

        return $groupedCartItems;
    }
}
