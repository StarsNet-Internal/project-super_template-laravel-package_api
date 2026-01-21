<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\TripleGaga\App\Models\LoginRecord;

class LoginRecordController extends Controller
{
    public function getAllAccounts(): Collection
    {
        return Account::whereHas('user', function ($query) {
            return $query->where('type', '!=', 'TEMP');
        })
            ->get();
    }

    public function getAll(): Collection
    {
        return LoginRecord::all();
    }

    public function createLoginRecord(Request $request)
    {
        LoginRecord::create($request->all());

        return [
            'message' => 'Updated LoginRecord successfully'
        ];
    }

    public function updateLoginRecord(Request $request)
    {
        LoginRecord::where($request->route('id'))
            ->update($request->all());

        return [
            'message' => 'Updated LoginRecord successfully'
        ];
    }
}
