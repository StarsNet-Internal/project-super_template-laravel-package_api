<?php

namespace StarsNet\Project\DemyArt\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;
use StarsNet\Project\DemyArt\App\Models\Calendar;

class CalendarController extends Controller
{
    public function getEventDetails(Request $request)
    {
        // Extract attributes from request
        $googleEventID = $request->route('google_event_id');

        $event = Calendar::whereGoogleEventID($googleEventID)->first();

        if (is_null($event)) {
            return response()->json([
                'message' => 'Event not found'
            ], 404);
        }

        return $event;
    }

    public function getEventList(Request $request)
    {
        // Extract attributes from request
        $variantID = $request->product_variant_id;
        $orderID = $request->order_id;

        $events = Calendar::where('product_variant_id', $variantID)
            ->where('order_id', $orderID)
            ->get();

        return $events;
    }
}
