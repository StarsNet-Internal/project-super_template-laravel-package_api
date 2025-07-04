<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Warehouse;
use App\Traits\Controller\AuthenticationTrait;
use App\Constants\Model\VerificationCodeType;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

use App\Events\Customer\Authentication\CustomerLogin;
use App\Models\User;

use App\Events\Customer\Authentication\CustomerRegistration;
use App\Models\VerificationCode;
use StarsNet\Project\Paraqon\App\Models\Notification;

class AuthenticationController extends Controller
{
    use AuthenticationTrait;

    public function login(Request $request)
    {
        // Declare local constants
        $roleName = 'customer';

        // Extract attributes from $request
        $loginType = $request->input('type', LoginType::EMAIL);
        $loginType = strtoupper($loginType);

        // Attempt to find User via Account Model
        $user = $this->findUserByCredentials(
            $loginType,
            $request->email,
            $request->area_code,
            $request->phone,
        );

        if (is_null($user)) {
            return response()->json([
                'message' => 'Credentials are not valid.'
            ], 404);
        }

        // Get login_id from found user
        $credentials =
            [
                'login_id' => $user->login_id,
                'password' => $request->password
            ];

        // Check if too many failed login attempts
        $account = $user->account;

        if (
            isset($account->failed_login_count)
            && $account->failed_login_count >= 5
        ) {
            return response()->json([
                'message' => 'Too many failed attempts. Your account has been temporarily locked for security reasons.'
            ], 423);
        }

        if (!Auth::attempt($credentials)) {
            // For incorrect password, increment failed_login_count on account
            $newFailedLoginCount = $account->failed_login_count + 1;
            $account->update(['failed_login_count' => $newFailedLoginCount]);

            return response()->json([
                'message' => 'Credentials are not valid.'
            ], 404);
        }

        // Get User, then validate
        $user = $this->user();

        if ($user->isDeleted()) {
            return response()->json([
                'message' => 'Account not found'
            ], 403);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'message' => 'This account is disabled'
            ], 403);
        }

        if ($user->isStaff()) {
            return response()->json([
                'message' => 'This account is a staff account'
            ], 403);
        }

        // Disable all old LOGIN type VerificationCode
        $user->verificationCodes()
            ->where('type', 'LOGIN')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Generate new VerificationCode
        $code = $this->generateVerificationCodeByType(
            'LOGIN',
            15,
            $user,
            $loginType
        );

        // Clear failed login count
        $account->update(['failed_login_count' => 0]);

        // Check is_2fa_verification_required
        $is2faVerificationRequired = $account->is_2fa_verification_required ?? true;

        // Return response
        return response()->json([
            'message' => 'Login credentials are valid, we have sent you a 2FA verification code',
            'code' => $code,
            'is_2fa_verification_required' => $is2faVerificationRequired
        ], 200);
    }

    public function twoFactorAuthenticationlogin(Request $request)
    {
        // Declare local constants
        $roleName = 'customer';

        // Extract attributes from $request
        $loginType = $request->input('type', LoginType::EMAIL);
        $loginType = strtoupper($loginType);
        $code = $request->code;

        // Attempt to find User via Account Model
        $user = $this->findUserByCredentials(
            $loginType,
            $request->email,
            $request->area_code,
            $request->phone,
        );

        if (is_null($user)) {
            return response()->json([
                'message' => 'Credentials are not valid.'
            ], 404);
        }

        // Get login_id from found user
        $credentials =
            [
                'login_id' => $user->login_id,
                'password' => $request->password
            ];

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Credentials are not valid.'
            ], 404);
        }

        // Get User, then validate
        $user = $this->user();

        if ($user->isDeleted()) {
            return response()->json([
                'message' => 'Account not found'
            ], 403);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'message' => 'This account is disabled'
            ], 403);
        }

        if ($user->isStaff()) {
            return response()->json([
                'message' => 'This account is a staff account'
            ], 403);
        }

        // Find VerificationCode
        $verificationCode = $user->verificationCodes()
            ->where('type', 'LOGIN')
            ->where('code', $code)
            ->orderBy('_id', 'desc')
            ->first();

        if (is_null($verificationCode)) {
            return response()->json([
                'message' => 'Invalid Verfication Code'
            ], 404);
        }

        if ($verificationCode->isDisabled()) {
            return response()->json([
                'message' => 'Verfication Code is disabled'
            ], 403);
        }

        if ($verificationCode->isUsed()) {
            return response()->json([
                'message' => 'Verfication Code has already been used'
            ], 403);
        }

        if ($verificationCode->isExpired()) {
            return response()->json([
                'message' => 'Verfication Code expired'
            ], 403);
        }

        if ($verificationCode->code != $code) {
            return response()->json([
                'message' => 'Verfication Code is incorrect'
            ], 403);
        }

        // Update VerificationCode
        $verificationCode->use();

        // Create token
        $accessToken = $user->createToken($roleName)->accessToken;

        // Fire event
        event(new CustomerLogin($user));

        // Return data
        $data = [
            'token' => $accessToken,
            'user' => $user
        ];

        return response()->json($data, 200);
    }

    public function migrateToRegistered(Request $request)
    {
        // Get User, then validate
        $user = $this->user();

        if (!$user->isTypeTemp()) {
            return response()->json([
                'message' => 'This User does not have permission',
            ], 401);
        }

        // Find if user exists
        $ifAccountExists = Account::where('email', $request->email)
            ->exists();

        if ($ifAccountExists) {
            return response()->json([
                'message' => 'This email address has already been taken: ' . $request->email,
            ], 401);
        }

        $ifAccountExists = Account::where('area_code', $request->area_code)
            ->where('phone', $request->phone)
            ->exists();

        if ($ifAccountExists) {
            return response()->json([
                'message' => 'This phone has already been taken: +' . $request->area_code . ' ' . $request->phone,
            ], 401);
        }

        // Override request value
        $request->merge([
            'type' => LoginType::EMAIL,
        ]);

        // Update User
        $this->updateUserViaRegistration($user, $request);
        // $user->generateVerificationCodeByType(
        //     VerificationCodeType::ACCOUNT_VERIFICATION,
        //     60
        // );

        // Update Account
        /** @var ?Account $account */
        $account = $user->account;
        if ($account instanceof Account) {
            $this->updateAccountViaRegistration($account, $request);
        }

        // Update User, then update Account
        $userUpdateAttributes = [
            'login_id' => $request->email
        ];
        $user->update($userUpdateAttributes);

        $accountUpdateAttributes = [
            'email' => $request->email,
            'area_code' => $request->area_code,
            'phone' => $request->phone,
            'source' => $request->source,

            // New key for Account Type
            'account_type' => $request->input('account_type', "INDIVIDUAL"),
            'company_name' => $request->input('company_name'),
            'business_registration_number' => $request->input('business_registration_number'),
            'company_address' => $request->input('company_address'),
            'business_registration_verification' => $request->input('business_registration_verification'),
            'registrar_of_shareholders_verification' => $request->input('registrar_of_shareholders_verification'),

            // Account Verification
            'address_proof_verification' => $request->input('address_proof_verification'),
            'photo_id_verification' => $request->input('photo_id_verification'),
            'legal_name_verification' => $request->input('legal_name_verification'),

            // Boolean
            'is_2fa_verification_required' => $request->input('is_2fa_verification_required'),

            // Admin Created Accounts
            'is_created_by_admin' => $request->input('is_created_by_admin', false),
            'is_default_password_changed' => $request->input('is_default_password_changed', false)
        ];
        $account->update($accountUpdateAttributes);

        // Update Notification Settings
        $setting = $account->notificationSetting;

        $notificationChannels = ["EMAIL", "SMS"];
        switch ($request->area_code) {
            case '852': {
                    $notificationChannels = ["EMAIL"];
                    break;
                }
            case '86':
            default: {
                    $notificationChannels = ["EMAIL"];
                    break;
                }
        }

        $setting->update([
            "channels" => $notificationChannels,
            "language" => "EN",
            "is_accept" => [
                "marketing_info" => true,
                "delivery_update" => true,
                "wishlist_product_update" => true,
                "special_offers" => true,
                "auction_notifications" => true,
                "bid_notifications" => true,
                "monthly_newsletter" => true,
                "sales_support" => true
            ],
            "is_notifiable" => true,
        ]);

        // Return success message
        return response()->json([
            'message' => 'Registered as new Customer successfully',
            'id' => $user->id,
            'warehouse_id' => null
        ], 200);
    }

    public function changeEmailRequest()
    {
        // Get User, then validate
        $user = $this->user();

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

        // Get Account, then validate
        $account = $this->account();

        if (is_null($account)) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        // Get email (address), then validate
        /** @var ?string $email */
        $email = $account->email;

        if (is_null($email)) {
            return response()->json([
                'message' => 'Email not found'
            ], 404);
        }

        // Generate new VerificationCode
        $code = $this->generateVerificationCodeByType(
            VerificationCodeType::CHANGE_EMAIL,
            15,
            $user,
            'EMAIL'
        );

        // Return success message
        return response()->json([
            'message' => 'Email sent to ' . $email,
        ]);
    }

    public function changePhoneRequest()
    {
        // Get User, then validate
        $user = $this->user();

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

        // Get Account, then validate
        $account = $this->account();

        if (is_null($account)) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        // Get phone info, then validate
        $areaCode = $account->area_code;
        $phone = $account->phone;

        if (is_null($areaCode) || is_null($phone)) {
            return response()->json([
                'message' => 'Phone not found'
            ], 404);
        }

        // Generate new VerificationCode
        $code = $this->generateVerificationCodeByType(
            VerificationCodeType::CHANGE_PHONE,
            15,
            $user,
            'PHONE'
        );

        // Return success message
        return response()->json([
            'message' => 'SMS sent to +' . $areaCode . ' ' . $phone,
        ]);
    }

    public function changePhone(Request $request)
    {
        // Declare local constants
        $loginType = LoginType::PHONE;

        // Extract attributes from $request
        $verificationCode = $request->input('verification_code');
        $areaCode = $request->input('area_code');
        $phone = $request->input('phone');

        // Get VerificationCode, then validate
        $code = VerificationCode::where(
            'type',
            VerificationCodeType::CHANGE_PHONE
        )
            ->where('code', $verificationCode)
            ->latest()
            ->first();

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
        /** @var ?User $user */
        $user = $code->user;

        if (is_null($user)) {
            return response()->json([
                'message' => 'Invalid verification_code / User not found'
            ], 404);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'message' => 'This account is disabled'
            ], 403);
        }

        // Validate if new credentials are duplicated
        $newLoginID = $areaCode . $phone;
        if ($this->checkIfUserExists($loginType, $newLoginID)) {
            return response()->json([
                'message' => 'Update failed, User/phone already exists',
            ], 403);
        }

        // Update User and Account
        $this->updatePhoneUserCredentials($user, $areaCode, $phone);

        // Update VerificationCode
        $code->use();

        // Return success message
        return response()->json([
            'message' => 'Updated phone successfully',
        ], 200);
    }

    public function forgetPassword(Request $request)
    {
        // Extract attributes from $request
        $loginType = $request->input('type', LoginType::EMAIL);
        $loginType = strtoupper($loginType);

        // Validate Request
        if (!in_array($loginType, [LoginType::EMAIL, LoginType::PHONE])) {
            return response()->json([
                'message' => 'Incorrect type input'
            ], 401);
        }

        if ($loginType == LoginType::EMAIL && is_null($request->email)) {
            return response()->json([
                'message' => 'Missing email'
            ], 401);
        }

        if (
            $loginType == LoginType::PHONE
            && is_null($request->area_code)
            && is_null($request->phone)
        ) {
            return response()->json([
                'message' => 'Missing area_code or phone'
            ], 401);
        }

        // Attempt to find User via Account Model
        $user = $this->findUserByCredentials(
            $loginType,
            $request->email,
            $request->area_code,
            $request->phone,
        );

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
        $code = $this->generateVerificationCodeByType(
            VerificationCodeType::FORGET_PASSWORD,
            30,
            $user,
            $loginType
        );

        // Get Account, then validate
        /** @var ?Account $account */
        $account = $user->account;

        if (is_null($account)) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        // Return response
        switch ($loginType) {
            case LoginType::EMAIL:
                // Get email (address), then validate
                $email = $account->email;

                if (is_null($email)) {
                    return response()->json([
                        'message' => 'Email not found'
                    ], 404);
                }

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

    private function findUserIDByCredentials(
        string $loginType,
        ?string $email,
        ?string $areaCode,
        ?string $phone
    ) {
        switch ($loginType) {
            case LoginType::EMAIL:
            case LoginType::TEMP:
                $account = Account::where('email', $email)
                    ->first();
                return optional($account)->user_id;
            case LoginType::PHONE:
                $account = Account::where('area_code', $areaCode)
                    ->where('phone', $phone)
                    ->first();
                return optional($account)->user_id;
            default:
                return null;
        }
    }

    private function findUserByCredentials(
        string $loginType,
        ?string $email,
        ?string $areaCode,
        ?string $phone
    ) {
        $userID = $this->findUserIDByCredentials(
            $loginType,
            $email,
            $areaCode,
            $phone
        );
        return User::find($userID);
    }

    private function generateVerificationCodeByType(
        string $codeType,
        int $minutesAllowed = 15,
        User $user,
        ?string $notificationType = 'EMAIL'
    ) {
        $code = (string) mt_rand(100000, 999999);;
        $expiryDate = now()->addMinutes($minutesAllowed);

        $verificationCodeAttributes = [
            'type' => $codeType,
            'code' => $code,
            'expires_at' => $expiryDate,
            'notification_type' => $notificationType
        ];
        $user->verificationCodes()
            ->create($verificationCodeAttributes);

        return $code;
    }

    public function getAuthUserInfo()
    {
        // Get User, then validate
        $user = $this->user();

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

        // Get Role 
        $user->role = $user->getRole();

        // Get Unread Message Count
        $account = $this->account();
        $unreadNotificationCount = Notification::where('account_id', $account->_id)
            ->where('is_read', false)
            ->count();
        $user->unread_notification_count = $unreadNotificationCount;

        // Return data
        $data = [
            'user' => $user
        ];

        return response()->json($data, 200);
    }
}
