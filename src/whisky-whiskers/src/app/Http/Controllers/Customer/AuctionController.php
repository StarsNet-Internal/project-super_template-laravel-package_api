<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class AuctionController extends Controller
{
    public function getAllAuctions(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', [Status::ACTIVE, Status::ARCHIVED]);

        // Get Auction Store(s)
        $auctions = Store::whereType(StoreType::OFFLINE)
            ->statuses($statuses)
            ->get();

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

        // Return Auction Store
        return $auction;
    }
}
