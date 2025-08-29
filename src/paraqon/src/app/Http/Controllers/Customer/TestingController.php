<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Admin\DevelopmentController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\Store;
use App\Models\Content;
use App\Models\Customer;
use MongoDB\BSON\UTCDateTime;

class TestingController extends Controller
{
    public function healthCheck(Request $request)
    {
        // $s = ScheduledPayment::create(['time_now' => new UTCDateTime(now())]);
        // return $s;
    }

    public function callbackTest(Request $request)
    {
        return 'asdas';
    }
}
