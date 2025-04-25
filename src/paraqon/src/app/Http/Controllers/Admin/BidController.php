<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;

use App\Constants\Model\Status;
use App\Constants\Model\ReplyStatus;
use Illuminate\Http\Request;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BidController extends Controller
{
    public function createOnlineBidByCustomer(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $customerId = $request->customer_id;
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

        // Check customer
        $customer = Customer::find($customerId);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Check AuctionRegistrationRequest
        $auctionRegistrationRequest = AuctionRegistrationRequest::where('requested_by_customer_id', $customerId)
            ->where('status', Status::ACTIVE)
            ->where('reply_status', ReplyStatus::APPROVED)
            ->latest()
            ->first();

        if (is_null($auctionRegistrationRequest)) {
            return response()->json([
                'message' => 'ACTIVE and APPROVED AuctionRegistrationRequest not found for this customer'
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

    public function cancelBidByCustomer(Request $request)
    {
        // Extract attributes from request
        $bidID = $request->route('bid_id');
        $bid = Bid::find($bidID);

        // Validate Bid
        if (is_null($bid)) {
            return response()->json([
                'message' => 'Bid not found'
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

            // Find winningCustomerID
            $auctionLotMaximumBid = Bid::where('auction_lot_id', $auctionLotID)
                ->where('is_hidden',  false)
                ->orderBy('bid', 'desc')
                ->first();

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
        }

        return response()->json([
            'message' => 'Bid cancelled successfully'
        ], 200);
    }

    public function getCustomerAllBids(Request $request)
    {
        $customerId = $request->route('customer_id');

        $bids = Bid::where('customer_id', $customerId)
            ->with([
                'product',
                'productVariant',
                'store',
            ])
            ->get();

        return $bids;
    }

    public function hideBid(Request $request)
    {
        // Extract attributes from $request
        $bidId = $request->route('bid_id');

        Bid::where('_id', $bidId)->update(['is_hidden' => true]);

        return response()->json([
            'message' => 'Bid updated is_hidden as true'
        ], 200);
    }
}
