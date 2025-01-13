<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Admin;

use App\Constants\Model\ShipmentDeliveryStatus;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Configuration;
use App\Models\Order;
use App\Models\ShoppingCartItem;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/videocom'
        ], 200);
    }
}
