<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Constants
use App\Constants\Model\Status;

// Models
use App\Models\Customer;
use App\Models\CustomerGroup;

class CustomerGroupController extends Controller
{
    protected $model = CustomerGroup::class;

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
                'account.role'
            ])
            ->get()
            ->makeVisible(['customer_group_ids']);

        $customers = array_map(function ($customer) {
            $customer['user'] = [
                'username' => $customer['account']['username'],
                'avatar' => $customer['account']['avatar'],
            ];
            $customer['country'] = $customer['account']['country'];
            $customer['gender'] = $customer['account']['gender'];
            $customer['email'] = $customer['account']['email'];
            $customer['area_code'] = $customer['account']['area_code'];
            $customer['phone'] = $customer['account']['phone'];
            $customer['user_id'] = $customer['account']['user_id'];
            $customer['store_ids'] = $customer['account']['store_ids'];
            $customer['role'] = $customer['account']['role'];

            unset($customer['account']);
            return $customer;
        }, $customers->toArray());

        // Return Customer(s)
        return $customers;
    }
}
