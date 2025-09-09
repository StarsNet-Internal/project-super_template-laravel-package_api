<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Account;

class AccountController extends Controller
{
    public function updateAccountVerification(Request $request)
    {
        $accountID = $request->route('id');
        $account = Account::find($accountID);

        $attributes = $request->all();
        $account->update($attributes);

        return response()->json([
            'message' => 'Updated Verification document successfully'
        ], 200);
    }

    public function updateAccountDetails(Request $request)
    {
        $accountID = $request->route('id');
        $account = Account::find($accountID);

        // Update Account
        $attributes = $request->all();
        $account->update($attributes);

        // Return success message
        return response()->json([
            'message' => 'Updated Account Details successfully'
        ]);
    }
}
