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
use StarsNet\Project\Videocom\App\Actions\CreateOrder;
use StarsNet\Project\Videocom\App\Models\AuctionLot;

class TestingController extends Controller
{
    public function cart(Request $request)
    {
        $customerID = $request->customer_id;
        $storeID = $request->store_id;

        $customer = Customer::find($customerID);
        $store = Store::find($storeID);

        $order = new CreateOrder(
            $customer,
            $store
        );
        $items = $order->getAllCartItems();

        return response()->json($items);
    }

    public function healthCheck(Request $request)
    {
        $customerID = $request->customer_id;
        $storeID = $request->storeID;

        $customer = Customer::find($customerID);
        $store = Store::find($storeID);

        $order = new CreateOrder(
            $customer,
            $store
        );
        return response()->json($order);

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
