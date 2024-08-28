<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;

class TestingController extends Controller
{
    public function healthCheck()
    {
        $now = now();
        $earliestAvailableStore = Store::where('type', StoreType::OFFLINE)
            ->where('status', Status::ARCHIVED)
            ->where('start_datetime', '>', $now->toDateString())
            ->orderBy('start_datetime')
            ->first();
        return $earliestAvailableStore;

        return response()->json([
            'message' => 'OK from package/whisky-whiskers'
        ], 200);
    }
}
