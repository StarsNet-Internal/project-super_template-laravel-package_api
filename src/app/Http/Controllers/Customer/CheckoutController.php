<?php

namespace StarsNet\Project\App\Http\Controllers\Customer;

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
use StarsNet\Project\App\Models\CustomStoreQuote;

class CheckoutController extends CustomerCheckoutController
{
    use ShoppingCartTrait,
        CheckoutTrait,
        WarehouseInventoryTrait,
        StoreDependentTrait;

    protected $model = Checkout::class;

    protected $store;

    public function checkOutQuote(Request $request)
    {
        $quoteOrderId = $request->quote_order_id;

        $res = json_decode(json_encode($this->checkOut($request)), true)['original'];

        if (!is_null($quoteOrderId)) {
            $quote = CustomStoreQuote::where('quote_order_id', $quoteOrderId)->first();
            $quote->update([
                'purchase_order_id' => $res['order_id']
            ]);
        }

        return $res;
    }

    public function onlinePaymentCallback(Request $request)
    {
        return response()->json('SUCCESS', 200);
    }
}
