<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Store;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuctionLotController extends Controller
{
    public function getAuctionLotDetails(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');

        $auctionLot = AuctionLot::with([
            'product',
            'productVariant',
            'store',
        ])->find($auctionLotId);

        if (!in_array(
            $auctionLot->status,
            [Status::ACTIVE, Status::ARCHIVED]
        )) {
            return response()->json([
                'message' => 'Auction is not available for public'
            ], 404);
        }

        // Get isLiked 
        $customer = $this->customer();
        $auctionLot->is_liked = WishlistItem::where([
            'customer_id' => $customer->_id,
            'store_id' => $auctionLot->store_id,
            'product_id' => $auctionLot->product_id,
        ])->exists();

        // Get current_bid
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        $auctionLot->current_bid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);

        // Check is_reserve_met
        $auctionLot->is_reserve_price_met = $auctionLot->current_bid >= $auctionLot->reserve_price;
        $auctionLot->setHidden(['reserve_price']);

        // Return Auction Store
        return $auctionLot;
    }

    public function getAllOwnedAuctionLots(Request $request)
    {
        $customer = $this->customer();

        $auctionLots = AuctionLot::where('owned_by_customer_id', $customer->_id)
            ->with([
                'product',
                'productVariant',
                'store',
                'latestBidCustomer',
                'winningBidCustomer'
            ])->get();

        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);
        }

        return $auctionLots;
    }

    public function getAllParticipatedAuctionLots(Request $request)
    {
        $customer = $this->customer();
        $customerId = $customer->_id;

        $auctionLots = AuctionLot::whereHas('bids', function ($query2) use ($customerId) {
            return $query2->where('customer_id', $customerId);
        })
            ->with([
                'product',
                'productVariant',
                'store',
            ])
            ->get();

        // Calculate highest bid
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);
        }

        return $auctionLots;
    }

    public function getBiddingHistory(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');

        // Get Auction Store(s)
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        // Get all distinctive bid values per AuctionLot, then sort from highest to lowest
        $distinctBidValues = $auctionLot->bids()
            ->where('is_hidden', false)
            ->pluck('bid')
            ->unique()
            ->sort()
            ->reverse();

        // Only get the first 2 earliest bid per distinctive bid value
        $extractedBids = new Collection();
        foreach ($distinctBidValues as $value) {
            // Get the two earliest bids for the current bid value
            $twoEarliestBids = $auctionLot->bids()
                ->where('bid', $value)
                ->where('is_hidden', false)
                ->orderBy('created_at', 'asc')
                ->take(2)
                ->get();

            // Merge the two earliest bids to the collection
            $extractedBids = $extractedBids->merge($twoEarliestBids);
        }

        // Attach customer and account information to each big
        foreach ($extractedBids as $bid) {
            $customer = $bid->customer;
            $account = $customer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;
        }

        // Calculate the new current bid and override
        if ($distinctBidValues->count() > 0) {
            // Get highest with max(), sort reverse didn't work
            $highestMaximumBid = $distinctBidValues->max();

            // Calculate the new highest bid value
            $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
            $calculatedCurrentBid = $auctionLot->getCurrentBidPrice($incrementRulesDocument);

            $extractedBids->transform(function ($item) use ($highestMaximumBid, $calculatedCurrentBid) {
                if ($item['bid'] == $highestMaximumBid) {
                    $item['bid'] = $calculatedCurrentBid;
                }
                return $item;
            });
        }

        return $extractedBids;
    }

    public function createMaximumBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;

        // Check auction lot
        /** @var AuctionLot $auctionLot */
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::ARCHIVED) {
            return response()->json([
                'message' => 'Auction Lot has been archived'
            ], 404);
        }

        if ($auctionLot->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        // if (
        //     $auctionLot->owned_by_customer_id == $this->customer()->_id
        // ) {
        //     return response()->json([
        //         'message' => 'You cannot place bid on your own auction lot'
        //     ], 404);
        // }

        // Check time
        $store = $auctionLot->store;

        if ($store->status == Status::ARCHIVED) {
            return response()->json([
                'message' => 'Auction has been archived'
            ], 404);
        }

        if ($store->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction not found'
            ], 404);
        }

        $nowDateTime = now();

        if ($nowDateTime < $store->start_datetime) {
            return response()->json([
                'message' => 'Auction has not started'
            ], 404);
        }

        if ($nowDateTime > $store->end_datetime) {
            return response()->json([
                'message' => 'Auction has already ended'
            ], 404);
        }

        // Get current bid
        $biddingIncrementRules = Configuration::slug('bidding-increments')->latest()->first();
        $currentBid = $auctionLot->getCurrentBidPrice($biddingIncrementRules);

        // Get bidding increment, and valid minimum bid 
        $biddingIncrementValue = 0;

        if ($auctionLot->is_bid_placed == true) {
            $range = $biddingIncrementRules->bidding_increments;
            foreach ($range as $key => $interval) {
                if ($currentBid >= $interval['from'] && $currentBid < $interval['to']) {
                    $biddingIncrementValue = $interval['increment'];
                    break;
                }
            }
        }

        $minimumBid = $currentBid + $biddingIncrementValue;

        if ($minimumBid > $request->bid) {
            return response()->json([
                'message' => 'Your bid is lower than current valid bid ' .  $minimumBid . '.',
                'error_status' => 0,
                'bid' => $minimumBid
            ], 400);
        }

        // Get user's current largest bid
        $customer = $this->customer();

        $userExistingMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
            ->where('customer_id', $customer->_id)
            ->where('is_hidden',  false)
            ->orderBy('bid', 'desc')
            ->first();

        // Determine minimum possible bid for input from Customer
        if (!is_null($userExistingMaximumBid)) {
            $minimumBid = max($minimumBid, $userExistingMaximumBid->bid ?? 0);;
        }

        if ($request->bid <= $minimumBid) {
            return response()->json([
                'message' => 'Your bid is lower than your maximum bid value of ' .  $minimumBid . '.',
                'error_status' => 1,
                'bid' => $minimumBid
            ], 400);
        }

        // Validate Request
        // $validator = Validator::make(
        //     $request->all(),
        //     [
        //         'bid' =>
        //         [
        //             'required',
        //             'numeric',
        //             'gte:' . $minimumBid
        //         ]
        //     ]
        // );

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 400);
        // }

        // Create Bid
        $bid = Bid::create([
            'auction_lot_id' => $auctionLotId,
            'customer_id' => $customer->_id,
            'store_id' => $auctionLot->store_id,
            'product_id' => $auctionLot->product_id,
            'product_variant_id' => $auctionLot->product_variant_id,
            'bid' => $requestedBid
        ]);

        // Extend endDateTime
        $gracePeriodInMins = 2;
        $newEndDateTime = now()->addMinutes($gracePeriodInMins)->ceilMinute();

        if ($newEndDateTime > $store->end_datetime) {
            $store->update([
                'end_datetime' => $newEndDateTime
            ]);
        }

        if ($auctionLot->is_bid_placed == false) {
            $auctionLot->update([
                'is_bid_placed' => true,
                // 'current_bid' => $requestedBid,
                'latest_bid_customer_id' => $customer->_id
            ]);
        }

        // Return Auction Store
        return response()->json([
            'message' => 'Created New maximum Bid successfully',
            '_id' => $bid->_id
        ], 200);
    }
}
