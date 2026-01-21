<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\Account;

class UserController extends Controller
{
    public function updateUserPassword(Request $request)
    {
        /** @var ?Account $account */
        $account = Account::where('_id', $request->account_id)->first();
        if (is_null($account)) return response()->json(['Account not found'], 404);

        /** @var ?User $user */
        $user = $account->user;
        if (is_null($user)) return response()->json(['User not found'], 404);

        // Update Password
        $user->update(['password' => Hash::make($request->password)]);

        return [
            'message' => 'Updated User password successfully',
            'user_id' => $user->id,
            'login_id' => $user->login_id
        ];
    }
}
