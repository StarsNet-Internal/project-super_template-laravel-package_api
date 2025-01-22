<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Traits\Controller\AuthenticationTrait;
use App\Constants\Model\VerificationCodeType;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

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
            // Default
            'email' => $request->email,
            'area_code' => $request->area_code,
            'phone' => $request->phone,
            // For Videocom
            'user_display_id' => $request->user_display_id,
            'password_verification_question' => $request->password_verification_question,
            'password_verification_answer' => $request->password_verification_answer,
            'address' => $request->address,
            'landline_area_code' => $request->landline_area_code,
            'landline_phone' => $request->landline_phone,
            'is_agree_lifetime_membership' => $request->is_agree_lifetime_membership,
            'referrer_id' => $request->referrer_id,
            'gender' => $request->gender,
            'birthday' => $request->dob,
            'agree_to_terms_of_use' => $request->agree_to_terms_of_use,
            'agree_to_collection_and_use_of_personal_information' => $request->agree_to_collection_and_use_of_personal_information,
            'agree_to_provide_personal_information_to_third_parties' => $request->agree_to_provide_personal_information_to_third_parties,
            'agree_to_personal_information_processing' => $request->agree_to_personal_information_processing,
            'agree_to_receive_sms' => $request->agree_to_receive_sms,
            'agree_to_receive_emails' => $request->agree_to_receive_emails,
        ];
        $account->update($accountUpdateAttributes);

        // Create Warehouse
        // $warehouseTitle = 'account_warehouse_' . $account->_id;
        // $warehouse = Warehouse::create([
        //     'type' => 'PERSONAL',
        //     'slug' => Str::slug($warehouseTitle),
        //     'title' => [
        //         'en' => $warehouseTitle,
        //         'zh' => $warehouseTitle,
        //         'cn' => $warehouseTitle
        //     ],
        //     'account_id' => $account->_id,
        //     'is_system' => true,
        // ]);

        // Update Notification Settings
        // $setting = $account->notificationSetting;
        // $setting->update([
        //     "channels" => ["EMAIL", "SMS"],
        //     "language" => "EN",
        //     "is_accept" => [
        //         "marketing_info" => true,
        //         "delivery_update" => true,
        //         "wishlist_product_update" => true,
        //         "special_offers" => true,
        //         "auction_notifications" => true,
        //         "bid_notifications" => true,
        //         "monthly_newsletter" => true,
        //         "sales_support" => true
        //     ],
        //     "is_notifiable" => true,
        // ]);

        // Return success message
        return response()->json([
            'message' => 'Registered as new Customer successfully',
            'id' => $user->id,
            'warehouse_id' => null
        ], 200);
    }
}
