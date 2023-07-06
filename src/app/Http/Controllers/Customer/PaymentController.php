<?php

namespace StarsNet\Project\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Events\Common\Payment\PaidFromPinkiePay;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShoppingCartItem;
use App\Models\Store;
use StarsNet\Project\App\Models\DealGroupShoppingCartItem;
use StarsNet\Project\App\Models\DealGroupOrderCartItem;
use StarsNet\Project\App\Traits\Controller\ProjectShoppingCartTrait;

class PaymentController extends Controller
{
    use ProjectShoppingCartTrait;

    public function onlinePaymentCallback(Request $request)
    {
        event(new PaidFromPinkiePay($request));
        return response()->json('SUCCESS', 200);
    }
}
