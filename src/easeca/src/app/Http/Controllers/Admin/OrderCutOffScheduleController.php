<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\Easeca\App\Models\OrderCutOffSchedule;

use Illuminate\Support\Arr;

class OrderCutOffScheduleController extends Controller
{
    public function getAllOrderCutOffSchedule(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get all Store(s)
        $schedules = OrderCutOffSchedule::whereHas('store', function ($query) use ($statuses) {
            $query->statusesAllowed(Status::$typesForAdmin, $statuses);
        })->get();

        return $schedules;
    }

    public function updateOrderCutOffSchedule(Request $request)
    {
        // Extract attributes from $request
        $storeId = $request->route('store_id');

        $schedule = OrderCutOffSchedule::where('store_id', $storeId)->first();

        if (is_null($schedule)) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        // Update OrderCutOffSchedule
        $schedule->update($request->all());

        // Return success message
        return response()->json([
            'message' => 'Updated OrderCutOffSchedule successfully'
        ], 200);
    }

    public function updateOrderCutOffSchedules(Request $request)
    {
        // Extract attributes from $request
        $items = $request->items;

        foreach ($items as $item) {
            $storeId = $item['store_id'];

            $schedule = OrderCutOffSchedule::where('store_id', $storeId)->first();

            $updateFields = Arr::except($item, ['store_id']);

            if (is_null($schedule)) {
                $store = Store::find($storeId);
                if (is_null($store)) continue;

                $schedule = OrderCutOffSchedule::create($updateFields);
                $schedule->associateStore($store);
            } else {
                $schedule->update($updateFields);
            }
        }

        // Return success message
        return response()->json([
            'message' => 'Updated OrderCutOffSchedule successfully'
        ], 200);
    }
}
