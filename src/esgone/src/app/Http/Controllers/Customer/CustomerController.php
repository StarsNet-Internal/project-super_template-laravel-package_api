<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Models\Customer;
use App\Models\CustomerGroup;

class CustomerController extends Controller
{
    public function getAllCustomers()
    {
        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::whereHas('account', function ($query) {
            $query->whereHas('user', function ($query2) {
                $query2->where('type', '!=', LoginType::TEMP)->where('is_staff', false);
            });
        })->with([
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
            $industries = array_filter($customer['groups'], function ($group) {
                return $group['slug'] === null;
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
            $customer['company_name'] = $customer['account']['company_name'] ?? null;
            // Tree
            $customer['website'] = $customer['account']['website'] ?? null;
            $customer['short_description'] = $customer['account']['short_description'] ?? null;
            $customer['member_level'] = reset($memberLevel)['slug'];
            $customer['industries'] = array_map(function ($industry) {
                return [
                    '_id' => $industry['_id'],
                    'title' => $industry['title'],
                ];
            }, $industries);

            unset($customer['account'], $customer['groups']);
            return $customer;
        }, $customers->toArray());

        // Return Customer(s)
        return $customers;
    }

    public function getAllCustomerGroups(Request $request)
    {
        $groups = CustomerGroup::whereItemType('Customer')
            ->statusActive()
            ->where('is_system', false)
            ->whereNull('slug')
            ->get();

        return $groups;
    }
}
