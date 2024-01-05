<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK admin from package/esgone'
        ], 200);
    }
}
