<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Events\Common\Order\OrderCreated;
use App\Events\Common\Order\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\Checkout;
use App\Models\DiscountCode;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WarehouseInventoryTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Customer\CheckoutController as CustomerCheckoutController;

class CheckoutController extends CustomerCheckoutController
{
    use ShoppingCartTrait,
        CheckoutTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait;

    protected $model = Checkout::class;

    protected $store;

    public function getStoreByAccount(Request $request)
    {
        $account = $this->account();

        if ($account['store_id'] != null) {
            $this->store = $this->getStoreByValue($account['store_id']);
        } else {
            $this->store = $this->getStoreByValue($request->route('store_id'));
        }
    }

    public function checkOut(Request $request)
    {
        $this->getStoreByAccount($request);
        return parent::checkOut($request);
    }
}
