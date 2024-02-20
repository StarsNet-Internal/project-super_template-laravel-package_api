<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class BidController extends Controller
{
    public function getAllBids(Request $request)
    {
        $customer = $this->customer();

        $bids = Bid::where('customer_id', $customer->id)
            ->with([
                'store' => function ($query) {
                    $query->select('title', 'images', 'start_datetime', 'end_datetime');
                },
                'product' => function ($query) {
                    $query->select('title', 'images');
                },
                'auctionLot' => function ($query) {
                    $query->select('starting_price', 'current_bid');
                }
            ])
            ->get();

        return $bids;
    }

    // public function createBid(Request $request)
    // {
    //     // Extract attributes from $request
    //     $auctionLotId = $request->auction_lot_id;
    //     $storeId = $request->store_id;
    //     $productId = $request->product_id;

    //     // Validate
    //     $account = $this->account();

    //     // Get Models
    //     $store = Store::find($storeId);
    //     $product = Product::find($productId);

    //     // Create Bid, attach relationship
    //     $bid = new Bid();
    //     $bid->associateAccount($account);
    //     if (!is_null($store)) $bid->associateStore($store);
    //     if (!is_null($product)) $bid->associateProduct($product);

    //     // Return success message
    //     return response()->json([
    //         'message' => 'Created New Bid successfully',
    //         '_id' => $bid->_id
    //     ], 200);
    // }
}
