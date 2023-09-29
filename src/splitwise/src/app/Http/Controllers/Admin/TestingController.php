<?php

namespace StarsNet\Project\Splitwise\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/splitwise'
        ], 200);
    }
}
