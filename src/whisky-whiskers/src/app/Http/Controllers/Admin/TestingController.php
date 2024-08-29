<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Configuration;

use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;


class TestingController extends Controller
{
    public function healthCheck()
    {
        // $auctionLot = AuctionLot::find('666a6af5fcbadf1a480b85ab');
        // $bids = $auctionLot->bids()->where('is_hidden', false)->get();
        // $sortedBids = $bids->sortBy('bid')->values()->all();
        // $sortedBids = $bids->sortBy(function ($item) {
        //     return [$item->bid, $item->created_at];
        // })->values()->all();

        // $previousCustomerID = null;
        // $previousBid = null;
        // foreach ($sortedBids as $bid) {
        //     $currentCustomerID = $bid["customer_id"];
        //     $currentBid = $bid["bid"];

        //     if ($currentCustomerID == $previousCustomerID) {
        //         $bid["is_to_be_deleted"] = true;
        //     } else if ($currentBid == $previousBid) {
        //         $bid["is_to_be_deleted"] = true;
        //     } else {
        //         $bid["is_to_be_deleted"] = false;
        //     }

        //     $previousCustomerID = $currentCustomerID;
        //     $previousBid = $currentBid;
        // }
        // $sortedBids = collect($sortedBids)->filter(function ($item) {
        //     return $item["is_to_be_deleted"] == false;
        // });

        // $descendingSortedBids = $sortedBids->sortByDesc('bid')->values();
        // $highestBid = $descendingSortedBids[0]['bid'];
        // $secondHighestBid = $descendingSortedBids[1]['bid'];

        // $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        // $incrementRules = $incrementRulesDocument->bidding_increments;

        // $nextValidBid = $secondHighestBid;
        // foreach ($incrementRules as $key => $interval) {
        //     if ($secondHighestBid >= $interval['from'] && $secondHighestBid < $interval['to']) {
        //         $nextValidBid = $secondHighestBid + $interval['increment'];
        //     }
        // }

        // $sortedBids = $sortedBids->transform(function ($item) use ($highestBid, $nextValidBid) {
        //     if ($item['bid'] == $highestBid) $item['bid'] = $nextValidBid;
        //     unset($item['is_to_be_deleted']);
        //     return $item;
        // })->values()->all();

        // $maxBid = collect($sortedBids)->max('bid');
        // $maxBid = collect($sortedBids)->where('bid', $maxBid)->count();
        // return
        //     $maxBid;


        // $store = Store::find('65c5e6e419152f333900ed86');
        // $date = Carbon::parse($store->end_datetime);
        // return [
        //     'date' => $date,
        //     'end_date_str' => (string) $store->end_datetime,
        //     'end_date' => $date
        // ];

        return response()->json([
            'message' => 'OK from package/auction'
        ], 200);
    }
}
