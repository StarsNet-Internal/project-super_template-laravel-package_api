<?php

namespace StarsNet\Project\Capi\App\Http\Controllers\Customer;

use App\Constants\Model\OrderDeliveryMethod;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use StarsNet\Project\Capi\App\Models\Deal;
use StarsNet\Project\Capi\App\Models\DealCategory;
use StarsNet\Project\Capi\App\Models\DealGroup;
use StarsNet\Project\Capi\App\Models\DealGroupShoppingCartItem;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use StarsNet\Project\Capi\App\Traits\Controller\ProjectShoppingCartTrait;
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
            ->byProductVariant($variant)
            ->byStore($this->store)
            ->first();

        $res = $this->addToCart($request);

        if ($qty == 0) {
            $existingDealGroupShoppingCartItem = DealGroupShoppingCartItem::where('shopping_cart_item_id', $existingCartItem['_id'])->first();
            $existingDealGroupShoppingCartItem->delete();
        } else if (is_null($existingCartItem)) {
            $cartItem = $customer
                ->shoppingCartItems()
                ->byProductVariant($variant)
                ->byStore($this->store)
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

        $data['cart_items'] = array_map(function ($item) {
            $item['product_id'] = $item['deal_id'];
            $item['discounted_price_per_unit'] = $item['deal_price_per_unit'];
            $item['subtotal_price'] = $item['deal_subtotal_price'];
            return $item;
        },  $data['cart_items']->toArray());

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

    public function getRelatedDealsUrls(Request $request)
    {
        // Extract attributes from $request
        $excludedProductIDs = $request->input('exclude_ids', []);
        $itemsPerPage = $request->items_per_page;

        // Get authenticated User information
        $customer = $this->customer();

        // Get first valid product_id
        $cartItems = $customer->getAllCartItemsByStore($this->store);
        $dealIDs = [];
        foreach ($cartItems as $cartItem) {
            $item = DealGroupShoppingCartItem::with([
                'dealGroup',
                'dealGroup.deal'
            ])->where('shopping_cart_item_id', $cartItem['_id'])
                ->first();
            $dealIDs[] = $item['dealGroup']['deal']['_id'];
        }
        // return $dealIDs;
        $addedProductIDs = $cartItems->pluck('_id')->all();
        $productID = count($addedProductIDs) > 0 ? $dealIDs[0] : null;

        if (!is_null($productID)) {
            /** @var Product $product */
            $product = Deal::find($productID);
            // Append to excluded Product
            $excludedProductIDs[] = $product->_id;
        }

        // Initialize a Product collector
        $products = [];

        /*
        *   Stage 1:
        *   Get Product(s) from System ProductCategory, recommended-products
        */
        $systemCategory = DealCategory::slug('recommended-products')->first();

        if (!is_null($systemCategory)) {
            // Get Product(s)
            $recommendedProducts = $systemCategory->deals()
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $recommendedProducts = $recommendedProducts->shuffle(); // randomize ordering

            // Collect data
            $products = array_merge($products, $recommendedProducts->all()); // collect Product(s)
            $excludedProductIDs = array_merge($excludedProductIDs, $recommendedProducts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 2:
        *   Get Product(s) from active, related ProductCategory(s)
        */
        if (isset($product) && !is_null($product)) {
            // Get related ProductCategory(s) by Product and within Store
            $relatedCategories = $product->categories()
                ->storeID($this->store)
                ->statusActive()
                ->get();

            $relatedCategoryIDs = $relatedCategories->pluck('_id')->all();

            // Get Product(s)
            $relatedProducts = Deal::whereHas('categories', function ($query) use ($relatedCategoryIDs) {
                $query->whereIn('_id', $relatedCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $relatedProducts = $relatedProducts->shuffle(); // randomize ordering

            // Collect data
            $products = array_merge($products, $relatedProducts->all()); // collect Product(s)
            $excludedProductIDs = array_merge($excludedProductIDs, $relatedProducts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 3:
        *   Get Product(s) assigned to this Store's active ProductCategory(s)
        */
        // Get remaining ProductCategory(s) by Store
        if (!isset($relatedCategoryIDs)) $relatedCategoryIDs = [];
        $otherCategories = DealCategory::storeID($this->store)
            ->statusActive()
            ->excludeIDs($relatedCategoryIDs)
            ->get();

        if ($otherCategories->count() > 0) {
            $otherCategoryIDs = $otherCategories->pluck('_id')->all();

            // Get Product(s)
            $otherProducts = Deal::whereHas('categories', function ($query) use ($otherCategoryIDs) {
                $query->whereIn('_id', $otherCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $otherProducts = $otherProducts->shuffle();

            // Collect data
            $products = array_merge($products, $otherProducts->all());
        }

        /*
        *   Stage 4:
        *   Generate URLs
        */
        $productIDsSet = collect($products)->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($productIDsSet as $IDsSet) {
            $urls[] = str_replace('https', 'http', route('deals.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all()
            ]));
        }

        // Return url(s)
        return $urls;
    }
}
