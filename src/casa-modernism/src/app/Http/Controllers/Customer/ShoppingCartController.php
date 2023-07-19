<?php

namespace StarsNet\Project\CasaModernism\App\Http\Controllers\Customer;

use App\Constants\Model\OrderDeliveryMethod;
use App\Http\Controllers\Controller;
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

use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;
use StarsNet\Project\CasaModernism\App\Traits\Controller\ProjectShoppingCartTrait;

class ShoppingCartController extends CustomerShoppingCartController
{
    use AuthenticationTrait,
        ShoppingCartTrait,
        StoreDependentTrait,
        WarehouseInventoryTrait,
        ProjectShoppingCartTrait;

    public function getAll(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Get ShoppingCartItem(s)
        $cartItems = $customer->getAllCartItemsByStore($this->store);
        $addedProductVariantIDs = $cartItems->pluck('product_variant_id')->all();

        // Validate Request
        // $validator = Validator::make($request->all(), [
        //     'checkout_product_variant_ids' => [
        //         'array',
        //     ],
        //     'voucher_code' => [
        //         'nullable',
        //     ],
        //     'checkout_product_variant_ids.*' => [
        //         Rule::in($addedProductVariantIDs)
        //     ],
        //     'delivery_info.country_code' => [
        //         'nullable'
        //     ],
        //     'delivery_info.method' => [
        //         'nullable',
        //         Rule::in(OrderDeliveryMethod::$defaultTypes)
        //     ],
        //     'delivery_info.courier_id' => [
        //         Rule::requiredIf(fn () => $request->delivery_info['method'] === OrderDeliveryMethod::DELIVERY),
        //         'exists:App\Models\Courier,_id'
        //     ],
        //     'delivery_info.warehouse_id' => [
        //         Rule::requiredIf(fn () => $request->delivery_info['method'] === OrderDeliveryMethod::SELF_PICKUP),
        //         'exists:App\Models\Warehouse,_id'
        //     ],
        // ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 400);
        // }

        // Extract attributes from $request
        $checkoutVariantIDs = $request->checkout_product_variant_ids;
        $voucherCode = $request->voucher_code;
        $deliveryInfo = $request->delivery_info;
        $depositPercentage = $request->deposit_percentage ?? 100;

        $courierID = $deliveryInfo['method'] === OrderDeliveryMethod::DELIVERY ?
            $deliveryInfo['courier_id'] :
            null;

        // Get ShoppingCartItem data
        $data = $this->getProjectShoppingCartDetailsByCustomerAndStore(
            $customer,
            $this->store,
            $checkoutVariantIDs,
            $voucherCode,
            $courierID,
            $depositPercentage
        );

        // Return data
        return response()->json($data);
    }
}
