<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/paraqon'
        ], 200);
    }
}
