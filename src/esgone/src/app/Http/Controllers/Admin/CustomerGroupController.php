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
            ->whereIsDeleted(false)
            ->with([
                'account',
                'groups' => function ($group) {
                    $group->where('is_system', false);
                },
            ])
            ->get();

        $customers = array_map(function ($customer) {
            $memberLevel = array_filter($customer['groups'], function ($group) {
                return $group['slug'] !== null;
            });

            $customer['user'] = [
                'username' => $customer['account']['username'],
                'avatar' => $customer['account']['avatar'],
            ];
            $customer['country'] = $customer['account']['country'];
            $customer['gender'] = $customer['account']['gender'];
            $customer['email'] = $customer['account']['email'];
            $customer['area_code'] = $customer['account']['area_code'];
            $customer['phone'] = $customer['account']['phone'];
            $customer['member_level'] = reset($memberLevel) ? reset($memberLevel)['slug'] : null;

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
            ->whereIsDeleted(false)
            ->with([
                'account',
                'groups' => function ($group) {
                    $group->where('is_system', false);
                },
            ])
            ->get();

        $customers = array_map(function ($customer) {
            $memberLevel = array_filter($customer['groups'], function ($group) {
                return $group['slug'] !== null;
            });

            $customer['user'] = [
                'username' => $customer['account']['username'],
                'avatar' => $customer['account']['avatar'],
            ];
            $customer['country'] = $customer['account']['country'];
            $customer['gender'] = $customer['account']['gender'];
            $customer['email'] = $customer['account']['email'];
            $customer['area_code'] = $customer['account']['area_code'];
            $customer['phone'] = $customer['account']['phone'];
            $customer['member_level'] = reset($memberLevel) ? reset($memberLevel)['slug'] : null;

            unset($customer['account']);
            return $customer;
        }, $customers->toArray());

        // Return success message
        return $customers;
    }
}
