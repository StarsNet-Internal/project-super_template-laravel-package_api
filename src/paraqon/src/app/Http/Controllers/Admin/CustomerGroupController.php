<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Customer;
use App\Models\CustomerGroup;

// Constants
use App\Constants\Model\LoginType;

class CustomerGroupController extends Controller
{
    public function getCustomerGroupAssignedCustomers(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('id');

        // Get CustomerGroup, then validate
        /** @var CustomerGroup $category */
        $category = CustomerGroup::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'CustomerGroup not found'
            ], 404);
        }

        // Get Customer
        $customers = $category->customers()
            ->with([
                'account',
                'account.user'
            ])
            ->get();

        // Return Customer(s)
        return $customers;
    }

    public function getCustomerGroupUnassignedCustomers(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('id');

        // Get CustomerGroup, then validate
        /** @var CustomerGroup $category */
        $category = CustomerGroup::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'CustomerGroup not found'
            ], 404);
        }

        // Get Assigned Post(s)
        $assignedCustomerIDs = $category->customers()->pluck('_id')->all();
        /** @var Collection $posts */
        $customers = Customer::excludeIDs($assignedCustomerIDs)
            ->whereHas('account', function ($query) {
                $query->whereHas('user', function ($query2) {
                    $query2->where('type', '!=', LoginType::TEMP);
                });
            })
            ->with([
                'account'
            ])
            ->get();

        // Return success message
        return $customers;
    }
}
