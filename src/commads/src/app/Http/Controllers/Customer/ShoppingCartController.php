<?php

namespace StarsNet\Project\Commads\App\Http\Controllers\Customer;

use App\Constants\Model\OrderDeliveryMethod;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\Courier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Carbon\CarbonInterval;

use StarsNet\Project\Commads\App\Models\CustomStoreQuote;
use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;

class ShoppingCartController extends CustomerShoppingCartController
{
    use AuthenticationTrait,
        ShoppingCartTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait;

    /** @var Store $store */
    protected $store;

    protected $model = ShoppingCartItem::class;

    public function addQuotedItemsToCart(Request $request)
    {
        $this->clearCart();

        $quote = CustomStoreQuote::where('quote_order_id', $request['order_id'])->first();

        if ($quote) {
            foreach ($quote->cart_items as $cart_item) {
                $modifiedRequest = $request->merge([
                    'product_variant_id' => $cart_item['product_variant_id'],
                    'qty' => $cart_item['subtotal_price']
                ]);
                $res = $this->addToCart($modifiedRequest);
            }
        } else {
            $res = $this->addToCart($request);
        }

        return $res;
    }
}
