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
use StarsNet\Project\Easeca\App\Traits\Controller\ProjectAuthenticationTrait;
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
                'country' => $invitationCode
            ]);
            $suffix = strtoupper(Str::random(6));
            $discountCode = $voucher->createVoucher($suffix, $account->customer, false);

            // Inbox
            $inboxAttributes = [
                'title' => [
                    'en' => 'New User Offer',
                    'zh' => '新用戶優惠',
                    'cn' => '新用户优惠',
                ],
                'short_description' => [
                    'en' => 'Enter the promo code "' . $discountCode->full_code . '" for your first order to enjoy the offer.',
                    'zh' => '首張訂單輸入優惠碼"' . $discountCode->full_code . '"即可享優惠。',
                    'cn' => '首张订单输入优惠码"' . $discountCode->full_code . '"即可享优惠。',
                ],
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
}
