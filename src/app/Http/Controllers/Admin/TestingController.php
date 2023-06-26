<?php

namespace StarsNet\ProjectPackage;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'testing second course branch healthy'
        ], 200);
    }
}
