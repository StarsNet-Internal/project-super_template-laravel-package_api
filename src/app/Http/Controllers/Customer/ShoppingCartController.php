<?php

namespace Starsnet\Project\App\Http\Controllers\Customer;

use App\Constants\Model\OrderDeliveryMethod;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use Starsnet\Project\App\Models\DealGroup;
use Starsnet\Project\App\Models\DealGroupShoppingCartItem;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use Starsnet\Project\App\Traits\Controller\ProjectShoppingCartTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;

class ShoppingCartController extends CustomerShoppingCartController
{
    use AuthenticationTrait,
        ShoppingCartTrait,
        WarehouseInventoryTrait,
        ProjectShoppingCartTrait;

    public function addToCartByDealGroup(Request $request)
    {
        // Extract attributes from $request, then validate
        $productVariantID = $request->product_variant_id;
        $dealGroupID = $request->deal_group_id;
        $qty = $request->qty;

        $variant = ProductVariant::find($productVariantID);
        $group = DealGroup::find($dealGroupID);

        if (!$group->isDealGroupValid()) {
            return response()->json([
                'message' => 'Invalid Deal Group'
            ], 400);
        }

        $customer = $this->customer();

        $existingCartItem = $customer
            ->shoppingCartItems()
            ->byStore($this->store)
            ->where('product_variant_id', $productVariantID)
            ->first();

        $res = $this->addToCart($request);

        if ($qty == 0) {
            $existingDealGroupShoppingCartItem = DealGroupShoppingCartItem::where('shopping_cart_item_id', $existingCartItem['_id'])->first();
            $existingDealGroupShoppingCartItem->delete();
        } else if (is_null($existingCartItem)) {
            $cartItem = $customer
                ->shoppingCartItems()
                ->byStore($this->store)
                ->where('product_variant_id', $productVariantID)
                ->first();
            $item = DealGroupShoppingCartItem::create([]);
            $item->associateDealGroup($group);
            $item->associateShoppingCartItem($cartItem);
        }

        return $res;
    }

    public function getAll(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($this->store);
        $addedProductVariantIDs = $cartItems->pluck('product_variant_id')->all();

        // Extract attributes from $request
        $checkoutVariantIDs = $request->checkout_product_variant_ids;
        $voucherCode = $request->voucher_code;
        $deliveryInfo = $request->delivery_info;

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;

        // Get ShoppingCartItem data
        $data = $this->getShoppingCartDetailsByDeals(
            $customer,
            $this->store,
            $checkoutVariantIDs,
            $voucherCode,
            $courierID
        );

        // Return data
        return response()->json($data);
    }

    public function clearCart()
    {
        // Get authenticated User information
        $customer = $this->customer();

        $cartItems = $customer
            ->shoppingCartItems()
            ->byStore($this->store)
            ->pluck('_id');

        // Find all ids in new collection by shopping_cart_item_id
        $items = DealGroupShoppingCartItem::whereIn('shopping_cart_item_id', $cartItems);

        $res = parent::clearCart();

        // Delete all ids in new collection
        $items->delete();

        return $res;
    }
}
