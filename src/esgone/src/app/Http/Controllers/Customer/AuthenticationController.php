<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Constants\Model\VerificationCodeType;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\Store;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\StoreDependentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Customer\AuthenticationController as CustomerAuthenticationController;

class AuthenticationController extends CustomerAuthenticationController
{
    use AuthenticationTrait, StoreDependentTrait;

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

    public function register(Request $request)
    {
        // Generate a new customer-identity Account
        $user = $this->createNewUserAsCustomer($request);
        $user->setAsCustomerAccount();

        // Update User
        $this->updateUserViaRegistration($user, $request);
        $user->generateVerificationCodeByType(
            VerificationCodeType::ACCOUNT_VERIFICATION,
            60
        );

        // Update Account
        $account = $user->account;
        $this->updateAccountViaRegistration($account, $request);

        // Package
        $customer = $account->customer;
        $categoryIds = $request->input('category_ids', []);

        $categories = [];
        $greenMember = CustomerGroup::where('slug', 'green-members')->first();
        $categoryIds[] = $greenMember->_id;
        foreach ($categoryIds as $id) {
            $category = CustomerGroup::find($id);

            if (is_null($category)) {
                return response()->json([
                    'message' => 'CustomerGroup not found'
                ], 404);
            }
            $categories[] = $category;
        }
        $customer->attachGroups(collect($categories));
        $account->update($request->except([
            'type', 'username', 'email', 'area_code', 'phone', 'password', 'category_ids'
        ]));

        // Return success message
        return response()->json([
            'message' => 'Registered as new Customer successfully',
        ]);
    }
}
