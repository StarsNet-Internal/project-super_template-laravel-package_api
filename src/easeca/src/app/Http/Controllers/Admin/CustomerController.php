<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

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

    public function getAllCustomers(Request $request)
    {
        $customers = Customer::whereIsDeleted(false)
            ->with(['account'])
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
            $customer['store_id'] = $customer['account']['store_id'] ?? null;
            $customer['is_approved'] = $customer['account']['is_approved'] ?? null;

            unset($customer['account']);
            return $customer;
        }, $customers->toArray());

        // Return Customer(s)
        return $customers;
    }

    public function getCustomerDetails(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        $customer = Customer::with(['account', 'account.user'])->find($customerID)->append(['order_statistics']);

        $customer['user'] = [
            'username' => $customer['account']['username'],
            'avatar' => $customer['account']['avatar'],
        ];
        $customer['country'] = $customer['account']['country'];
        $customer['gender'] = $customer['account']['gender'];
        $customer['last_logged_in_at'] = $customer['account']['user']['last_logged_in_at'];
        $customer['email'] = $customer['account']['email'];
        $customer['area_code'] = $customer['account']['area_code'];
        $customer['phone'] = $customer['account']['phone'];
        $customer['store_id'] = $customer['account']['store_id'] ?? null;
        $customer['is_approved'] = $customer['account']['is_approved'] ?? null;
        unset($customer['account']);

        // Return Customer
        return response()->json($customer, 200);
    }

    public function createCustomer(Request $request)
    {
        // Extract attributes from $request
        $registrationType = $request->input('type');
        $email = $request->input('email');
        $userName = $request->input('username');

        // Get customer Role, then validate
        /** @var Role $role */
        $role = Role::slug('customer')->first();

        if (is_null($role)) {
            return response()->json([
                'message' => 'Role not found'
            ], 404);
        }

        // Generate a new customer-identity Account
        $user = $this->createNewUserAsCustomer($request);
        $user->setAsCustomerAccount();

        // Update User
        $this->updateUserViaRegistration($user, $request);

        // Update Account
        /** @var ?Account $account */
        $account = $user->account;
        if ($account instanceof Account) {
            $this->updateAccountViaRegistration($account, $request);
        }
        $user->verify();
        // $store = $this->getStoreByValue($request->store_id);
        $account->update([
            'store_id' => $request->store_id,
            'is_approved' => true,
        ]);
        $customer = $account->customer;
        $customer->update([
            'delivery_recipient' => [
                'name' => $request->username,
                'address' => $request->address,
                'area_code' => $request->area_code,
                'phone' => $request->phone,
            ]
        ]);

        // Return success message
        return response()->json([
            'message' => 'Created New Customer successfully',
            '_id' => $user->id,
            'customer_id' => optional($account->customer)->_id
        ], 200);
    }

    public function approveCustomerAccounts(Request $request)
    {
        // Extract attributes from $request
        $customerIDs = $request->input('ids', []);

        $customers = Customer::find($customerIDs);

        // Update customer(s)
        /** @var Customer $customer */
        foreach ($customers as $customer) {
            $customer->account->update([
                'is_approved' => true,
            ]);
        }

        // Return success message
        return response()->json([
            'message' => 'Approved ' . $customers->count() . ' Account(s) successfully'
        ], 200);
    }
}
