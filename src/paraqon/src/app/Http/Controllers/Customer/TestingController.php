<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Models\Content;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;

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
        $paymentIntentID = 213;
        $url = env('PARAQON_STRIPE_BASE_URLSSS', 'https://socket.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/cancel';
        return [
            'message' => $url
        ];

        // $now = now();
        // $upcomingStores = Store::where(
        //     'type',
        //     StoreType::OFFLINE
        // )
        //     ->orderBy('start_datetime')
        //     ->get();

        // $nearestUpcomingStore = null;
        // foreach ($upcomingStores as $store) {
        //     $startTime = $store->start_datetime;
        //     $startTime = Carbon::parse($startTime);
        //     if ($now < $startTime) {
        //         $nearestUpcomingStore = $store;
        //         break;
        //     }
        // }
        // return $nearestUpcomingStore;

        return response()->json([
            'message' => 'OK from package/paraqon'
        ], 200);
    }

    public function callbackTest(Request $request)
    {
        return 'asdas';
        $content = Content::create($request->all());
    }
}
