<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use StarsNet\Project\Paraqon\App\Models\AuctionLot;

use App\Models\Store;
use App\Models\Content;
use App\Models\Customer;

class TestingController extends Controller
{
    public function cart(Request $request)
    {
        $customerID = $request->customer_id;
        $storeID = $request->store_id;

        $customer = Customer::find($customerID);
        $store = Store::find($storeID);

        return response()->json($store);
    }

    public function healthCheck(Request $request)
    {
        $paymentIntentID = 123456;
        $url = env('PARAQON_STRIPE_BASE_URLSSS', 'https://socket.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/cancel';
        return [
            'message' => $url,
            // 'message' => 'OK from package/paraqon'
        ];
    }

    public function callbackTest(Request $request)
    {
        return 'asdas';
    }
}
