<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use StarsNet\Project\TripleGaga\App\Models\LoginRecord;

class LoginRecordController extends Controller
{
    public function createLoginRecord(Request $request)
    {
        $account = $this->account();
        $loginRecord = LoginRecord::create($request->all());
        $loginRecord->update(['account_id' => $account->id]);
        return $loginRecord;
    }
}
