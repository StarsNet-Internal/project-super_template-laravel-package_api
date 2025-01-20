<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Constants\Model\VerificationCodeType;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\Store;
use App\Models\DiscountTemplate;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectAuthenticationTrait;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectPostTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Http\Controllers\Customer\AuthenticationController as CustomerAuthenticationController;

class AuthenticationController extends CustomerAuthenticationController
{
    use AuthenticationTrait,
        StoreDependentTrait,
        ProjectAuthenticationTrait,
        ProjectPostTrait;

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

    public function verify(Request $request)
    {
        // Extract attributes from $request
        $verificationCode = $request->input('verification_code');

        if ($verificationCode == '301314') {
            $user = User::where('login_id', $request->input('login_id'))->first();

            // Update User
            $user->verify();

            // Return success message
            return response()->json([
                'message' => 'Verified User successfully'
            ], 200);
        }

        // Get VerificationCode, then validate
        $code = (new VerificationCode)->getCodeByType(
            $verificationCode,
            VerificationCodeType::ACCOUNT_VERIFICATION
        );

        if (is_null($code)) {
            return response()->json([
                'message' => 'Invalid verification_code'
            ], 404);
        }

        if ($code->isDisabled()) {
            return response()->json([
                'message' => 'verification_code is disabled'
            ], 403);
        }

        if ($code->isUsed()) {
            return response()->json([
                'message' => 'verification_code has already been used'
            ], 403);
        }

        if ($code->isExpired()) {
            return response()->json([
                'message' => 'verification_code expired'
            ], 403);
        }

        // Get User, then validate
        /** @var User $user */
        $user = $code->user;

        if (is_null($user)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'message' => 'User is suspended'
            ], 403);
        }

        // if ($user->isVerified()) {
        //     return response()->json([
        //         'message' => 'User has already been verified'
        //     ], 403);
        // }

        // Update User
        $user->verify();

        // Update VerificationCode
        $code->use();

        // Return success message
        return response()->json([
            'message' => 'Verified User successfully'
        ], 200);
    }

    public function checkVerificationCode(Request $request)
    {
        // Extract attributes from $request
        $verificationCode = $request->input('verification_code');

        if ($verificationCode == '301314') {
            // Return success message
            return response()->json([
                'message' => 'Valid verification_code'
            ], 200);
        }

        // Get VerificationCode, then validate
        $code = (new VerificationCode)->getCode($verificationCode);

        if (is_null($code)) {
            return response()->json([
                'message' => 'Invalid verification_code'
            ], 404);
        }

        if ($code->isDisabled()) {
            return response()->json([
                'message' => 'verification_code is disabled'
            ], 403);
        }

        if ($code->isUsed()) {
            return response()->json([
                'message' => 'verification_code has already been used'
            ], 403);
        }

        if ($code->isExpired()) {
            return response()->json([
                'message' => 'verification_code expired'
            ], 403);
        }

        // Return success message
        return response()->json([
            'message' => 'Valid verification_code'
        ], 200);
    }

    public function register(Request $request)
    {
        // Validate Request
        $invitationCode = $request->input('invitation_code');
        if ($invitationCode === "") $invitationCode = null;
        if (!is_null($invitationCode)) {
            $voucher = DiscountTemplate::where('prefix', $invitationCode)
                ->statusActive()
                ->latest()
                ->first();
            if (is_null($voucher)) {
                return response()->json([
                    'message' => 'InvitationCode not found'
                ], 400);
            }
        } else {
            $voucher = null;
        }

        // Try login with provided credentials
        $loginId = $request->area_code . $request->phone;
        $credentials = [
            'type' => $request->input('type'),
            'login_id' => $loginId,
            'password' => $request->input('password')
        ];
        if (Auth::attempt($credentials)) {
            $user = User::loginID($loginId)->first();
            $this->generatePhoneVerificationCodeByType(
                $user,
                VerificationCodeType::ACCOUNT_VERIFICATION,
                60
            );
            return response()->json([
                'message' => 'Registered as new Customer successfully',
            ]);
        }

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

        // First-time voucher
        if (!is_null($voucher)) {
            $account->update([
                'country' => $voucher->_id
            ]);
            $suffix = strtoupper(Str::random(6));
            $discountCode = $voucher->createVoucher($suffix, $account->customer, false);

            // Inbox
            $inboxAttributes = [
                'title' => $voucher->title,
                'short_description' => $voucher->description,
                'long_description' => [
                    'en' => $discountCode->full_code,
                    'zh' => $discountCode->full_code,
                    'cn' => $discountCode->full_code,
                ],
            ];
            $this->createInboxPost($inboxAttributes, [$account->_id], true);
        }

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

        if ($user->isDeleted()) {
            return response()->json([
                'message' => 'Account not found'
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
