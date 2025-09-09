<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function updateAccountVerification(Request $request)
    {
        $account = $this->account();
        $account->update($request->all());
        return ['message' => 'Updated Verification document successfully'];
    }

    public function getAllCustomerGroups(Request $request)
    {
        return $this->customer()
            ->groups()
            ->statusActive()
            ->get();
    }
}
