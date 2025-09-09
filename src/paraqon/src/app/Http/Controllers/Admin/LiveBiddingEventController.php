<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\LiveBiddingEvent;

class LiveBiddingEventController extends Controller
{
    public function createEvent(Request $request)
    {
        $store = Store::find($request->route('store_id'));

        $event = LiveBiddingEvent::create($request->all());
        $event->associateStore($store);

        return ['message' => 'Success'];
    }
}
