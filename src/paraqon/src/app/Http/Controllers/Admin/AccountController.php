<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

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
    public function updateAccountVerification(Request $request)
    {
        Address::create(['hello' => 'asdad']);
        return 'done';
        // $address = Address::find('655a56be64bcd9e08d02b312');
        // $address->update(['company_name' => 'Fai company']);
        // return $address;

        $accountID = $request->route('account_id');
        $account = Account::find($accountID);

        $account->timestamps = false;
        $account->update(['hello' => 'asdas']);

        return response()->json([
            'message' => 'Updated Verification document successfully'
        ], 200);
    }
}
