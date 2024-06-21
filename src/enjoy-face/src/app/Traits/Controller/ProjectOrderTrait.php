<?php

namespace StarsNet\Project\EnjoyFace\App\Traits\Controller;

// Default

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

trait ProjectOrderTrait
{
    private function getReceiptNumber(array $order, array $orders)
    {
        $createdAt = Carbon::parse($order['created_at']);
        $startOfDay = $createdAt->copy()->setTimezone('Asia/Hong_Kong')->startOfDay();

        // Count the number of orders created on this day and before this order
        $filteredOrders = array_filter($orders, function ($order) use ($createdAt, $startOfDay) {
            return $startOfDay <= Carbon::parse($order['created_at']) && Carbon::parse($order['created_at']) < $createdAt;
        });
        // $orderCount = $orders->where('created_at', '>=', $startOfDay)
        //     ->where('created_at', '<=', $createdAt)
        //     ->count();

        // Generate the receipt number
        $datePart = $createdAt->format('Ymd');
        $orderNumber = str_pad(count($filteredOrders), 4, '0', STR_PAD_LEFT);
        $receiptNumber = $datePart . $orderNumber;

        return $receiptNumber;
    }
}
