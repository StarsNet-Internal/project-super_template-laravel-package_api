<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Traits\Controller\AuthenticationTrait;

use Illuminate\Http\Request;
use Carbon\Carbon;

use StarsNet\Project\Auction\App\Models\ReferralCode;
use StarsNet\Project\Auction\App\Models\ReferralCodeHistory;

class AuthenticationController extends Controller
{
    use AuthenticationTrait;

    public function migrateToRegistered(Request $request)
    {
        // Validate if referral_code is filled in
        $now = now()->addHours(8);
        $user = $this->user();
        $customer = $this->customer();

        $inputCode = $request->referral_code;
        if (!is_null($inputCode)) {
            $cutoffDate = Carbon::create(2025, 8, 31)->endOfDay();
            if ($now->gt($cutoffDate)) {
                return response()->json([
                    'message' => 'referral_code expired',
                ], 400);
            }

            $referralCodeDetails = ReferralCode::where('code', $inputCode)->latest()->first();
            if (is_null($referralCodeDetails)) {
                return response()->json([
                    'message' => 'Invalid referral_code of ' . $inputCode,
                ], 404);
            }

            if ($referralCodeDetails->is_deleted === true) {
                return response()->json([
                    'message' => 'Invalid referral_code of ' . $inputCode,
                ], 403);
            }

            if ($referralCodeDetails->is_disabled === true) {
                return response()->json([
                    'message' => 'referral_code of ' . $inputCode . ' is disabled',
                ], 403);
            }

            $quotaLeft = $referralCodeDetails->quota_left;
            if ($quotaLeft <= 0) {
                return response()->json([
                    'message' => 'Referral code has no more quota',
                ], 403);
            }

            if ($referralCodeDetails->customer_id === $customer->id) {
                return response()->json([
                    'message' => 'You cannot use your own referral_code',
                ], 400);
            }
        }

        // Get User, then validate
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
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),

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
            case '852':
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

        // Update referral_code logic
        $referralCodeDetails = ReferralCode::where('code', $inputCode)->latest()->first();
        if (!is_null($referralCodeDetails)) {
            // Deduct 1 quota
            $referralCodeDetails->update(['quota_left' => $referralCodeDetails->quota_left - 1]);
            // Create new ReferralCodeHistory
            ReferralCodeHistory::create([
                'owned_by_customer_id' => $referralCodeDetails->customer_id,
                'used_by_customer_id' => $customer->id,
                'referral_code_id' => $referralCodeDetails->id,
                'code' => $referralCodeDetails->code,
            ]);
        }

        // Create ReferralCode
        $existingCodes = ReferralCode::pluck('code')->toArray();
        do {
            $code = $this->generateUniqueCode(8, $existingCodes);
        } while (in_array($code, $existingCodes));
        ReferralCode::create([
            'customer_id' => $customer->id,
            'code' => $code,
            'quota_left' => 3
        ]);

        // Return success message
        return response()->json([
            'message' => 'Registered as new Customer successfully',
            'id' => $user->id,
            'warehouse_id' => null
        ], 200);
    }


    protected function generateUniqueCode(int $length, array $existingCodes): string
    {
        $characters = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ'; // No zero
        $max = strlen($characters) - 1;
        $code = '';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, $max)];
            }
        } while (in_array($code, $existingCodes));

        return $code;
    }
}
