<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Warehouse;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;

use Illuminate\Support\Str;

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

        // Update User
        $this->updateUserViaRegistration($user, $request);

        // Update Account
        /** @var ?Account $account */
        $account = $user->account;
        if ($account instanceof Account) {
            $this->updateAccountViaRegistration($account, $request);
        }

        // Create Warehouse
        $warehouseTitle = 'account_warehouse_' . $account->_id;
        $warehouse = Warehouse::create([
            'type' => 'PERSONAL',
            'slug' => Str::slug($warehouseTitle),
            'title' => [
                'en' => $warehouseTitle,
                'zh' => $warehouseTitle,
                'cn' => $warehouseTitle
            ],
            'account_id' => $account->_id,
            'is_system' => true,
        ]);

        // Return success message
        return response()->json([
            'message' => 'Registered as new Customer successfully',
            'id' => $user->id,
            'warehouse_id' => $warehouse->_id
        ], 200);
    }
}
