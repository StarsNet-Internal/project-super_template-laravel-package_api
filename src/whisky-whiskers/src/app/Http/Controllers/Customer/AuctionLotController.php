<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Store;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
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
            // 'latestBidCustomer',
            // 'winningBidCustomer'
        ])->find($auctionLotId);

        $auctionLot->is_reserve_price_met = $auctionLot->current_bid >= $auctionLot->reserve_price;
        $auctionLot->setHidden(['reserve_price']);

        // Get isLiked 
        $customer = $this->customer();
        $auctionLot->is_liked = WishlistItem::where([
            'customer_id' => $customer->_id,
            'store_id' => $auctionLot->store_id,
            'product_id' => $auctionLot->product_id,
        ])->exists();

        if (!in_array($auctionLot->status, [Status::ACTIVE, Status::ARCHIVED])) {
            return response()->json([
                'message' => 'Auction is not available for public'
            ], 404);
        }

        // Correct the bid value of highest bid to the lowest increment possible
        $highestValidCurrentBid = $auctionLot->starting_price;
        $bids = $auctionLot->bids()
            ->where('is_hidden', false)
            ->get()
            ->sortByDesc('bid')
            ->sortBy('created_at')
            ->unique('bid')
            ->sortByDesc('bid')
            ->values()
            ->all();

        $startingPrice = $auctionLot->starting_price ?? 0;
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        if (!is_null($incrementRulesDocument)) {
            // Find previous valid bid value
            $previousValidBid = $startingPrice;
            if (count($bids) >= 2) {
                $previousValidBid = $bids[1]->bid;
            }
            // Calculate next valid minimum bid value
            $incrementRules = $incrementRulesDocument->bidding_increments;
            $nextValidBid = $previousValidBid + 1;
            foreach ($incrementRules as $key => $interval) {
                if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                    $nextValidBid = $previousValidBid + $interval['increment'];
                }
            }

            if (count($bids) >= 1) {
                $highestBidInformation = $bids[0];
                if ($highestBidInformation->bid > $nextValidBid) {
                    $highestValidCurrentBid = $nextValidBid;
                }
            }
        }
        $auctionLot->current_bid = $highestValidCurrentBid;

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
                'latestBidCustomer',
                'winningBidCustomer'
            ])
            ->get();

        foreach ($auctionLots as $lot) {
            $lot->bid_count = $lot->bids()->count();
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

        $bids = $auctionLot->bids()
            ->where('is_hidden', false)
            ->get()
            ->sortByDesc('bid')
            ->sortBy('created_at')
            ->unique('bid')
            ->sortByDesc('bid');

        // Correct the bid value of highest bid to the lowest increment possible
        $startingPrice = $auctionLot->starting_price ?? 0;
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        if (!is_null($incrementRulesDocument)) {
            // Find previous valid bid value
            $previousValidBid = $startingPrice;
            if ($bids->count() >= 2) {
                $previousValidBid = $bids->get(2)->bid;
            }

            // Calculate next valid minimum bid value
            $incrementRules = $incrementRulesDocument->bidding_increments;
            $nextValidBid = $previousValidBid + 1;
            foreach ($incrementRules as $key => $interval) {
                if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                    $nextValidBid = $previousValidBid + $interval['increment'];
                }
            }

            if ($bids->count() >= 1) {
                $bids->transform(function ($item, $key) use ($nextValidBid) {
                    if ($key == 0) {
                        if ($item['bid'] > $nextValidBid) {
                            $item['bid'] = $nextValidBid;
                        }
                    }
                    return $item;
                });
            }
        }

        foreach ($bids as $bid) {
            $customer = $bid->customer;
            $account = $customer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;
        }

        // Return Auction Store
        return $bids;
    }

    public function createMaximumBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;

        // Check auction lot
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
        $currentBid = optional($auctionLot)->current_bid ?? 0;

        // Get bidding increment, and valid minimum bid 
        $biddingIncrementValue = 0;

        $slug = 'bidding-increments';
        $biddingIncrementRules = Configuration::slug($slug)->latest()->first();

        if (!is_null($biddingIncrementRules)) {
            $range = $biddingIncrementRules->bidding_increments;
            foreach ($range as $key => $interval) {
                if ($currentBid >= $interval['from'] && $currentBid < $interval['to']) {
                    $biddingIncrementValue = $interval['increment'];
                    break;
                }
            }
        }

        $minimumBid = $currentBid + $biddingIncrementValue;

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

        // Validate Request
        $validator = Validator::make(
            $request->all(),
            [
                'bid' =>
                [
                    'required',
                    'numeric',
                    'gte:' . $minimumBid
                ]
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

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

        // $auctionLot->update([
        //     'current_bid' => $requestedBid,
        //     'latest_bid_customer_id' => $customer->_id
        // ]);

        // Return Auction Store
        return response()->json([
            'message' => 'Created New maximum Bid successfully',
            '_id' => $bid->_id
        ], 200);
    }
}
