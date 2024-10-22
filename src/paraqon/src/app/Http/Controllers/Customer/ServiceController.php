<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Models\Content;
use Carbon\Carbon;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;

class ServiceController extends Controller
{
    public function checkCurrentTime(Request $request)
    {
        $now = now();
        return response()->json([
            'now_time' => $now
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
