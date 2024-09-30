<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use StarsNet\Project\WhiskyWhiskers\App\Models\BidHistory;
use Illuminate\Support\Facades\Http;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuctionLotController extends Controller
{
    public function getAllAuctionLotsInStorage(Request $request)
    {
        $products = Product::where('listing_status', 'AVAILABLE')
            ->statuses(Status::$typesForAdmin)
            ->get();

        $lots = AuctionLot::whereIn('product_id', $products->pluck('_id'))
            ->statuses(Status::$typesForAdmin)
            ->get();

        foreach ($products as $product) {
            $filteredLots = $lots->filter(function ($lot) use ($product) {
                return $lot->product_id == $product->_id;
            })->all();

            $product->auction_lots = array_values($filteredLots);
        }

        return $products;
    }

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

        // Get current_bid
        $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();

        // Check is_reserve_met
        $auctionLot->is_reserve_price_met = $auctionLot->current_bid >= $auctionLot->reserve_price;

        $bidHistory = BidHistory::where('auction_lot_id', $auctionLotId)->first();
        $displayBidRecords = $bidHistory['histories'];

        $customers = Customer::with([
            'account'
        ])->find($bidHistory['histories']->pluck('winning_bid_customer_id'));

        // Attach customer and account information to each bid
        foreach ($displayBidRecords as $bid) {
            $winningBidCustomerId = $bid['winning_bid_customer_id'];
            $customer = $customers->first(function ($customer) use ($winningBidCustomerId) {
                return $customer->id == $winningBidCustomerId;
            });

            $bid->username = $customer->account->username;
            $bid->avatar = $customer->account->avatar;
        }

        $auctionLot->histories = $displayBidRecords;

        // Return Auction Store
        return $auctionLot;
    }
}
