<?php

namespace StarsNet\Project\Splitwise\App\Traits\Controller;

use App\Models\Customer;
use App\Models\MembershipPoint;
use Carbon\Carbon;

trait ProjectCustomerTrait
{
    private function addOrDeductCredit(Customer $customer, int $points, string $type)
    {
        $expiresAt = Carbon::now()->addCenturies(5);
        // if ($points > 0) {
        return MembershipPoint::createByCustomer($customer, $points, $type, $expiresAt);
        // } else {
        //     return $customer->deductMembershipPoints(abs($points));
        // }
    }
}
