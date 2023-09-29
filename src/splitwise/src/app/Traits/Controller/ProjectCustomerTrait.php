<?php

namespace StarsNet\Project\Splitwise\App\Traits\Controller;

use App\Models\Customer;
use App\Models\MembershipPoint;
use App\Models\MembershipPointHistory;
use Carbon\Carbon;

trait ProjectCustomerTrait
{
    private function addOrDeductCredit(Customer $staff, Customer $customer, int $points, string $type)
    {
        $expiresAt = Carbon::now()->addCenturies(5);
        if ($points === 0) return null;

        // Create MembershipPoint
        $pointAttributes = [
            'earned' => $points,
            'remarks' => $type,
            'expires_at' => $expiresAt,
            'created_by_customer_id' => $customer->_id,
        ];
        $pointAttributes = array_filter($pointAttributes); // Remove all null values
        $point = MembershipPoint::create($pointAttributes);
        $point->associateCustomer($staff);

        // Create MembershipPointHistory
        MembershipPointHistory::createByCustomer(
            $staff,
            $points,
            $type,
            $expiresAt,
            null,
            $customer->_id
        );

        return $point;
    }
}
