<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Store;
use App\Models\Customer;
use Carbon\Carbon;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use Illuminate\Support\Facades\Http;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use StarsNet\Project\WhiskyWhiskers\App\Models\BidHistory;

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
            'bids'
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
        $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();

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
            ->where('status', '!=', Status::DELETED)
            ->with([
                'product',
                'productVariant',
                'store',
                'latestBidCustomer',
                'winningBidCustomer'
            ])->get();

        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();
            // $auctionLot->passed_auction_count = $auctionLot->passedAuctionRecords()
            //     ->where('customer_id', $customer->_id)
            //     ->get();
        }

        return $auctionLots;
    }

    public function getAllParticipatedAuctionLots(Request $request)
    {
        $customer = $this->customer();
        $customerId = $customer->_id;

        $auctionLots = AuctionLot::whereHas(
            'bids',
            function ($query2) use ($customerId) {
                return $query2->where('customer_id', $customerId);
            }
        )
            ->where('status', '!=', Status::DELETED)
            ->with([
                'product',
                'productVariant',
                'store',
                'winningBidCustomer'
            ])
            ->get();

        // Calculate highest bid
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();
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

        // Get Bid History
        $biddingIncrementRules = Configuration::slug('bidding-increments')->latest()->first();
        $currentBid = $auctionLot->getCurrentBidPrice();

        $bidHistory = BidHistory::where('auction_lot_id', $auctionLotId)->first();
        if ($bidHistory == null) {
            $bidHistory = BidHistory::create([
                'auction_lot_id' => $auctionLotId,
                'current_bid' => $currentBid,
                'histories' => []
            ]);
        }
        $displayBidRecords = $bidHistory['histories'];

        // Attach customer and account information to each bid
        foreach ($displayBidRecords as $bid) {
            $winningBidCustomerID = $bid['winning_bid_customer_id'];
            $winningCustomer = Customer::find($winningBidCustomerID);
            $account = $winningCustomer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;
        }

        return $displayBidRecords;
    }

    public function createMaximumBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;
        $now = now();

        // Check auction lot
        /** @var AuctionLot $auctionLot */
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if (
            $auctionLot->status == Status::DELETED
        ) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::ARCHIVED) {
            return response()->json([
                'message' => 'Auction Lot has been archived'
            ], 404);
        }

        if (
            $auctionLot->owned_by_customer_id == $this->customer()->_id
        ) {
            return response()->json([
                'message' => 'You cannot place bid on your own auction lot'
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

        if ($now <= Carbon::parse($store->start_datetime)) {
            return response()->json([
                'message' => 'Auction has not started'
            ], 404);

            return response()->json([
                'message' => 'The auction id: ' . $store->_id . ' has not yet started.',
                'error_status' => 2,
                'system_time' => now(),
                'auction_start_datetime' => Carbon::parse($store->start_datetime)
            ], 400);
        }

        if ($now > Carbon::parse($store->end_datetime)) {
            return response()->json([
                'message' => 'The auction id: ' . $store->_id . ' has already ended.',
                'error_status' => 3,
                'system_time' => now(),
                'auction_end_datetime' => Carbon::parse($store->end_datetime)
            ], 400);
        }

        // Get current bid
        $customer = $this->customer();
        $biddingIncrementRules = Configuration::slug('bidding-increments')->latest()->first();
        $currentBid = $auctionLot->getCurrentBidPrice();

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
        $userExistingMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
            ->where('customer_id', $customer->_id)
            ->where('is_hidden',  false)
            ->orderBy('bid', 'desc')
            ->first();

        // Determine minimum possible bid for input from Customer
        if (!is_null($userExistingMaximumBid)) {
            $userMaximumBidValue = $userExistingMaximumBid->bid;

            if ($request->bid <= $userMaximumBidValue) {
                return response()->json([
                    'message' => 'Your bid cannot be lower than or equal to your maximum bid value of ' . $userMaximumBidValue . '.',
                    'error_status' => 1,
                    'bid' => $userMaximumBidValue
                ], 400);
            }
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

        $auctionLotMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
            ->where('is_hidden',  false)
            ->orderBy('bid', 'desc')
            ->first();

        $winningCustomerID = null;
        if (!is_null($auctionLotMaximumBid)) {
            $winningCustomerID = $auctionLotMaximumBid->customer_id;
        }

        $newCurrentBid = $auctionLot->getCurrentBidPrice();
        $auctionLot->update([
            'is_bid_placed' => true,
            'current_bid' => $newCurrentBid,
            'latest_bid_customer_id' => $customer->_id,
            'winning_bid_customer_id' => $winningCustomerID
        ]);

        // Create Bid History Record
        if ($newCurrentBid > $currentBid) {
            $bidHistory = BidHistory::where('auction_lot_id', $auctionLotId)->first();
            if ($bidHistory == null) {
                $bidHistory = BidHistory::create([
                    'auction_lot_id' => $auctionLotId,
                    'current_bid' => $newCurrentBid,
                    'histories' => []
                ]);
            }

            $bidHistoryItemAttributes = [
                'winning_bid_customer_id' => $winningCustomerID,
                'current_bid' => $newCurrentBid
            ];
            $bidHistory->histories()->create($bidHistoryItemAttributes);
            $bidHistory->update(['current_bid' => $newCurrentBid]);
        }

        // Extend endDateTime
        $gracePeriodInMins = 15;
        $currentEndDateTime = Carbon::parse($store->end_datetime);
        $graceEndDateTime = $currentEndDateTime->copy()->subMinutes($gracePeriodInMins);

        if ($now > $graceEndDateTime && $now < $currentEndDateTime) {
            $newEndDateTime = $currentEndDateTime->copy()->addMinutes($gracePeriodInMins);
            $store->update([
                'end_datetime' => $newEndDateTime->toISOString()
            ]);
        }

        // Socket
        if ($requestedBid == $auctionLot->starting_price || $newCurrentBid > $currentBid) {
            try {
                $url = 'https://socket.whiskywhiskers.com/api/publish';
                $data = [
                    "site" => 'whisky-whiskers',
                    "room" => $auctionLotId,
                    "message" => [
                        "bidPrice" => $newCurrentBid,
                        "lotId" => $auctionLotId,
                    ]
                ];

                $res = Http::post(
                    $url,
                    $data
                );
            } catch (\Exception $e) {
                print($e);
            }
        }

        // Return Auction Store
        return response()->json([
            'message' => 'Created New maximum Bid successfully',
            '_id' => $bid->_id
        ], 200);
    }
}
