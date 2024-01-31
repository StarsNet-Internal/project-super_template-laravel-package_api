<?php

namespace StarsNet\Project\HeiFei\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK admin from package/heifei'
        ], 200);
    }
}
