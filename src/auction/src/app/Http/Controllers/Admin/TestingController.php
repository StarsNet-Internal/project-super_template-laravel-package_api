<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class TestingController extends Controller
{
    public function healthCheck()
    {
        $order = Order::find('6873c39501127543480abb70');

        // $totalPrice = (float) $order->calculations['price']['total'];
        $totalPrice = (float) "1999.56";

        $chargeAmount = $totalPrice < 1000 ? $totalPrice : (int) 1000 + ($totalPrice % 1000);

        $stripeChargedAmount = (int) $totalPrice * 100;

        $newTotalPrice = min(0, floor($totalPrice - $chargeAmount));

        return [
            'total_price' => $totalPrice,
            'charge_amount' => $chargeAmount,
            'stripe_charged_amount' => $stripeChargedAmount,
            'new_total_price' => $newTotalPrice,
        ];

        return response()->json([
            'message' => 'OK from package/auction'
        ], 200);
    }
}
