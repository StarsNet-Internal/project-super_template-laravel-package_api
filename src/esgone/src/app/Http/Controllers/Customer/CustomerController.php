<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Models\Customer;

class CustomerController extends Controller
{
    public function getAllCustomers()
    {
        // Define keys for append
        $appendKeys = [
            'user',
            'country',
            'gender',
            'last_logged_in_at',
            'email',
            'area_code',
            'phone'
        ];

        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::whereIsDeleted(false)
            ->get()
            ->append($appendKeys);

        // Return Customer(s)
        return $customers;
    }
}
