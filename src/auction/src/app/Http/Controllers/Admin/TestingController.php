<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/auction'
        ], 200);
    }
}
