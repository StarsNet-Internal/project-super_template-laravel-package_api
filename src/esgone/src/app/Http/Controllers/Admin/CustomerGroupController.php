<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Admin;

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
            ->whereHas('account', function ($query) {
                $query->whereHas('user', function ($query2) {
                    $query2->where('is_staff', false);
                });
            })
            ->with([
                'account',
            ])
            ->get();

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

            unset($customer['account']);
            return $customer;
        }, $customers->toArray());

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
                    $query2->where('is_staff', false);
                });
            })
            ->with([
                'account',
            ])
            ->get();

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

            unset($customer['account']);
            return $customer;
        }, $customers->toArray());

        // Return success message
        return $customers;
    }
}
