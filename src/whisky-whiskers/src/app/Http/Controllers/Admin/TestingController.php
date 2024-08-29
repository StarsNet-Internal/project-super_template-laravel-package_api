<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;


class TestingController extends Controller
{
    public function healthCheck()
    {
        $now = now();

        // $stores = Store::where('type', 'OFFLINE')
        //     ->where('status', Status::ACTIVE)
        //     ->where('end_datetime', '<=', $now)
        //     ->get();

        return [
            'now' => $now,
            // 'stores' => $stores
        ];

        // $store = Store::find('65c5e6e419152f333900ed86');
        // $date = Carbon::parse($store->end_datetime);
        // return [
        //     'date' => $date,
        //     'end_date_str' => (string) $store->end_datetime,
        //     'end_date' => $date
        // ];

        return response()->json([
            'message' => 'OK from package/auction'
        ], 200);
    }
}
