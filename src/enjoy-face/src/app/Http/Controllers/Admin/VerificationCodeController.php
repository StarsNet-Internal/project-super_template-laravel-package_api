<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\VerificationCode;

class VerificationCodeController extends Controller
{
    public function getAllVerificationCodes(Request $request)
    {
        $limit = $request->input('limit', 100);

        $verificationCodes = VerificationCode::orderByDesc('created_at')
            ->take($limit)
            ->with('user')
            ->get();
        return $verificationCodes;
    }
}
