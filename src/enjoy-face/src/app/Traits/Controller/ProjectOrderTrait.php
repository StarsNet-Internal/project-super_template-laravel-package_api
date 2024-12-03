<?php

namespace StarsNet\Project\EnjoyFace\App\Traits\Controller;

// Default

use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

trait ProjectOrderTrait
{
    private function getAllOrders()
    {
        $orders = Order::all()->toArray();
        return $orders;
    }

    private function getReceiptNumber(array $order, array $allOrders)
    {
        if ($order['store']['slug'] === 'default-mini-store') {
            $allOrders = array_values(array_filter($allOrders, function ($order) {
                return $order['store']['slug'] === 'default-mini-store';
            }));

            $prefixes = [];
            foreach (range('A', 'Z') as $first) {
                foreach (range('A', 'Z') as $second) {
                    $prefixes[] = $first . $second;
                }
            }

            // Generate the final strings
            // $generatedStrings = [];
            $index = array_search($order['_id'], array_map(function ($order) {
                return $order['_id'];
            }, $allOrders));

            $prefixIndex = intdiv($index, 9999); // Determine the prefix index

            $prefix = $prefixes[$prefixIndex];
            $numericPart = str_pad(($index % 9999) + 1, 4, '0', STR_PAD_LEFT);

            return $prefix . $numericPart;

            // return substr($order['_id'], -6);
        }

        $orders = array_filter($allOrders, function ($order) {
            return $order['store']['slug'] !== 'default-mini-store';
        });

        $createdAt = Carbon::parse($order['created_at']);
        $startOfDay = $createdAt->copy()->setTimezone('Asia/Hong_Kong')->startOfDay();

        $filteredOrders = array_filter($orders, function ($order) use ($createdAt, $startOfDay) {
            return $startOfDay <= Carbon::parse($order['created_at']) && Carbon::parse($order['created_at']) < $createdAt;
        });

        $datePart = $createdAt->format('Ymd');
        $orderNumber = str_pad(count($filteredOrders) + 1, 4, '0', STR_PAD_LEFT);
        $receiptNumber = $datePart . $orderNumber;

        return $receiptNumber;
    }
}
