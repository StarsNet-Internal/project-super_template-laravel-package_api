<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

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

use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;

class ShoppingCartController extends CustomerShoppingCartController
{
    public function getAll(Request $request)
    {
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        $data['cart_items'] = array_map(function ($item) {
            $variant = ProductVariant::find($item['product_variant_id']);
            $item['qty'] = $variant->cost;
            $item['product_variant_title'] = $this->store->title;
            if (count($this->store->images)) {
                $item['image'] = $this->store->images[0];
            }
            return $item;
        }, $data['cart_items']);

        return response()->json($data);
    }
}
