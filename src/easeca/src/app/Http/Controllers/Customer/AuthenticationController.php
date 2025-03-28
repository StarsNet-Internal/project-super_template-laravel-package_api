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
use App\Models\Store;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\Easeca\App\Traits\Controller\ProjectAuthenticationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Customer\AuthenticationController as CustomerAuthenticationController;

class AuthenticationController extends CustomerAuthenticationController
{
    use AuthenticationTrait, StoreDependentTrait, ProjectAuthenticationTrait;

    public function generatePhoneVerificationCodeByType(User $user, string $type, int $minutesAllowed = 60): VerificationCode
    {
        // Create VerificationCode
        $attributes = [
            'type' => $type,
            'code' => str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT),
        ];

        /** @var VerificationCode $code */
        $code = $user->verificationCodes()->create($attributes);

        // Update $expiryDate
        $expiryDate = now()->addMinutes($minutesAllowed);
        $code->setExpiresAt($expiryDate);

        return $code;
    }

    public function login(Request $request)
    {
        $response = parent::login($request);
        $data = json_decode(json_encode($response), true)['original'];

        $account = $this->account();
        if ($account->store_id != null) {
            $store = $this->getStoreByValue($account->store_id);
            $data['user']['account']['country'] =
                $store->is_system === true ? 'default-main-store' : $store->remarks;
        }
        if (isset($request->fcm_token) && $request->fcm_token != '') {
            $tokens = $account->fcm_tokens ?? [];
            array_push($tokens, $request->fcm_token);
            $account->update([
                'fcm_tokens' => $tokens
            ]);
        }

        // Return success message
        return response()->json($data, $response->getStatusCode());
    }

    public function logoutMobileDevice(Request $request)
    {
        if (isset($request->fcm_token) && $request->fcm_token != '') {
            $tokenToRemoved = $request->fcm_token;
            $account = $this->account();
            $tokens = array_filter($account->fcm_tokens, function ($token) use ($tokenToRemoved) {
                return $token != $tokenToRemoved;
            });
            $account->update([
                'fcm_tokens' => array_values($tokens)
            ]);
        }

        $response = parent::logout();
        $data = json_decode(json_encode($response), true)['original'];

        // Return success message
        return response()->json($data, $response->getStatusCode());
    }

    public function getAuthUserInfo()
    {
        $response = parent::getAuthUserInfo();
        $data = json_decode(json_encode($response), true)['original'];

        $account = $this->account();
        if ($account->store_id != null) {
            $store = $this->getStoreByValue($account->store_id);
            $data['user']['account']['country'] =
                $store->is_system === true ? 'default-main-store' : $store->remarks;
        }

        // Return success message
        return response()->json($data, $response->getStatusCode());
    }

    public function register(Request $request)
    {
        // Generate a new customer-identity Account
        $user = $this->createNewUserAsCustomer($request);
        $user->setAsCustomerAccount();

        // Update User
        $this->updateUserViaRegistration($user, $request);
        $this->generatePhoneVerificationCodeByType(
            $user,
            VerificationCodeType::ACCOUNT_VERIFICATION,
            60
        );

        // Update Account
        $account = $user->account;
        $this->updateAccountViaRegistration($account, $request);

        // Package
        $customer = $account->customer;
        $store = $this->getStoreByValue($request->store_id);
        $account->update([
            'store_id' => $request->store_id,
            'is_approved' => $store->is_system === true ? true : false,
        ]);
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
            'message' => 'Registered as new Customer successfully',
        ]);
    }

    public function forgetPassword(Request $request)
    {
        // Extract attributes from $request
        $loginType = $request->input('type', LoginType::EMAIL);

        // Get loginID from $request, then validate
        switch ($loginType) {
            case LoginType::EMAIL:
                $loginID = $request->email;
                break;
            case LoginType::PHONE:
                $loginID = $request->area_code . $request->phone;
                break;
            default:
                return response()->json([
                    'message' => 'Incorrect type input'
                ], 401);
        }

        if (is_null($loginID)) {
            return response()->json([
                'message' => 'login_id not found'
            ], 404);
        }

        // Get User, then validate
        $user = (new User)->getUserByTypeAndLoginID($loginType, $loginID);

        if (is_null($user)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'message' => 'This account is disabled'
            ], 403);
        }

        // Create VerificationCode
        $verificationCode = $this->generatePhoneVerificationCodeByType(
            $user,
            VerificationCodeType::FORGET_PASSWORD,
            30
        );

        // Get Account, then validate
        /** @var ?Account $account */
        $account = $user->account;

        if (is_null($account)) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        switch ($loginType) {
            case LoginType::EMAIL:
                // Get email (address), then validate
                $email = $account->email;

                if (is_null($email)) {
                    return response()->json([
                        'message' => 'Email not found'
                    ], 404);
                }

                // TODO: Send email

                // Return success message
                return response()->json([
                    'message' => 'Email sent to ' . $email,
                ], 200);
            case LoginType::PHONE:
                // Get phone info, then validate
                $areaCode = $account->area_code;
                $phone = $account->phone;

                if (is_null($areaCode) || is_null($phone)) {
                    return response()->json([
                        'message' => 'Phone not found'
                    ], 404);
                }

                // TODO: Send SMS

                // Return success message
                return response()->json([
                    'message' => 'SMS sent to +' . $areaCode . ' ' . $phone,
                ], 200);
            default:
                // Default error message
                return response()->json([
                    'message' => 'Incorrect type input'
                ], 401);
        }
    }

    public function getVerificationCode(Request $request)
    {
        $this->generatePhoneVerificationCodeByType(
            $this->user(),
            VerificationCodeType::ACCOUNT_VERIFICATION,
            60
        );
        return response()->json([
            'message' => 'Generated new VerificationCode successfully',
        ]);
    }

    public function deleteAccount(Request $request)
    {
        // Get User, then validate
        $user = $this->user();

        if (is_null($user) || $user->isDeleted()) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Delete User
        $user->softDeletes();

        $this->updateLoginIdOnDelete($user);

        // Logout
        $user->token()->revoke();

        // Return success message
        return response()->json([
            'message' => 'Account scheduled to be deleted successfully'
        ], 200);
    }
}
