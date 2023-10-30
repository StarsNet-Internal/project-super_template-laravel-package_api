<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Constants\Model\VerificationCodeType;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Models\VerificationCode;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Customer\AuthenticationController as CustomerAuthenticationController;

class AuthenticationController extends CustomerAuthenticationController
{
    use AuthenticationTrait;

    public function getAuthUserInfo()
    {
        $response = parent::getAuthUserInfo();
        $data = json_decode(json_encode($response), true)['original'];

        // Return success message
        return response()->json($data);
    }

    public function register(Request $request)
    {
        // Generate a new customer-identity Account
        $user = $this->createNewUserAsCustomer($request);
        $user->setAsCustomerAccount();

        // Fire event
        event(new CustomerRegistration($user, $request));

        $account = $user->account();
        $customer = $account->customer();
        $account->update([
            'store_id' => $request->store_id,
        ]);
        $customer->update([
            'delivery_recipient' => [
                'name' => $request->username,
                'address' => $request->address,
                'area_code' => $request->area_phone,
                'phone' => $request->phone,
            ]
        ]);

        // Return success message
        return response()->json([
            'message' => 'Registered as new Customer successfully',
        ]);
    }
}
