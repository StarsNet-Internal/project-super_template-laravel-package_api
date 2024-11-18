<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;

class BidController extends Controller
{
    public function getAllBids(Request $request)
    {
        $customer = $this->customer();

        $bids = Bid::where('customer_id', $customer->id)
            ->where('is_hidden', false)
            ->with([
                'store',
                'product',
                'auctionLot'
            ])
            ->get();

        return $bids;
    }

    public function cancelBid(Request $request)
    {
        // Extract attributes from request
        $bidID = $request->route('id');
        $bid = Bid::find($bidID);

        // Validate Bid
        if (is_null($bid)) {
            return response()->json([
                'message' => 'Bid not found'
            ], 404);
        }

        $customer = $this->customer();

        if ($bid->customer_id != $customer->_id) {
            return response()->json([
                'message' => 'You cannot cancel bids that are not placed by your account'
            ], 404);
        }

        // Validate AuctionLot
        $auctionLot = $bid->auctionLot;

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::ACTIVE) {
            return response()->json([
                'message' => 'You cannot cancel ADVANCED bid when the auction lot is already ACTIVE'
            ], 404);
        }

        $now = now();
        if ($now >= Carbon::parse($auctionLot->start_datetime)) {
            return response()->json([
                'message' => 'You cannot cancel ADVANCED bid when the auction lot has already started'
            ], 404);
        }

        // Update Bid
        $bid->update(['is_hidden' => true]);

        // Update BidHistory and AuctionLot
        if ($bid->type == 'ADVANCED') {
            $auctionLotID = $auctionLot->_id;

            $bidHistory = BidHistory::where('auction_lot_id', $auctionLotID)->first();
            if ($bidHistory == null) {
                $bidHistory = BidHistory::create([
                    'auction_lot_id' => $auctionLotID,
                    'current_bid' => $auctionLot->starting_price,
                    'histories' => []
                ]);
            } else {
                // Clear all histories items
                $bidHistory->update([
                    'current_bid' => $auctionLot->starting_price,
                    'histories' => []
                ]);
            }

            // Find winningCustomerID
            $auctionLotMaximumBid = Bid::where('auction_lot_id', $auctionLotID)
                ->where('is_hidden',  false)
                ->orderBy('bid', 'desc')
                ->first();

            if (!is_null($auctionLotMaximumBid)) {
                // get current bid and winner
                $newCurrentBid = $auctionLot->getCurrentBidPrice(
                    true,
                    $auctionLotMaximumBid->customer_id,
                    $auctionLotMaximumBid->bid,
                    $auctionLotMaximumBid->type
                );

                $winningCustomerID = null;
                if (!is_null($auctionLotMaximumBid)) {
                    $winningCustomerID = $auctionLotMaximumBid->customer_id;
                }

                // Update BidHistory
                $bidHistoryItemAttributes = [
                    'winning_bid_customer_id' => $winningCustomerID,
                    'current_bid' => $newCurrentBid
                ];
                $bidHistory->histories()->create($bidHistoryItemAttributes);
                $bidHistory->update(['current_bid' => $newCurrentBid]);

                // Update Auction Lot
                $auctionLot->update([
                    'is_bid_placed' => true,
                    'current_bid' => $newCurrentBid,
                    'latest_bid_customer_id' => $winningCustomerID,
                    'winning_bid_customer_id' => $winningCustomerID,
                ]);
            } else {
                $auctionLot->update([
                    'is_bid_placed' => false,
                    'current_bid' => $auctionLot->starting_price,
                    'latest_bid_customer_id' => null,
                    'winning_bid_customer_id' => null,
                ]);
            }
        }

        return response()->json([
            'message' => 'Bid cancelled successfully'
        ], 200);
    }

    public function cancelLiveBid(Request $request)
    {
        // Extract attributes from request
        $bidID = $request->route('id');
        $bid = Bid::find($bidID);

        // Validate Bid
        if (is_null($bid)) {
            return response()->json([
                'message' => 'Bid not found'
            ], 404);
        }

        $customer = $this->customer();

        if ($bid->customer_id != $customer->_id) {
            return response()->json([
                'message' => 'You cannot cancel bids that are not placed by your account'
            ], 404);
        }

        // Validate AuctionLot
        $auctionLot = $bid->auctionLot;

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::ACTIVE) {
            return response()->json([
                'message' => 'You cannot cancel ADVANCED bid when the auction lot is already ACTIVE'
            ], 404);
        }

        // $now = now();
        // if ($now >= Carbon::parse($auctionLot->start_datetime)) {
        //     return response()->json([
        //         'message' => 'You cannot cancel ADVANCED bid when the auction lot has already started'
        //     ], 404);
        // }

        // Update Bid
        $bid->update(['is_hidden' => true]);

        // Update BidHistory and AuctionLot
        if ($bid->type == 'ADVANCED') {
            $auctionLotID = $auctionLot->_id;

            $bidHistory = BidHistory::where('auction_lot_id', $auctionLotID)->first();
            if ($bidHistory == null) {
                $bidHistory = BidHistory::create([
                    'auction_lot_id' => $auctionLotID,
                    'current_bid' => $auctionLot->starting_price,
                    'histories' => []
                ]);
            } else {
                // Clear all histories items
                $bidHistory->update([
                    'current_bid' => $auctionLot->starting_price,
                    'histories' => []
                ]);
            }

            // Find winningCustomerID
            $auctionLotMaximumBid = Bid::where('auction_lot_id', $auctionLotID)
                ->where('is_hidden',  false)
                ->orderBy('bid', 'desc')
                ->first();

            if (!is_null($auctionLotMaximumBid)) {
                // get current bid and winner
                $newCurrentBid = $auctionLot->getCurrentBidPrice(
                    true,
                    $auctionLotMaximumBid->customer_id,
                    $auctionLotMaximumBid->bid,
                    $auctionLotMaximumBid->type
                );

                $winningCustomerID = null;
                if (!is_null($auctionLotMaximumBid)) {
                    $winningCustomerID = $auctionLotMaximumBid->customer_id;
                }

                // Update BidHistory
                $bidHistoryItemAttributes = [
                    'winning_bid_customer_id' => $winningCustomerID,
                    'current_bid' => $newCurrentBid
                ];
                $bidHistory->histories()->create($bidHistoryItemAttributes);
                $bidHistory->update(['current_bid' => $newCurrentBid]);

                // Update Auction Lot
                $auctionLot->update([
                    'is_bid_placed' => true,
                    'current_bid' => $newCurrentBid,
                    'latest_bid_customer_id' => $winningCustomerID,
                    'winning_bid_customer_id' => $winningCustomerID,
                ]);
            } else {
                $auctionLot->update([
                    'is_bid_placed' => false,
                    'current_bid' => $auctionLot->starting_price,
                    'latest_bid_customer_id' => null,
                    'winning_bid_customer_id' => null,
                ]);
            }
        }

        return response()->json([
            'message' => 'Bid cancelled successfully'
        ], 200);
    }
}
