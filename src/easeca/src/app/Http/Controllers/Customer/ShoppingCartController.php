<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

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
use App\Traits\Utils\RoundingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;

class ShoppingCartController extends CustomerShoppingCartController
{
    use AuthenticationTrait,
        ShoppingCartTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait,
        RoundingTrait;

    /** @var Store $store */
    protected $store;

    protected $model = ShoppingCartItem::class;

    // public function __construct(Request $request)
    // {
    //     $account = $this->account();

    //     if ($account['store_id'] != null) {
    //         $this->store = $this->getStoreByValue($account['store_id']);
    //     } else {
    //         $this->store = $this->getStoreByValue($request->route('store_id'));
    //     }
    // }

    public function getAll(Request $request)
    {
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        $data['cart_items'] = array_map(function ($item) {
            $variant = ProductVariant::find($item['product_variant_id']);
            $item['subtotal_point'] = $this->roundingValue($variant->cost);
            return $item;
        }, $data['cart_items']);

        return response()->json($data);
    }
}
