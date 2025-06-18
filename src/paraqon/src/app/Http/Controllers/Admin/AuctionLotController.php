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
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;
use StarsNet\Project\Paraqon\App\Models\LiveBiddingEvent;

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

        $highestLotNumber =
            AuctionLot::where('store_id', $request->store_id)
            ->get()
            ->max('lot_number')
            ?? 0;
        $lotNumber = $highestLotNumber + 1;

        // Create AuctionLot
        $auctionLotAttributes = [
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

            'status' => $request->status,

            'documents' => $request->documents ?? [],
            'attributes' => $request->attributes ?? [],
            'shipping_costs' => $request->shipping_costs ?? [],

            'lot_number' => $lotNumber,

            'brand' => $request->brand,
            'saleroom_notice' => $request->saleroom_notice,

            'commission_rate' => $request->commission_rate
        ];

        $auctionLot = AuctionLot::create($auctionLotAttributes);

        // Create BidHistory
        $auctionLotID = $auctionLot->_id;

        $bidHistoryAttributes = [
            'auction_lot_id' => $auctionLotID,
            'current_bid' => $auctionLot->starting_price,
            'histories' => []
        ];
        BidHistory::create($bidHistoryAttributes);

        return response()->json([
            'message' => 'Created AuctionLot successfully',
            '_id' => $auctionLotID,
        ], 200);
    }

    public function updateAuctionLotDetails(Request $request)
    {
        // Extract attributes from $request
        $auctionLotID = $request->route('id');

        // Get AuctionLot
        $auctionLot = AuctionLot::find($auctionLotID);

        // Update AuctionLot
        $auctionLot->update($request->all());

        return response()->json([
            'message' => 'Updated AuctionLot successfully',
        ], 200);
    }

    public function deleteAuctionLots(Request $request)
    {
        // Extract attributes from $request
        $auctionLotIDs = $request->input('ids', []);

        // Get Courier(s)
        /** @var Collection $lots */
        $lots = AuctionLot::find($auctionLotIDs);

        // Update Courier(s)
        /** @var Courier $courier */
        foreach ($lots as $lot) {
            $lot->statusDeletes();
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $lots->count() . ' Lot(s) successfully'
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
            $storeID = Category::find($categoryID)->model_type_id;

            $auctionLots = AuctionLot::with(['bids'])
                ->whereHas('product', function ($query) use ($categoryID) {
                    $query->whereHas('categories', function ($query2) use ($categoryID) {
                        $query2->where('_id', $categoryID);
                    });
                })
                ->where('store_id', $storeID)
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

    public function getAuctionLotDetails(Request $request)
    {
        // Extract attributes from $request
        $auctionLotID = $request->route('id');

        // Get AuctionLot
        $auctionLot = AuctionLot::find($auctionLotID);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found',
            ], 404);
        }

        if ($auctionLot->status === Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found',
            ], 404);
        }

        return $auctionLot;
    }

    public function getAllAuctionLotBids(Request $request)
    {
        // Extract attributes from $request
        $auctionLotID = $request->route('id');

        // Get AuctionLot
        $auctionLot = AuctionLot::find($auctionLotID);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found',
            ], 404);
        }

        if ($auctionLot->status === Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found',
            ], 404);
        }

        // Get Bids
        $bids = $auctionLot->bids()
            ->with('customer')
            ->get();

        return $bids;
    }

    public function massUpdateAuctionLots(Request $request)
    {
        $lotAttributes = $request->lots;

        foreach ($lotAttributes as $lot) {
            $lotID = $lot['id'];
            $auctionLot = AuctionLot::find($lotID);

            // Check if the AuctionLot exists
            if (!is_null($auctionLot)) {
                $updateAttributes = $lot;
                unset($updateAttributes['id']);
                $auctionLot->update($updateAttributes);
            }
        }

        return response()->json([
            'message' => 'Auction Lots updated successfully'
        ], 200);
    }

    public function createLiveBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;
        $bidType = $request->input('type', 'MAX');
        $customerID = $request->customer_id;

        // Validation for the request body
        if (!in_array($bidType, ['MAX', 'DIRECT', 'ADVANCED'])) {
            return response()->json([
                'message' => 'Invalid bid type'
            ], 400);
        }

        // Check auction lot
        /** @var AuctionLot $auctionLot */
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->is_disabled == true) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->owned_by_customer_id == $this->customer()->_id) {
            return response()->json([
                'message' => 'You cannot place bid on your own auction lot'
            ], 404);
        }

        // Check time
        $store = $auctionLot->store;

        if ($store->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction not found'
            ], 404);
        }

        // Get current_bid place
        $now = now();
        $customer = Customer::find($customerID) ?? $this->customer();
        $currentBid = $auctionLot->getCurrentBidPrice();
        $isBidPlaced = $auctionLot->is_bid_placed;

        if (in_array($bidType, ['MAX', 'DIRECT'])) {
            if ($auctionLot->status == Status::ARCHIVED) {
                return response()->json([
                    'message' => 'Auction Lot has been archived'
                ], 404);
            }

            if ($store->status == Status::ARCHIVED) {
                return response()->json([
                    'message' => 'Auction has been archived'
                ], 404);
            }

            // // Check if this MAX or DIRECT bid place after start_datetime
            // if ($now <= Carbon::parse($store->start_datetime)) {
            //     return response()->json([
            //         'message' => 'The auction id: ' . $store->_id . ' has not yet started.',
            //         'error_status' => 2,
            //         'system_time' => now(),
            //         'auction_start_datetime' => Carbon::parse($store->start_datetime)
            //     ], 400);
            // }

            // // Check if this MAX or DIRECT bid place before end_datetime
            // if ($now > Carbon::parse($store->end_datetime)) {
            //     return response()->json([
            //         'message' => 'The auction id: ' . $store->_id . ' has already ended.',
            //         'error_status' => 3,
            //         'system_time' => now(),
            //         'auction_end_datetime' => Carbon::parse($store->end_datetime)
            //     ], 400);
            // }

            // // Get bidding increment, and valid minimum bid 
            // $incrementRules = optional($auctionLot->bid_incremental_settings)['increments'];
            // $biddingIncrementValue = 0;

            // if ($isBidPlaced == true) {
            //     foreach ($incrementRules as $interval) {
            //         if ($currentBid >= $interval['from'] && $currentBid < $interval['to']) {
            //             $biddingIncrementValue = $interval['increment'];
            //             break;
            //         }
            //     }
            // }

            // $minimumBid = $currentBid + $biddingIncrementValue;

            // if ($minimumBid > $request->bid) {
            //     return response()->json([
            //         'message' => 'Your bid is lower than current valid bid ' .  $minimumBid . '.',
            //         'error_status' => 0,
            //         'bid' => $minimumBid
            //     ], 400);
            // }

            // // Get user's current largest bid
            // $userExistingMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
            //     ->where('customer_id', $customer->_id)
            //     ->where('is_hidden',  false)
            //     ->where('type', $bidType)
            //     ->orderBy('bid', 'desc')
            //     ->first();

            // // Determine minimum possible bid for input from Customer
            // if (!is_null($userExistingMaximumBid)) {
            //     $userMaximumBidValue = $userExistingMaximumBid->bid;

            //     if ($request->bid <= $userMaximumBidValue) {
            //         return response()->json([
            //             'message' => 'Your bid cannot be lower than or equal to your maximum bid value of ' . $userMaximumBidValue . '.',
            //             'error_status' => 1,
            //             'bid' => $userMaximumBidValue
            //         ], 400);
            //     }
            // }

            $highestAdvancedBid = $auctionLot->bids()
                ->where('is_hidden', false)
                ->where('type', 'ADVANCED')
                ->orderByDesc('bid')
                ->first();
            $highestAdvancedBidValue = optional($highestAdvancedBid)->bid ?? 0;

            if ($requestedBid < $currentBid && $requestedBid < $highestAdvancedBidValue) {
                return response()->json([
                    'message' => 'Your bid cannot be lower than highest advanced bid value of ' . $highestAdvancedBidValue . '.',
                    'error_status' => 1,
                    'bid' => $highestAdvancedBidValue
                ], 400);
            }

            $auctionLot->bids()
                ->where('is_hidden', false)
                ->where('type', 'DIRECT')
                ->where('bid', '>=', $requestedBid)
                ->update(['is_hidden' => true]);
        }

        // Hide previous placed ADVANCED bid, if there's any
        if ($bidType == 'ADVANCED') {
            if ($auctionLot->status == Status::ACTIVE) {
                return response()->json([
                    'message' => 'Auction Lot is now active, no longer accept any ADVANCED bids'
                ], 404);
            }

            // // Check if this MAX or DIRECT bid place after start_datetime
            // if ($now >= Carbon::parse($store->start_datetime)) {
            //     return response()->json([
            //         'message' => 'Auction has started, no longer accept any ADVANCED bids'
            //     ], 404);
            // }

            Bid::where('auction_lot_id', $auctionLotId)
                ->where('customer_id', $customer->_id)
                ->where('is_hidden', false)
                ->update(['is_hidden' => true]);
        }

        // Create Bid
        $bid = Bid::create([
            'auction_lot_id' => $auctionLotId,
            'customer_id' => $customer->_id,
            'store_id' => $auctionLot->store_id,
            'product_id' => $auctionLot->product_id,
            'product_variant_id' => $auctionLot->product_variant_id,
            'bid' => $requestedBid,
            'type' => $bidType
        ]);

        // Update current_bid
        if (in_array($bidType, ['MAX', 'DIRECT'])) {
            // Extend AuctionLot endDateTime
            $currentLotEndDateTime = Carbon::parse($auctionLot->end_datetime);

            $addExtendDays = $auctionLot->auction_time_settings['extension']['days'];
            $addExtendHours = $auctionLot->auction_time_settings['extension']['hours'];
            $addExtendMins = $auctionLot->auction_time_settings['extension']['mins'];

            $extendLotDeadline = $currentLotEndDateTime->copy()
                ->subDays($addExtendDays)
                ->subHours($addExtendHours)
                ->subMinutes($addExtendMins);

            $newLotEndDateTime = $currentLotEndDateTime;
            if ($now >= $extendLotDeadline && $now < $currentLotEndDateTime) {
                $addMaxDays = $auctionLot->auction_time_settings['allow_duration']['days'];
                $addMaxHours = $auctionLot->auction_time_settings['allow_duration']['hours'];
                $addMaxMins = $auctionLot->auction_time_settings['allow_duration']['mins'];

                $newEndDateTime = $currentLotEndDateTime->copy()
                    ->addDays($addExtendDays)
                    ->addHours($addExtendHours)
                    ->addMinutes($addExtendMins);

                $maxEndDateTime = $currentLotEndDateTime->copy()
                    ->addDays($addMaxDays)
                    ->addHours($addMaxHours)
                    ->addMinutes($addMaxMins);

                $newLotEndDateTime = $newEndDateTime >= $maxEndDateTime
                    ? $maxEndDateTime :
                    $newEndDateTime;
            }

            // Update current_bid
            // Find winningCustomerID
            $auctionLotMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
                ->where('is_hidden',  false)
                ->orderBy('bid', 'desc')
                ->first();

            $winningCustomerID = null;
            if (!is_null($auctionLotMaximumBid)) {
                $winningCustomerID = $auctionLotMaximumBid->customer_id;
            }

            $newCurrentBid = $auctionLot->getCurrentBidPrice(
                true,
                $bid->customer_id,
                $bid->bid,
                $bid->type
            );

            $auctionLot->update([
                'is_bid_placed' => true,
                'current_bid' => $newCurrentBid,
                'latest_bid_customer_id' => $customer->_id,
                'winning_bid_customer_id' => $winningCustomerID,
                'end_datetime' => $newLotEndDateTime->toISOString()
            ]);

            // Create Bid History Record
            // if ($isBidPlaced == false || $newCurrentBid > $currentBid) {
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
            // }

            // Extend Store endDateTime
            $currentStoreEndDateTime = Carbon::parse($store->end_datetime);
            if ($newLotEndDateTime > $currentStoreEndDateTime) {
                $store->update([
                    'end_datetime' => $newLotEndDateTime->toISOString()
                ]);
            }
        }

        if ($bidType == 'ADVANCED') {
            $bidHistory = BidHistory::where('auction_lot_id', $auctionLotId)->first();
            if ($bidHistory == null) {
                $bidHistory = BidHistory::create([
                    'auction_lot_id' => $auctionLotId,
                    'current_bid' => $auctionLot->starting_price,
                    'histories' => []
                ]);
            }

            // get current bid and winner
            $newCurrentBid = $auctionLot->getCurrentBidPrice(
                true,
                $bid->customer_id,
                $bid->bid,
                $bid->type
            );

            // Find winningCustomerID
            $auctionLotMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
                ->where('is_hidden',  false)
                ->orderBy('bid', 'desc')
                ->first();

            $winningCustomerID = null;
            if (!is_null($auctionLotMaximumBid)) {
                $winningCustomerID = $auctionLotMaximumBid->customer_id;
            }

            // Update BidHistory
            $bidHistory->update([
                'current_bid' => $auctionLot->starting_price,
                'histories' => []
            ]);
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
        }

        // Return Auction Store
        return response()->json([
            'message' => 'Created New maximum Bid successfully',
            '_id' => $bid->_id
        ], 200);
    }

    public function resetAuctionLot(Request $request)
    {
        $auctionLotId = $request->route('auction_lot_id');

        $auctionLot = AuctionLot::find($auctionLotId);
        $bidHistory = BidHistory::firstWhere('auction_lot_id', $auctionLotId);

        // Reset events
        LiveBiddingEvent::where('store_id', $auctionLot->store_id)
            ->where('value_1', $auctionLotId)
            ->update(['is_hidden' => true]);

        // Hide previous DIRECT bids
        $auctionLot->bids()
            ->where('is_hidden', false)
            ->where('type', 'DIRECT')
            ->update(['is_hidden' => true]);

        // Reset history
        $bidHistory->update([
            'current_bid' => $auctionLot->starting_price
        ]);
        foreach ($bidHistory['histories'] as $history) {
            $history->update(['is_hidden' => true]);
        }

        // Get current bid and winner
        $auctionLotMaximumBid = $auctionLot->bids()
            ->where('is_hidden',  false)
            ->orderBy('bid', 'desc')
            ->first();

        // Copy from Customer BidController cancelBid START
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
        // Copy from Customer BidController cancelBid END

        return response()->json([
            'message' => 'Lot reset successfully'
        ], 200);
    }

    public function updateBidHistoryLastItem(Request $request)
    {
        // Extract attributes from request
        $auctionLotID = $request->route('auction_lot_id');
        $winningBidCustomerID = $request->winning_bid_customer_id;

        // Find AuctionLot
        $lot = AuctionLot::find($auctionLotID);

        if (is_null($lot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        // Find Customer
        $customer = Customer::find($winningBidCustomerID);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Update BidHistory
        $bidHistory = $lot->bidHistory()->first();

        if (is_null($bidHistory)) {
            return response()->json([
                'message' => 'BidHistory not found'
            ], 404);
        }

        if ($bidHistory->histories()->count() == 0) {
            return response()->json([
                'message' => 'BidHistory histories is empty'
            ], 404);
        }

        $lastItem = $bidHistory->histories()->last();
        $lastItem->update(['winning_bid_customer_id' => $winningBidCustomerID]);

        return response()->json([
            'message' => 'Updated BidHistory winning_bid_customer_id for the last item'
        ], 200);
    }
}
