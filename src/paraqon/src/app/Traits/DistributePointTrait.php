<?php

namespace StarsNet\Project\Paraqon\Traits;

// Laravel built-in
use Carbon\Carbon;

// Enums
use App\Constants\Model\MembershipPointHistoryType;

// Models
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\MembershipPoint;
use App\Models\MembershipPointHistory;
use App\Models\Order;

trait DistributePointTrait
{
    protected function processMembershipPoints(Customer $customer, Order $order): void
    {
        $points = $this->calculatePoints($order);
        if ($points <= 0) return;

        $expiryDate = $this->getPointsExpiryDate();
        $this->createMembershipPoint($customer, $order, $points, $expiryDate);
        $this->createMembershipPointHistory($customer, $order, $points, $expiryDate);
        return;
    }

    protected function getPointRatioConfig(): Configuration
    {
        return Configuration::where('slug', 'membership-point-conversion-rate')->latest()->first();
    }

    protected function getPointExpiryDateConfig(): Configuration
    {
        return Configuration::where('slug', 'membership-point-valid-duration')->latest()->first();
    }

    protected function getPointsExpiryDate(): Carbon
    {
        $config = $this->getPointExpiryDateConfig();
        if (!$config) return now()->endOfCentury();

        switch ($config->unit) {
            case 'days':
                return now()->addDays($config->value);
            case 'weeks':
                return now()->addWeeks($config->value);
            case 'months':
                return now()->addMonths($config->value);
            case 'years':
                return now()->addYears($config->value);
            default:
                return now()->addDays($config->value);
        }
    }

    protected function calculatePoints(Order $order): int
    {
        $pointRatioConfig = $this->getPointRatioConfig();
        if (!$pointRatioConfig) return 0;
        if (is_null($pointRatioConfig->value) || $pointRatioConfig->value === 0) return 0;

        $totalPrice = $order['calculations']['price']['total'];
        if ($totalPrice == 0) return 0;
        return intval($totalPrice / $pointRatioConfig->value);
    }

    protected function createMembershipPoint(
        Customer $customer,
        Order $order,
        int $points,
        Carbon $expiryDate
    ) {
        return MembershipPoint::create([
            'customer_id' => $customer->id,
            'earned' => $points,
            'used' => 0,
            'description' => [
                'en' => "Points gained from Order (id: $order->id)",
                'zh' => "Points gained from Order (id: $order->id)",
                'ch' => "Points gained from Order (id: $order->id)"
            ],
            'remarks' => "Points gained from Order (id: $order->id)",
            'expires_at' => $expiryDate,
        ]);
    }

    protected function createMembershipPointHistory(
        Customer $customer,
        Order $order,
        int $points,
        Carbon $expiryDate
    ) {
        return MembershipPointHistory::create([
            'customer_id' => $customer->id,
            'type' => MembershipPointHistoryType::PURCHASE,
            'value' => $points,
            'description' => $this->getPointDescription($order),
            'remarks' => "Points gained from Order (id: $order->id)",
            'expires_at' => $expiryDate,
        ]);
    }
}
