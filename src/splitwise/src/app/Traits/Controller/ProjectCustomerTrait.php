<?php

namespace StarsNet\Project\Splitwise\App\Traits\Controller;

use App\Models\Customer;
use App\Models\MembershipPoint;
use Carbon\Carbon;

trait ProjectCustomerTrait
{
    private function addOrDeductCredit(Customer $customer, int $points)
    {
        $expiresAt = Carbon::now()->addCenturies(5);
        if ($points > 0) {
            return MembershipPoint::createByCustomer($customer, $points, 'OTHERS', $expiresAt);
        } else {
            return $customer->deductMembershipPoints(abs($points));
        }
    }
}
