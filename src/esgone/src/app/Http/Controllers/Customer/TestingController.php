<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/esgone'
        ], 200);
    }
}
