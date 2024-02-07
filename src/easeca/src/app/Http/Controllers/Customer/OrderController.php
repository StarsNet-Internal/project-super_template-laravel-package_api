<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

use App\Constants\Model\CheckoutType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\StoreType;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use CheckoutTrait,
        StoreDependentTrait;

    protected $model = Order::class;

    public function getAllOfflineOrders(Request $request)
    {
        $account = $this->account();
        $store = $this->getStoreByValue($account['store_id']);

        // Get authenticated User information
        $customer = $this->customer();

        // Get Order(s)
        /** @var Collection $orders */
        $orders = Order::byStore($store)
            ->byCustomer($customer)
            ->get()
            ->makeHidden(['cart_items', 'gift_items']);

        // Return data
        return $orders;
    }
}
