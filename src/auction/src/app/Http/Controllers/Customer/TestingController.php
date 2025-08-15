<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Carbon\Carbon;

class TestingController extends Controller
{
    public function healthCheck()
    {
        $now = now()->addHours(19);
        $deadline = Carbon::create(2025, 8, 15)->endOfDay();
        return response()->json([
            'time_now' => $now,
            'parsedTimeNow' => $deadline,
            'is_expired' => $now->gt($deadline),
            'message' => 'OK from package/auction2'
        ], 200);
    }
}
