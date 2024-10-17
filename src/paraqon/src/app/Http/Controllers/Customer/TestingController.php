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

class TestingController extends Controller
{
    public function healthCheck(Request $request)
    {
        $lot = AuctionLot::find('670dee1fcabe9346e20b9238');

        $now = now();
        $currentEndDateTime = Carbon::parse($lot->end_datetime);

        $addExtendDays = $lot->auction_time_settings['extension']['days'];
        $addExtendHours = $lot->auction_time_settings['extension']['hours'];
        $addExtendMins = $lot->auction_time_settings['extension']['mins'];

        $addMaxDays = $lot->auction_time_settings['allow_duration']['days'];
        $addMaxHours = $lot->auction_time_settings['allow_duration']['hours'];
        $addMaxMins = $lot->auction_time_settings['allow_duration']['mins'];

        $newEndDateTime = $now->copy()
            ->addDays($addExtendDays)
            ->addHours($addExtendHours)
            ->addMinutes($addExtendMins);

        $maxEndDateTime = $currentEndDateTime->copy()
            ->addDays($addMaxDays)
            ->addHours($addMaxHours)
            ->addMinutes($addMaxMins);

        $extensionDeadline
            = $currentEndDateTime->copy()
            ->subDays($addExtendDays)
            ->subHours($addExtendHours)
            ->subMinutes($addExtendMins);

        $data = [
            'now' => $now,
            'currentEndDateTime' => $currentEndDateTime,
            'newEndDateTime' => $newEndDateTime,
            'maxEndDateTime' => $maxEndDateTime,
            'extensionDeadline' => $extensionDeadline,
        ];
        return response()->json($data);

        // $now = now();
        // $upcomingStores = Store::where(
        //     'type',
        //     StoreType::OFFLINE
        // )
        //     ->orderBy('start_datetime')
        //     ->get();

        // $nearestUpcomingStore = null;
        // foreach ($upcomingStores as $store) {
        //     $startTime = $store->start_datetime;
        //     $startTime = Carbon::parse($startTime);
        //     if ($now < $startTime) {
        //         $nearestUpcomingStore = $store;
        //         break;
        //     }
        // }
        // return $nearestUpcomingStore;

        return response()->json([
            'message' => 'OK from package/paraqon'
        ], 200);
    }

    public function callbackTest(Request $request)
    {
        return 'asdas';
        $content = Content::create($request->all());
    }
}
