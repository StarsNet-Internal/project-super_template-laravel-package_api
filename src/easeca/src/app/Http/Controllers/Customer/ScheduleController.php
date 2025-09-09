<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use StarsNet\Project\Easeca\App\Traits\Controller\ProjectScheduleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    use ProjectScheduleTrait;

    public function getSchedule(Request $request)
    {
        $cutOff = $this->getScheduleByAccount($request->input('address', ''));

        $startDate = Carbon::now('Asia/Hong_Kong');
        $currentHour = $startDate->hour;
        $dayOfWeek = $this->getCurrentDayOfWeek();

        $cutOffTime = $cutOff[$dayOfWeek]['hour'];
        if ($currentHour >= $cutOffTime) {
            $workingDays = $cutOff['working_days'];
        } else {
            $workingDays = max($cutOff['working_days'] - 1, 0);
        }

        // Create list for frontend calendar
        $results = [];
        for ($i = $workingDays; $i < 365; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $dow = strtolower(Carbon::parse($date)->format('l'));

            if ($cutOff[$dow]['available'] == false) {
                continue;
            }

            $times = [];
            for ($hour = 10; $hour <= 16; $hour++) {
                $times[] = [
                    'time' => $hour,
                    'price' => $cutOff['min_order_price'],
                ];
            }

            $results[] = [
                'date' => $date,
                'times' => $times,
            ];
        }

        return $results;
    }
}
