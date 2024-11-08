<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\LiveBiddingEvent;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;

class LiveBiddingEventController extends Controller
{
    public function createEvent(Request $request)
    {
        $storeId = $request->route('store_id');
        $store = Store::find($storeId);

        // Create Event
        $event = LiveBiddingEvent::create($request->all());
        $event->associateStore($store);

        // Return success message
        return response()->json([
            'message' => 'Success'
        ]);
    }
}
