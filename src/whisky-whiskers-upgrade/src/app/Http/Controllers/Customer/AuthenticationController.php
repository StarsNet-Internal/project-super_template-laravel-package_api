<?php

namespace StarsNet\Project\WhiskyWhiskersUpgrade\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Traits\Controller\AuthenticationTrait;

use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    use AuthenticationTrait;

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

            // Admin Created Accounts
            'is_created_by_admin' => $request->input('is_created_by_admin', false),
            'is_default_password_changed' => $request->input('is_default_password_changed', false)
        ];
        $account->update($accountUpdateAttributes);

        // Update Notification Settings
        $setting = $account->notificationSetting;

        $notificationChannels = ["EMAIL"];
        // switch ($request->area_code) {
        //     case '852': {
        //             $notificationChannels = ["EMAIL", "SMS"];
        //             break;
        //         }
        //     case '86':
        //     default: {
        //             $notificationChannels = ["EMAIL"];
        //             break;
        //         }
        // }

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
}
