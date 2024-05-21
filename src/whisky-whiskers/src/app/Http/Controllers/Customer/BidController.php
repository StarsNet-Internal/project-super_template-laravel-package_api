<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class BidController extends Controller
{
    public function getAllBids(Request $request)
    {
        $customer = $this->customer();

        $bids = Bid::where('customer_id', $customer->id)
            ->with([
                'store' => function ($query) {
                    $query->select('title', 'images', 'start_datetime', 'end_datetime');
                },
                'product' => function ($query) {
                    $query->select('title', 'images');
                },
            ])
            ->get();

        // Calculate highest bid
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        // Correct the bid value of highest bid to the lowest increment possible
        foreach ($bids as $bid) {
            $auctionLotID = $bid->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);

            // Get all other bids
            $otherBids = $auctionLot->bids()
                ->where('is_hidden', false)
                ->get()
                ->sortByDesc('bid')
                ->sortBy('created_at')
                ->sortByDesc('bid');
            $validBidValues = $otherBids->unique('bid')->pluck('bid')->sort()->values()->all();

            // Get all earliest bid per bid value
            $previousBiddingCustomerID = null;
            $earliestValidBids = new Collection();
            foreach ($validBidValues as $searchingBidValue) {
                $filteredBids = $otherBids->filter(function ($item) use ($searchingBidValue, $previousBiddingCustomerID) {
                    return $item->bid === $searchingBidValue;
                });
                $earliestBid = $filteredBids->sortBy('created_at')->first();
                if (is_null($earliestBid)) continue;

                // Extract info
                $earliestBid->bid_counter = $filteredBids->count();
                $earliestValidBids->push($earliestBid);
            }

            // Splice all Bid with successive customer_id
            $validBids = new Collection();
            foreach ($earliestValidBids as $item) {
                if ($item->customer_id != $previousBiddingCustomerID || $item->bid_counter >= 2) {
                    $validBids->push($item);
                    $previousBiddingCustomerID = $item->customer_id;
                }
            }

            // Finalize highest bid value
            $validBids = $validBids->sortByDesc('bid')->values();

            if ($validBids->count() >= 2) {
                if (!is_null($incrementRulesDocument) && $validBids->get(0)->bid_counter < 2) {
                    $previousValidBid = $validBids->get(1)->bid;

                    // Calculate next valid minimum bid value
                    $incrementRules = $incrementRulesDocument->bidding_increments;
                    $nextValidBid = $previousValidBid;
                    foreach ($incrementRules as $key => $interval) {
                        if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                            $nextValidBid = $previousValidBid + $interval['increment'];
                        }
                    }

                    $validBids->transform(function (
                        $item,
                        $key
                    ) use ($nextValidBid) {
                        if (
                            $key == 0
                        ) {
                            if ($item['bid'] > $nextValidBid) {
                                $item['bid'] = $nextValidBid;
                            }
                        }
                        return $item;
                    });
                }
            }

            if ($validBids->count() == 1) {
                $validBids[0]->bid = $auctionLot->starting_price;
            }

            $calculatedCurrentBid = null;

            // Update current_bid
            if ($validBids->count() > 0) {
                $calculatedCurrentBid = $validBids[0]->bid;
            } else {
                $calculatedCurrentBid = $auctionLot->starting_price;
            }

            $bid->auction_lot = [
                '_id' => $bid->auction_lot_id,
                'starting_price' => $auctionLot->starting_price,
                'current_bid' => $calculatedCurrentBid,
            ];
        }

        return $bids;
    }
}
