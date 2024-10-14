<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;

class BidController extends Controller
{
    public function getAllBids(Request $request)
    {
        $customer = $this->customer();

        $bids = Bid::where('customer_id', $customer->id)
            ->with([
                'store',
                'product',
                'auctionLot'
            ])
            ->get();

        // Calculate highest bid
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

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
}
