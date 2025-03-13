<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Store;
use App\Models\Customer;
use Carbon\Carbon;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\Bid;
use Illuminate\Support\Facades\Http;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\WatchlistItem;
use MongoDB\BSON\UTCDateTime;
use DateTime;

class AuctionLotController extends Controller
{
    public function requestForBidPermissions(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');

        // Validate AuctionLot
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if (!in_array(
            $auctionLot->status,
            [Status::ACTIVE, Status::ARCHIVED]
        )) {
            return response()->json([
                'message' => 'Auction is not available for public'
            ], 404);
        }

        if ($auctionLot->is_permission_required == false) {
            return response()->json([
                'message' => 'Auction Lot does not require permission to place bid'
            ], 404);
        }

        // Check if requests exist
        $allBidRequests = $auctionLot->permission_requests;

        $customer = $this->customer();
        $isCustomerRequestExists = collect($allBidRequests)->contains(function ($item) use ($customer) {
            return $item['customer_id'] == $customer->_id
                && in_array($item['approval_status'], ['PENDING', 'APPROVED']);
        });
        if ($isCustomerRequestExists) {
            return response()->json([
                'message' => 'You already have a PENDING or APPROVED request'
            ], 400);
        }

        $bidRequest = [
            'customer_id' => $customer->_id,
            'approval_status' => 'PENDING',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];
        $auctionLot->push('permission_requests', $bidRequest, true);

        return response()->json([
            'message' => 'Request to place bid on this Auction Lot sent successfully'
        ], 200);
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

        if (!in_array(
            $auctionLot->status,
            [Status::ACTIVE, Status::ARCHIVED]
        )) {
            return response()->json([
                'message' => 'Auction is not available for public'
            ], 404);
        }

        // Get isLiked 
        $customer = $this->customer();
        $auctionLot->is_liked = WishlistItem::where([
            'customer_id' => $customer->_id,
            'store_id' => $auctionLot->store_id,
            'product_id' => $auctionLot->product_id,
        ])->exists();

        // Get current_bid
        $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();

        // Check is_reserve_met
        $auctionLot->is_reserve_price_met = $auctionLot->current_bid >= $auctionLot->reserve_price;
        // $auctionLot->setHidden(['reserve_price']);

        // Get Watching Lots
        $watchingAuctionIDs = WatchlistItem::where('customer_id', $customer->id)
            ->where('item_type', 'auction-lot')
            ->get()
            ->pluck('item_id')
            ->all();

        $auctionLot->is_watching = in_array($auctionLotId, $watchingAuctionIDs);

        // Return Auction Store
        return $auctionLot;
    }

    public function getAllAuctionLotBids(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');

        $auctionLot = AuctionLot::find($auctionLotId);

        if (!in_array(
            $auctionLot->status,
            [Status::ACTIVE, Status::ARCHIVED]
        )) {
            return response()->json([
                'message' => 'Auction Lot is not available for public'
            ], 404);
        }

        $bids = Bid::where('auction_lot_id', $auctionLotId)
            ->where('is_hidden', false)
            ->latest()
            ->get();

        // Attach customer and account information to each bid
        foreach ($bids as $bid) {
            $customerID = $bid->customer_id;
            $customer = Customer::find($customerID);
            $account = $customer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;
        }

        return $bids;
    }

    public function getAllOwnedAuctionLots(Request $request)
    {
        $customer = $this->customer();

        $auctionLots = AuctionLot::where('owned_by_customer_id', $customer->_id)
            ->where('status', '!=', Status::DELETED)
            ->with([
                'product',
                'productVariant',
                'store',
                'latestBidCustomer',
                'winningBidCustomer'
            ])->get();

        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();
        }

