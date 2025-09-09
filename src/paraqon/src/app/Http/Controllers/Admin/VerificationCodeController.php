<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\VerificationCode;

class VerificationCodeController extends Controller
{
    public function getAllVerificationCodes(Request $request)
    {
        return VerificationCode::orderByDesc('created_at')
            ->take($request->limit ?? 100)
            ->with(['user', 'user.account'])
            ->get();
    }
}
