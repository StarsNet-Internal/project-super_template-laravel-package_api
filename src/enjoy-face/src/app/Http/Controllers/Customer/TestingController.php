<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/enjoy-face'
        ], 200);
    }
}
