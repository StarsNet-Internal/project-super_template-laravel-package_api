<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
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
            'latestBidCustomer',
            'winningBidCustomer'
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

        $bids = $auctionLot->bids()->get();

        foreach ($bids as $bid) {
            $customer = $bid->customer;
            $account = $customer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;
        }

        // Return Auction Store
        return $bids;
    }

    public function createBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;

        // Validate Request
        $validator = Validator::make(
            $request->all(),
            ['bid' => 'numeric']
        );

        // Get Auction Store(s)
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        // Get Customer
        $customer = $this->customer();

        // Get latest bid
        $latestBid = optional($auctionLot->bids()->latest()->first())->bid ?? $auctionLot->current_bid;
        if (floatval($requestedBid) <= floatval($latestBid)) {
            return response()->json([
                'message' => 'The requested bid cannot be lower than current highest bid'
            ], 404);
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

        $auctionLot->update([
            'current_bid' => $requestedBid,
            'latest_bid_customer_id' => $customer->_id
        ]);

        // Return Auction Store
        return response()->json([
            'message' => 'Created New Bid successfully',
            '_id' => $bid->_id
        ], 200);
    }
}
