<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Internal;


use App\Http\Controllers\Controller;
use App\Models\Order;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectOrderTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use ProjectOrderTrait;

    public function getOrderNumber(Request $request)
    {
        $order = Order::find($request->route('id'));
        $bookings = $this->getOfflineOrders();
        return response()->json([
            'cashier_id' => $this->getReceiptNumber($order, $bookings),
        ]);
    }
}
