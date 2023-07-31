<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

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

class CustomerController extends Controller
{
    use AuthenticationTrait,
        StoreDependentTrait;

    protected $model = Customer::class;

    public function getCustomerDetails(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        // Define keys for append
        $appendKeys =  [
            'user',
            'country',
            'gender',
            'last_logged_in_at',
            'email',
            'area_code',
            'phone',
            'order_statistics'
        ];

        // Get Customer, then validate
        /** @var Customer $customer */
        $customer = Customer::find($customerID)->append($appendKeys);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        $data = array_merge($customer->toArray(), [
            'birthday' => $customer->account->birthday
        ]);

        // Return Customer
        return response()->json($data, 200);
    }
}
