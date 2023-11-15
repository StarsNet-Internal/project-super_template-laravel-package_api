<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScheduleController extends Controller
{
    public function getSchedule(Request $request)
    {
        try {
            $user = $this->user();
            $url = 'http://192.168.0.252:5000/customer/schedules?user_id=' . $user->id;
            $response = Http::get($url);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data;
        } catch (\Throwable $th) {
            return [];
        }
    }
}
