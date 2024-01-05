<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Models\Customer;

class CustomerController extends Controller
{
    public function getAllCustomers()
    {
        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::with([
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
}
