<?php

namespace StarsNet\Project;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package'
        ], 200);
    }
}
