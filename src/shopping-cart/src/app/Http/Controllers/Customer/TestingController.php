<?php

namespace StarsNet\Project\ShoppingCart\App\Http\Controllers\Customer;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Customer\ShoppingCartController;
use Illuminate\Http\Request;

class TestingController extends Controller
{
    public function healthCheck(Request $request)
    {
        // Log::info($request->header());
        // Log::info(auth()->user());
        // return $request->header();

        return response()->json([
            'message' =>
            $request,
        ], 200);
    }
}
