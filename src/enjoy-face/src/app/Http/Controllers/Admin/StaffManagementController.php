<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\LoginType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectAuthenticationTrait;
use Illuminate\Http\Request;

class StaffManagementController extends Controller
{
    use AuthenticationTrait, ProjectAuthenticationTrait;

    public function deleteStaffAccounts(Request $request)
    {
        // Extract attributes from $request
        $userIDs = $request->input('ids', []);

        // Get User(s)
        /** @var Collection $users */
        $users = User::find($userIDs);

        // Update User(s)
        /** @var User $user */
        foreach ($users as $user) {
            $user->softDeletes();
            $this->updateLoginIdOnDelete($user);
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $users->count() . ' User(s) successfully'
        ], 200);
    }

    public function createStaff(Request $request)
    {
        // Extract attributes from $request
        $registrationType = $request->input('type', LoginType::EMAIL);
        $email = $request->input('email');
        $userName = $request->input('username');
        $roleID = $request->input('role_id');

        // Get Role, then validate
        /** @var Role $role */
        $role = Role::find($roleID);

        if (is_null($role)) {
            $role = Role::slug('staff')->latest()->first();
        }

        // Generate a new customer-identity Account
        $user = $this->createNewUserAsCustomer($request);
        $user->setAsStaffAccount($role);

        // Update User
        $this->updateUserViaRegistration($user, $request);

        // Update Account
        /** @var ?Account $account */
        $account = $user->account;
        if ($account instanceof Account) {
            $this->updateAccountViaRegistration($account, $request);
        }

        // Return success message
        return response()->json([
            'message' => 'Created Staff successfully',
            '_id' => $user->id,
            'customer_id' => $account->customer->_id
        ], 200);
    }

    public function updateMerchantDetails(Request $request)
    {
        $userID = $request->route('id');

        $user = User::find($userID);
        $account = $user->account;
        $account->update($request->all());

        return response()->json([
            'message' => 'Updated Merchant successfully'
        ], 200);
    }
}
