<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Admin;

use App\Constants\Model\LoginType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function getAllCustomers(Request $request)
    {
        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::whereIsDeleted(false)
            ->whereHas('account', function ($query) {
                $query->whereHas('user', function ($query2) {
                    $query2->where('type', '!=', LoginType::TEMP);
                });
            })
            ->with([
                'account',
                'account.user'
            ])
            ->get();

        // Return Customer(s)
        return $customers;
    }

    public function getCustomerDetails(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        // Get Customer, then validate
        /** @var Customer $customer */
        $customer = Customer::with([
            'account',
            'account.user',
            'account.notificationSetting'
        ])
            ->find($customerID);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Return Customer
        return response()->json($customer, 200);
    }
}
