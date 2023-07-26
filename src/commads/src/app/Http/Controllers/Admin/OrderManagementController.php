<?php

namespace StarsNet\Project\Commads\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Events\Common\Order\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Traits\Controller\ReviewTrait;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Http\Controllers\Admin\OrderManagementController as AdminOrderManagementController;
use StarsNet\Project\Commads\App\Models\CustomOrderImage;
use StarsNet\Project\Commads\App\Models\CustomStoreQuote;

class OrderManagementController extends AdminOrderManagementController
{
    use ReviewTrait, StoreDependentTrait;

    protected $model = Order::class;

    public function getCustomOrderDetails(Request $request)
    {
        $orderId = $request->route('id');

        $order = json_decode(json_encode($this->getOrderDetails($request)), true)['original'];

        $quote = CustomStoreQuote::where('quote_order_id', $orderId)
            ->orWhere('purchase_order_id', $orderId)
            ->first();

        if (!is_null($quote)) {
            $images = CustomOrderImage::where('order_id', $quote->quote_order_id)
                ->orWhere('order_id', $quote->purchase_order_id)
                ->latest()
                ->first();
        } else {
            $images = CustomOrderImage::where('order_id', $orderId)
                ->latest()
                ->first();
        }

        $order['quote'] = $quote;
        $order['custom_order_images'] = $images;

        return response()->json($order, 200);
    }

    public function createCustomOrderQuote(Request $request)
    {
        $orderId = $request->route('id');
        $total = $request->input('total_price');
        $cartItems = (array) $request->input('cart_items', []);

        $order = Order::find($orderId);

        $quote = CustomStoreQuote::create([
            'total_price' => $total
        ]);
        $quote->associateQuoteOrder($order);

        $items = [];
        foreach ($cartItems as $cartItem) {
            $variant = ProductVariant::where('remarks', $cartItem['product_variant_id'])->first() ??
                ProductVariant::where('product_id', $cartItem['product_id'])
                ->where('price', 1)
                ->first();
            $items[] = [
                'product_variant_id' => $variant->_id,
                'subtotal_price' => $cartItem['subtotal_price']
            ];
        }
        $quote->cart_items = $items;
        $quote->save();

        return response()->json([
            'message' => 'Quoted Order successfully'
        ], 200);
    }
}
