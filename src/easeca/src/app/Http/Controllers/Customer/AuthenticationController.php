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
        $response = parent::register($request);
        $register = json_decode(json_encode($response), true)['original'];

        $account = $this->account();
        $account->update([
            'store_id' => $request->store_id,
        ]);

        // Return success message
        return response()->json($register);
    }
}
