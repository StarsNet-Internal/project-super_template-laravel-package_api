<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Configuration;
use App\Models\ProductVariant;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Models\Category;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;

class AuctionLotController extends Controller
{
    use RoundingTrait;

    public function createAuctionLot(Request $request)
    {
        // Extract attributes from $request
        $productID = $request->product_id;
        $customerID = $request->customer_id;

        // Get Product
        $product = Product::find($productID);

        if (is_null($product)) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        // Create AuctionLot
        $auctionLotAttributes = [
            'lot_number' => $request->lot_number,
            'title' => $request->title,
            'short_description' => $request->short_description,
            'long_description' => $request->long_description,

            'product_id' => $request->product_id,
            'product_variant_id' => $request->product_variant_id,
            'store_id' => $request->store_id,
            'owned_by_customer_id' => $customerID,

            'starting_price' => $request->starting_price ?? 0,
            'current_bid' => $request->starting_price ?? 0,
            'reserve_price' => $request->reserve_price ?? 0,

            'auction_time_settings' => $request->auction_time_settings,
            'bid_incremental_settings' => $request->bid_incremental_settings,

            'start_datetime' => $request->start_datetime,
            'end_datetime' => $request->end_datetime,

            'status' => $request->status
        ];

        $auctionLot = AuctionLot::create($auctionLotAttributes);

        // Create BidHistory
        $auctionLotID = $auctionLot->_id;

        $bidHistoryAttributes = [
            'auction_lot_id' => $auctionLotID,
            'current_bid' => $auctionLot->starting_bid,
            'histories' => []
        ];
        BidHistory::create($bidHistoryAttributes);

        return response()->json([
            'message' => 'Created AuctionLot successfully',
            '_id' => $auctionLotID,
        ], 200);
    }

    public function getAllAuctionLots(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->category_id;
        $storeID = $request->store_id;

        // Get AuctionLots
        $auctionLots = new Collection();

        if (!is_null($storeID)) {
            $auctionLots = AuctionLot::with(['bids'])
                ->where('store_id', $storeID)
                ->where('status', '!=', Status::DELETED)
                ->whereNotNull('lot_number')
                ->get();
        } else if (!is_null($categoryID)) {
            $auctionLots = AuctionLot::with(['bids'])
                ->whereHas('product', function ($query) use ($categoryID) {
                    $query->whereHas('categories', function ($query2) use ($categoryID) {
                        $query2->where('_id', $categoryID);
                    });
                })
                ->where('status', '!=', Status::DELETED)
                ->whereNotNull('lot_number')
                ->get();
        } else {
            $auctionLots = AuctionLot::with(['bids'])
                ->where('status', '!=', Status::DELETED)
                ->whereNotNull('lot_number')
                ->get();
        }

        // Get Bids statistics
        foreach ($auctionLots as $lot) {
            $lot->bid_count = $lot->bids->count();
            $lot->participated_user_count = $lot->bids
                ->pluck('customer_id')
                ->unique()
                ->count();

            $lot->last_bid_placed_at = optional(
                $lot->bids
                    ->sortByDesc('created_at')
                    ->first()
            )
                ->created_at;

            unset($lot->bids);
        }

        return $auctionLots;
    }
}