        return $auctionLots;
    }

    public function getAllParticipatedAuctionLots(Request $request)
    {
        $customer = $this->customer();
        $customerId = $customer->_id;

        $auctionLots = AuctionLot::whereHas(
            'bids',
            function ($query2) use ($customerId) {
                return $query2->where('customer_id', $customerId);
            }
        )
            ->where('status', '!=', Status::DELETED)
            ->with([
                'product',
                'productVariant',
                'store',
                'winningBidCustomer'
            ])
            ->get();

        // Calculate highest bid
        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();
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

        // Get Bid History
        $currentBid = $auctionLot->getCurrentBidPrice();

        $bidHistory = BidHistory::where('auction_lot_id', $auctionLotId)->first();
        if ($bidHistory == null) {
            $bidHistory = BidHistory::create([
                'auction_lot_id' => $auctionLotId,
                'current_bid' => $currentBid,
                'histories' => []
            ]);
        }
        $displayBidRecords = $bidHistory['histories'];

        // Attach customer and account information to each bid
        foreach ($displayBidRecords as $bid) {
            $winningBidCustomerID = $bid['winning_bid_customer_id'];
            $winningCustomer = Customer::find($winningBidCustomerID);
            $account = $winningCustomer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;
        }

        return $displayBidRecords;
    }

    public function createMaximumBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;
        $bidType = $request->input('type', 'MAX');

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
        $customer = $this->customer();
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

            // Check if this MAX or DIRECT bid place after start_datetime
            if ($now <= Carbon::parse($store->start_datetime)) {
                return response()->json([
                    'message' => 'The auction id: ' . $store->_id . ' has not yet started.',
                    'error_status' => 2,
                    'system_time' => now(),
                    'auction_start_datetime' => Carbon::parse($store->start_datetime)
                ], 400);
            }

            // Check if this MAX or DIRECT bid place before end_datetime
            if ($now > Carbon::parse($store->end_datetime)) {
                return response()->json([
                    'message' => 'The auction id: ' . $store->_id . ' has already ended.',
                    'error_status' => 3,
                    'system_time' => now(),
                    'auction_end_datetime' => Carbon::parse($store->end_datetime)
                ], 400);
            }

            // Get bidding increment, and valid minimum bid 
            $incrementRules = optional($auctionLot->bid_incremental_settings)['increments'];
            $biddingIncrementValue = 0;

            if ($isBidPlaced == true) {
                foreach ($incrementRules as $interval) {
                    if ($currentBid >= $interval['from'] && $currentBid < $interval['to']) {
                        $biddingIncrementValue = $interval['increment'];
                        break;
                    }
                }
            }

            $minimumBid = $currentBid + $biddingIncrementValue;

            if ($minimumBid > $request->bid) {
                return response()->json([
                    'message' => 'Your bid is lower than current valid bid ' .  $minimumBid . '.',
                    'error_status' => 0,
                    'bid' => $minimumBid
                ], 400);
            }

            // Get user's current largest bid
            $userExistingMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
                ->where('customer_id', $customer->_id)
                ->where('is_hidden',  false)
                ->where('type', $bidType)
                ->orderBy('bid', 'desc')
                ->first();

            // Determine minimum possible bid for input from Customer
            if (!is_null($userExistingMaximumBid)) {
                $userMaximumBidValue = $userExistingMaximumBid->bid;

                if ($request->bid <= $userMaximumBidValue) {
                    return response()->json([
                        'message' => 'Your bid cannot be lower than or equal to your maximum bid value of ' . $userMaximumBidValue . '.',
                        'error_status' => 1,
                        'bid' => $userMaximumBidValue
                    ], 400);
                }
            }
        }

        // Hide previous placed ADVANCED bid, if there's any
        if ($bidType == 'ADVANCED') {
            if ($auctionLot->status == Status::ACTIVE) {
                return response()->json([
                    'message' => 'Auction Lot is now active, no longer accept any ADVANCED bids'
                ], 404);
            }

            // Check if this MAX or DIRECT bid place after start_datetime
            if ($now >= Carbon::parse($store->start_datetime)) {
                return response()->json([
                    'message' => 'Auction has started, no longer accept any ADVANCED bids'
                ], 404);
            }

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
            $newLotEndDateTime = $currentLotEndDateTime;

            $addExtendDays = $auctionLot->auction_time_settings['extension']['days'];
            $addExtendHours = $auctionLot->auction_time_settings['extension']['hours'];
            $addExtendMins = $auctionLot->auction_time_settings['extension']['mins'];

            $extendLotDeadline = $currentLotEndDateTime->copy()
                ->subDays($addExtendDays)
                ->subHours($addExtendHours)
                ->subMinutes($addExtendMins);

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

                $auctionLot->update([
                    'end_datetime' => $newLotEndDateTime->toISOString()
                ]);
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
            ]);

            // Create Bid History Record
            if ($isBidPlaced == false || $newCurrentBid > $currentBid) {
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
            }

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
            } else {
                // Clear all histories items
                $bidHistory->update([
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

    public function createLiveBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;
        $bidType = $request->input('type', 'MAX');

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
        $customer = $this->customer();
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

            // Get bidding increment, and valid minimum bid 
            $incrementRules = optional($auctionLot->bid_incremental_settings)['increments'];
            $biddingIncrementValue = 0;

            if ($isBidPlaced == true) {
                foreach ($incrementRules as $interval) {
                    if ($currentBid >= $interval['from'] && $currentBid < $interval['to']) {
                        $biddingIncrementValue = $interval['increment'];
                        break;
                    }
                }
            }

            $minimumBid = $currentBid + $biddingIncrementValue;

            if ($minimumBid > $request->bid) {
                return response()->json([
                    'message' => 'Your bid is lower than current valid bid ' .  $minimumBid . '.',
                    'error_status' => 0,
                    'bid' => $minimumBid
                ], 400);
            }

            // Get user's current largest bid
            $userExistingMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
                ->where('customer_id', $customer->_id)
                ->where('is_hidden',  false)
                ->where('type', $bidType)
                ->orderBy('bid', 'desc')
                ->first();

            // Determine minimum possible bid for input from Customer
            if (!is_null($userExistingMaximumBid)) {
                $userMaximumBidValue = $userExistingMaximumBid->bid;

                if ($request->bid <= $userMaximumBidValue) {
                    return response()->json([
                        'message' => 'Your bid cannot be lower than or equal to your maximum bid value of ' . $userMaximumBidValue . '.',
                        'error_status' => 1,
                        'bid' => $userMaximumBidValue
                    ], 400);
                }
            }
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
            if ($isBidPlaced == false || $newCurrentBid > $currentBid) {
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
            }

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
            } else {
                // Clear all histories items
                $bidHistory->update([
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
}
