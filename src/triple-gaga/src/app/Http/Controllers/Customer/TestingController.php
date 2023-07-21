<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;

class TestingController extends Controller
{
    public function healthCheck()
    {
        $accounts = Account::all();
        $account = $accounts->first();

        $refillInventory = new RefillInventoryRequest();
        $refillInventory->associateRequestedAccount($account);
        return $refillInventory;
    }
}
