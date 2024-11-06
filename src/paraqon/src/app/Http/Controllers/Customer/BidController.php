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

        // Correct the bid value of highest bid to the lowest increment possible
        foreach ($bids as $bid) {
            $auctionLotID = $bid->auction_lot_id;
            $auctionLot = AuctionLot::find($auctionLotID);
            $bid->auction_lot = [
                '_id' => $bid->auction_lot_id,
                'starting_price' => $auctionLot->starting_price,
                'current_bid' => $auctionLot->getCurrentBidPrice(),
            ];
        }

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

            // Get all ADVANCED bids
            $allAdvancedBids = $auctionLot->bids()
                ->where('is_hidden', false)
                ->orderBy('bid')
                ->orderBy('created_at')
                ->get()
                ->groupBy('bid')
                ->map(function ($group) {
                    return $group->first();
                })
                ->values();

            // Create History Item
            $lastBid = $allAdvancedBids->last();
            foreach ($allAdvancedBids as $bid) {
                $bidHistoryItemAttributes = [
                    'winning_bid_customer_id' => $bid->customer_id,
                    'current_bid' => $bid->bid
                ];
                $bidHistory->histories()->create($bidHistoryItemAttributes);

                if ($bid === $lastBid) {
                    $bidHistory->update(['current_bid' => $bid->bid]);
                    $auctionLot->update([
                        'is_bid_placed' => true,
                        'current_bid' => $bid->bid,
                        'latest_bid_customer_id' => $bid->customer_id,
                        'winning_bid_customer_id' => $bid->customer_id,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Bid cancelled successfully'
        ], 200);
    }
}
