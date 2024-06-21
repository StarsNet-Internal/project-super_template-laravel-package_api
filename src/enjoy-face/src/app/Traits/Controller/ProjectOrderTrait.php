<?php

namespace StarsNet\Project\EnjoyFace\App\Traits\Controller;

// Default

use App\Models\Order;
use Illuminate\Support\Collection;
use Carbon\Carbon;

trait ProjectOrderTrait
{
    private function getReceiptNumber(array $order, Collection $orders)
    {
        $createdAt = Carbon::parse($order['created_at']);
        $startOfDay = $createdAt->copy()->setTimezone('Asia/Hong_Kong')->startOfDay();

        // Count the number of orders created on this day and before this order
        $orderCount = $orders::where('created_at', '>=', $startOfDay)
            ->where('created_at', '<=', $createdAt)
            ->count();

        // Generate the receipt number
        $datePart = $createdAt->format('Ymd');
        $orderNumber = str_pad($orderCount, 4, '0', STR_PAD_LEFT);
        $receiptNumber = $datePart . $orderNumber;

        return $receiptNumber;
    }
}
