<?php

namespace StarsNet\Project\Easeca\App\Traits\Controller;

// Default

use App\Models\Store;
use Carbon\Carbon;

trait ProjectScheduleTrait
{
    private function getCurrentDayOfWeek()
    {
        return strtolower(Carbon::now('Asia/Hong_Kong')->format('l'));
    }

    private function getScheduleByAccount(string $address = '')
    {
        $account = $this->account();
        $customer = $this->customer();

        $store = Store::find($account->store_id);
        if ($address === '') {
            $address = $customer->delivery_recipient['address'];
        }

        if (isset($store) && isset($store->addresses[0])) {
            $schedule = $store->addresses[0];

            foreach ($store->addresses as $item) {
                if (
                    (isset($item['en']) && $item['en'] === $address) ||
                    (isset($item['zh']) && $item['zh'] === $address) ||
                    (isset($item['cn']) && $item['cn'] === $address)
                ) {
                    $schedule = $item;
                    break; // Stop at the first match
                }
            }
        } else {
            $schedule = [
                'monday' => [
                    'hour' => 16,
                    'available' => true
                ],
                'tuesday' => [
                    'hour' => 16,
                    'available' => true
                ],
                'wednesday' => [
                    'hour' => 16,
                    'available' => true
                ],
                'thursday' => [
                    'hour' => 16,
                    'available' => true
                ],
                'friday' => [
                    'hour' => 16,
                    'available' => true
                ],
                'saturday' => [
                    'hour' => 12,
                    'available' => true
                ],
                'sunday' => [
                    'hour' => 9,
                    'available' => false
                ],
                'working_days' => 3,
                'min_order_price' => 8000
            ];
        }

        return $schedule;
    }
}
