<?php

namespace StarsNet\Project\DemyArt\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;
use StarsNet\Project\DemyArt\App\Models\Calendar;

class CalendarController extends Controller
{
    public function createOrUpdateCalendarEvent(Request $request)
    {
        // Extract attributes from request
        $googleEventID = $request->route('google_event_id');

        // Get event
        $event = Calendar::whereGoogleEventID($googleEventID)->first();

        if (is_null($event)) {
            // Create event
            $event = Calendar::create($request->all());
            $event->setGoogleEventID($googleEventID);
        } else {
            // Update event
            $event->update($request->all());
        }

        return response()->json([
            'message' => 'Event updated successfully',
            '_id' => $event->_id,
            'google_event_id' => $event->google_event_id,
        ], 200);
    }

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
}
