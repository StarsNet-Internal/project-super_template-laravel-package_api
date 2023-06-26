<?php

namespace StarsNet\Project;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'course branch tag v1.0.0 healthy'
        ], 200);
    }
}
