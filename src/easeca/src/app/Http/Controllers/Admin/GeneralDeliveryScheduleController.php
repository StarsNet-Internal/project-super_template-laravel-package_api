<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;

class GeneralDeliveryScheduleController extends Controller
{
    public function getGeneralDeliverySchedule()
    {
        $slug = 'general-delivery-schedule';
        $schedule = Configuration::slug($slug)->latest()->first();
        return $schedule;
    }

    public function updateGeneralDeliverySchedule(Request $request)
    {
        $slug = 'general-delivery-schedule';
        $schedule = Configuration::slug($slug)->latest()->first();

        // Update OrderCutOffSchedule
        $schedule->update($request->all());

        // Return success message
        return response()->json([
            'message' => 'Updated OrderCutOffSchedule successfully'
        ], 200);
    }
}
