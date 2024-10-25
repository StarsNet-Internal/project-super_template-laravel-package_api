<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;
use StarsNet\Project\Paraqon\App\Models\WatchlistItem;

class AuctionController extends Controller
{
    public function getAllAuctions(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', [
            // Status::DRAFT,
            Status::ACTIVE,
            Status::ARCHIVED
        ]);

        // Get Auction Store(s)
        $auctions = Store::whereType(StoreType::OFFLINE)
            ->statuses($statuses)
            ->get();

        // Append keys
        $customer = $this->customer();
        $watchingAuctionIDs = WatchlistItem::where('customer_id', $customer->id)
            ->where('item_type', 'store')
            ->get()
            ->pluck('item_id')
            ->all();
        // $registeredAuctionIDs = AuctionRegistrationRequest::where('requested_by_customer_id', $customer->id)
        //     ->where('reply_status', ReplyStatus::APPROVED)
        //     ->get()
        //     ->pluck('store_id')
        //     ->all();

        foreach ($auctions as $auction) {
            $storeID = $auction->id;
            $auction->is_watching = in_array($storeID, $watchingAuctionIDs);

            $auctionRegistrationRequest = AuctionRegistrationRequest::where(
                'requested_by_customer_id',
                $customer->id
            )
                ->where('store_id', $auction->id)
                ->first();

            $auction->auction_registration_request = null;
            $auction->is_registered = false;

            if (
                !is_null($auctionRegistrationRequest)
                && in_array($auctionRegistrationRequest->reply_status, [ReplyStatus::APPROVED, ReplyStatus::PENDING])
                && $auctionRegistrationRequest->status === Status::ACTIVE
            ) {
                $auction->is_registered = $auctionRegistrationRequest->reply_status == ReplyStatus::APPROVED;
                $auction->auction_registration_request = $auctionRegistrationRequest;
            }

            $auction->deposits = Deposit::where('customer_id', $customer->id)
                ->where('status', '!=', Status::DELETED)
                ->whereHas('auctionRegistrationRequest', function ($query) use ($storeID) {
                    $query->where('store_id', $storeID);
                })
                ->latest()
                ->get();
        }

        return $auctions;
    }

    public function getAuctionDetails(Request $request)
    {
        // Extract attributes from $request
        $storeId = $request->route('auction_id');

        // Get Auction Store(s)
        $auction = Store::find($storeId);

        if (is_null($auction)) {
            return response()->json([
                'message' => 'Auction not found'
            ], 404);
        }

        if (!in_array($auction->status, [Status::ACTIVE, Status::ARCHIVED])) {
            return response()->json([
                'message' => 'Auction is not available for public'
            ], 404);
        }

        // get Registration Status
        $customer = $this->customer();

        $auctionRegistrationRequest = AuctionRegistrationRequest::where(
            'requested_by_customer_id',
            $customer->id
        )
            ->where('store_id', $auction->id)
            ->first();

        $auction->auction_registration_request = null;
        $auction->is_registered = false;

        if (
            !is_null($auctionRegistrationRequest)
            && in_array($auctionRegistrationRequest->reply_status, [ReplyStatus::APPROVED, ReplyStatus::PENDING])
            && $auctionRegistrationRequest->status === Status::ACTIVE
        ) {
            $auction->is_registered = $auctionRegistrationRequest->reply_status == ReplyStatus::APPROVED;
            $auction->auction_registration_request = $auctionRegistrationRequest;
        }

        // Get Watching Stores
        $watchingAuctionIDs = WatchlistItem::where('customer_id', $customer->id)
            ->where('item_type', 'store')
            ->get()
            ->pluck('item_id')
            ->all();

        $auction->is_watching = in_array($auction->id, $watchingAuctionIDs);

        // Return Auction Store
        return $auction;
    }

    public function getAllPaddles(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->route('auction_id');

        $records = AuctionRegistrationRequest::where('store_id', $storeID)
            ->get();

        $records = $records->map(function ($item) {
            return [
                'customer_id' => $item['requested_by_customer_id'],
                'paddle_id' => $item['paddle_id']
            ];
        });

        return $records;
    }
}
