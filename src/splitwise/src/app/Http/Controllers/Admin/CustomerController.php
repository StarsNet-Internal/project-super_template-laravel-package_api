<?php

namespace StarsNet\Project\Splitwise\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Utils 
use Illuminate\Support\Collection;

// Constants
use App\Constants\Model\LoginType;
use App\Constants\Model\StoreType;

// Models
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;

// Traits
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\Splitwise\App\Traits\Controller\ProjectCustomerTrait;

class CustomerController extends Controller
{
    use AuthenticationTrait,
        StoreDependentTrait,
        ProjectCustomerTrait;

    protected $model = Customer::class;

    public function deleteCustomers(Request $request)
    {
        // Extract attributes from $request
        $customerIDs = $request->input('ids');

        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::find($customerIDs);

        /** @var Customer $customer */
        foreach ($customers as $customer) {
            // Get User, then softDeletes
            $user = $customer->getUser();
            $user->softDeletes();
            $user->disable();
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $customers->count() . ' Customer(s) successfully'
        ], 200);
    }

    public function getMembershipPointBalance(Request $request)
    {
        // Extract attributes from $request
        $customer = $this->customer();

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Get MembershipPoint(s)
        /** @var Collection $points */
        $points = $customer->membershipPoints;

        // Return MembershipPoint(s)
        return $points;
    }

    public function addCreditToAccount(Request $request)
    {
        // Extract attributes from $request
        $points = $request->points;
        $type = $request->type;

        $customer = $this->customer();

        $this->addOrDeductCredit($customer, $points, $type);

        // Return success message
        return response()->json([
            'message' => 'Updated Credit successfully'
        ], 200);
    }
}
