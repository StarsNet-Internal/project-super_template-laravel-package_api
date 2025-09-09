<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestingController extends Controller
{
    public function healthCheck(Request $request)
    {
        return response()->json([
            'message' => 'OK from customer in package/paraqon'
        ], 200);
    }

    public function callbackTest(Request $request)
    {
        return 'asdas';
    }
}
