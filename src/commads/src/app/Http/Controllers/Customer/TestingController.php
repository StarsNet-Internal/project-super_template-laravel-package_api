<?php

namespace StarsNet\Project\Commads\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/tuen-sir'
        ], 200);
    }
}
