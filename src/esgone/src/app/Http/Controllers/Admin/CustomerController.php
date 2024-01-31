<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Admin;

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
        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::whereIsDeleted(false)
            ->whereHas('account', function ($query) {
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

            $customer['user'] = [
                'username' => $customer['account']['username'],
                'avatar' => $customer['account']['avatar'],
            ];
            $customer['country'] = $customer['account']['country'];
            $customer['gender'] = $customer['account']['gender'];
            $customer['email'] = $customer['account']['email'];
            $customer['area_code'] = $customer['account']['area_code'];
            $customer['phone'] = $customer['account']['phone'];
            // Tree
            $customer['member_level'] = reset($memberLevel) ? reset($memberLevel)['slug'] : null;

            unset($customer['account'], $customer['groups']);
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
        // $customer['company_name'] = $customer['account']['company_name'] ?? null;
        // $customer['website'] = $customer['account']['website'] ?? null;
        // $customer['short_description'] = isset($customer['account']['short_description']) ?
        //     $customer['account']['short_description']
        //     : ['en' => null, 'zh' => null, 'cn' => null];
        // unset($customer['account']);

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

        // Return success message
        return response()->json([
            'message' => 'Created New Customer successfully',
            '_id' => $user->id,
            'customer_id' => optional($account->customer)->_id
        ], 200);
    }

    public function updateCustomerDetails(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        // Get Customer, then validate
        /** @var Customer $customer */
        $customer = Customer::find($customerID);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Get Account, then validate
        /** @var Account $account */
        $account = $customer->account;

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        // Get User, then validate
        /** @var User $user */
        $user = $account->user;

        if (is_null($user)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Check if matching User loginID exists
        $loginID = null;
        switch ($user->type) {
            case LoginType::EMAIL:
                $loginID = $request->email;
                break;
            case LoginType::PHONE:
                $loginID = $request->area_code . $request->phone;
                break;
            default:
                break;
        }

        if (User::where('id', '!=', $user->id)->type($user->type)->loginID($loginID)->exists()) {
            return response()->json([
                'message' => 'loginID has already been taken'
            ], 404);
        }

        // Update Customer
        $deliveryRecipient = $request->input('delivery_recipient');
        if (!is_null($deliveryRecipient)) {
            $customer->updateDeliveryRecipient($deliveryRecipient);
        }

        // Update Account
        $attributes = [
            'username' => $request->username,
            'avatar' => $request->avatar,
            'gender' => $request->gender,
            'country' => $request->country,
        ];
        $attributes = array_filter($attributes);
        $account->update($attributes);
        $account->update($request->account);

        // Update User
        switch ($user->type) {
            case LoginType::EMAIL:
            case LoginType::TEMP:
                $this->updateEmailUserCredentials($user, $request->email);
                break;
            case LoginType::PHONE:
                $this->updatePhoneUserCredentials($user, $request->area_code, $request->phone);
                break;
            default:
                break;
        }

        // Return success message
        return response()->json([
            'message' => 'Updated Customer successfully'
        ], 200);
    }
}
