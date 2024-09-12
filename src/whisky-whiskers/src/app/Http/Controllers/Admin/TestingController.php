<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Configuration;

use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\ProductStorageRecord;


class TestingController extends Controller
{
    public function healthCheck()
    {
        // $lot = AuctionLot::find("66e29cae450bbb287309d818");
        // $allBids = $lot->bids()
        // ->where('is_hidden', false)
        // ->orderByDesc('bid')
        // ->orderBy('created_at')
        // ->get();

        // ->sortBy([
        //     'bid' => 'desc',
        //     'created_at' => 'asc',
        // ])

        // return $allBids;
        // ProductStorageRecord::create([
        //     // Default
        //     'start_datetime' => now()->toIso8601String(),
        // ]);

        // $auctionLot = AuctionLot::find('666a6af54231ff34c107fc97');

        // // Get Bids info
        // $allBids = $auctionLot->bids()
        //     ->where('is_hidden', false)
        //     ->get()
        //     ->groupBy('customer_id')
        //     ->map(function ($item) {
        //         return $item->sortByDesc('bid')->first();
        //     })
        //     ->sortByDesc('bid')
        //     ->values();

        // $allBidsCount = $allBids->count();

        // // Case 1: If 0 bids
        // $startingPrice = $auctionLot->starting_price;
        // if ($allBidsCount === 0) return $startingPrice; // Case 1

        // // If 1 bids
        // $maxBidValue = $allBids->max('bid');
        // $reservePrice = $auctionLot->reserve_price;
        // $isReservedPriceMet = $maxBidValue >= $reservePrice;
        // if ($allBidsCount === 1) {
        //     return $isReservedPriceMet ?
        //         $reservePrice : // Case 3A
        //         $startingPrice; // Case 2A
        // }

        // // If more than 1 bids
        // $maxBidCount = $allBids->where('bid', $maxBidValue)->count();
        // if ($maxBidCount >= 2) return $maxBidValue; // Case 2B(ii) & 3B (ii)

        // // For Case 2B(ii) & 3B (ii) Calculations
        // $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        // $incrementRules = $incrementRulesDocument->bidding_increments;

        // $maxBidValues = $allBids->sortByDesc('bid')->pluck('bid')->values()->all();
        // $secondHighestBidValue = $maxBidValues[1];

        // $incrementalBid = 0;
        // foreach ($incrementRules as $interval) {
        //     if ($secondHighestBidValue >= $interval['from'] && $secondHighestBidValue < $interval['to']) {
        //         $incrementalBid = $interval['increment'];
        //         break;
        //     }
        // }

        // if ($isReservedPriceMet) {
        //     // Case 3B (i)
        //     return max($reservePrice, $secondHighestBidValue + $incrementalBid);
        // } else {
        //     // Case 2B (i)
        //     return min($maxBidValue, $secondHighestBidValue + $incrementalBid);
        // }


        // return $uniqueCustomerhighestBids;

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
