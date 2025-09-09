<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function checkCurrentTime(Request $request)
    {
        return response()->json([
            'now_time' => now()
        ], 200);
    }

    public function checkOtherTimeZone(Request $request)
    {
        $timeZone = (int) $request->timezone;
        $now = now()->addHours($timeZone);
        return response()->json([
            'now_time' => $now
        ], 200);
    }
}
