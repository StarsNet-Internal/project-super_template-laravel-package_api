<?php

namespace StarsNet\Project\ShoppingCart\App\Http\Controllers\Customer;


use App\Http\Controllers\Customer\ShoppingCartController as BaseShoppingCartController;


use App\Constants\Model\OrderDeliveryMethod;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use Illuminate\Http\Request;

class ShoppingCartController extends BaseShoppingCartController
{
    use AuthenticationTrait,
        ShoppingCartTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait;


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
        $data = $this->getShoppingCartDetailsByCustomerAndStore(
            $customer,
            $this->store,
            $checkoutVariantIDs,
            $voucherCode,
            $courierID
        );

        // Service Fee
        $totalPrice = $data['calculations']['price']['total'];

        $serviceFee =  $request->input('fixed_fee', 0) + $totalPrice * $request->input('variable_fee', 0);
        $totalPricePlusServiceFee =  $totalPrice + $serviceFee;

        $serviceFee = (string) $serviceFee;
        $totalPricePlusServiceFee = (string) $totalPricePlusServiceFee;


        $data['calculations']['service_fee'] = $serviceFee;
        $data['calculations']['total_price_plus_service_fee'] = $totalPricePlusServiceFee;

        // Return data
        return response()->json($data);
    }
}
