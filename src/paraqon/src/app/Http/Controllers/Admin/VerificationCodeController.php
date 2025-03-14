<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\VerificationCode;

class VerificationCodeController extends Controller
{
    public function getAllVerificationCodes(Request $request)
    {
        $limit = $request->input('limit', 100);

        $verificationCodes = VerificationCode::orderByDesc('created_at')
            ->take($limit)
            ->with(['user', 'user.account'])
            ->get();
        return $verificationCodes;
    }
}
