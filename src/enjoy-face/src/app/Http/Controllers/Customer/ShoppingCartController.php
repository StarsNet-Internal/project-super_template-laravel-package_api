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
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectShoppingCartTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Carbon\CarbonInterval;

use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;

class ShoppingCartController extends CustomerShoppingCartController
{
    use ProjectShoppingCartTrait;

    public function getAll(Request $request)
    {
        if ($request->route('store_id') != 'default-mini-store') {
            $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

            $data['cart_items'] = array_map(function ($item) {
                $variant = ProductVariant::find($item['product_variant_id']);
                $item['qty'] = $variant->weight;
                $item['discounted_price_per_unit'] = strval($variant->cost);
                $item['product_variant_title'] = $this->store->title;
                // if (count($this->store->images)) {
                //     $item['image'] = $this->store->images[0];
                // }
                return $item;
            }, $data['cart_items']);

            return response()->json($data);
        } else {
            // Get authenticated User information
            $customer = $this->customer();

            // Extract attributes from $request
            $checkoutVariantIDs = $request->checkout_product_variant_ids;
            $voucherCode = $request->voucher_code;
            $deliveryInfo = $request->delivery_info;

            $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
                $deliveryInfo['courier_id'] :
                null;

            // Get ShoppingCartItem data
            $data = $this->getInStockShoppingCartDetailsByCustomerAndStore(
                $customer,
                $this->store,
                $checkoutVariantIDs,
                $voucherCode,
                $courierID
            );

            // Return data
            return response()->json($data);
        }
    }
}
