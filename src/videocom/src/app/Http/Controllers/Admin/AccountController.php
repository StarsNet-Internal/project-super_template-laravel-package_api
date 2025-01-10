<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;


use Illuminate\Http\Request;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
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
