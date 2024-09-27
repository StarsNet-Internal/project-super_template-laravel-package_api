<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;

use Illuminate\Http\Request;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function updateAccountVerification(Request $request)
    {
        $accountID = $request->route('account_id');
        $account = Account::find($accountID);

        $account->update($request->all());

        return response()->json([
            'message' => 'Updated Verification document successfully'
        ], 200);
    }
}
