<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Models\Content;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Host;

class TestingController extends Controller
{
    public function healthCheck(Request $request)
    {
        $customerID = $request->customer_id;
        $storeID = $request->storeID;

        $customer = Customer::find($customerID);
        $store = Store::find($storeID);

        return response()->json([
            'message' => 'OK from package/videocom'
        ], 200);
    }
}
