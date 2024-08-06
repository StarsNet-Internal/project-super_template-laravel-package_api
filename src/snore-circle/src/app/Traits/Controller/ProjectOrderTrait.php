<?php

namespace StarsNet\Project\SnoreCircle\App\Traits\Controller;

// Default

use Carbon\Carbon;

trait ProjectOrderTrait
{
    private function getAllOrders()
    {
        $orders = Order::all()->toArray();
        return $orders;
    }

    private function getReceiptNumber(array $order, array $orders)
    {
        $createdAt = Carbon::parse($order['created_at']);

        $filteredOrders = array_filter($orders, function ($order) use ($createdAt) {
            return Carbon::parse($order['created_at']) < $createdAt;
        });

        $orderNumber = str_pad(count($filteredOrders) + 1, 3, '0', STR_PAD_LEFT);
        $receiptNumber = 'YZF1' . $orderNumber;

        return $receiptNumber;
    }
}
