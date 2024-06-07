<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Store;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            // 'latestBidCustomer',
            // 'winningBidCustomer'
        ])->find($auctionLotId);

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

        // Correct the bid value of highest bid to the lowest increment possible
        $bids = $auctionLot->bids()
            ->where('is_hidden', false)
            ->get()
            ->sortByDesc('bid')
            ->sortBy('created_at')
            ->sortByDesc('bid');
        $validBidValues = $bids->unique('bid')->pluck('bid')->sort()->values()->all();

        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        $product = $auctionLot->product;
        $currentBid = 0;
        if (count($bids) <= 1) {
            $currentBid = $product->starting_price;
        } else {
            // Get all valid bids
            rsort($validBidValues);

            // Get all earliest bid per bid value
            $previousBiddingCustomerID = null;
            $earliestValidBids = new Collection();

            foreach ($validBidValues as $searchingBidValue) {
                $filteredBids = $bids->filter(function ($item) use ($searchingBidValue, $previousBiddingCustomerID) {
                    return $item->bid === $searchingBidValue;
                });
                $earliestBid = $filteredBids->sortBy('created_at')->first();
                if (is_null($earliestBid)) continue;

                // Extract info
                $earliestBid->bid_counter = $filteredBids->count();
                $earliestValidBids->push($earliestBid);
            }

            // Splice all Bid with successive customer_id
            $validBids = new Collection();
            foreach ($earliestValidBids as $item) {
                if ($item->customer_id != $previousBiddingCustomerID || $item->bid_counter >= 2) {
                    $validBids->push($item);
                    $previousBiddingCustomerID = $item->customer_id;
                }
            }

            // Finalize the final bid highest value
            $validBids = $validBids->sortByDesc('bid')->values();
            if (
                !is_null($incrementRulesDocument) && $validBids->get(0)->bid_counter < 2
            ) {
                $previousValidBid = $validBids->get(1)->bid;

                // Calculate next valid minimum bid value
                $incrementRules = $incrementRulesDocument->bidding_increments;
                $nextValidBid = $previousValidBid;
                foreach ($incrementRules as $key => $interval) {
                    if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                        $nextValidBid = $previousValidBid + $interval['increment'];
                    }
                }

                $validBids->transform(function (
                    $item,
                    $key
                ) use ($nextValidBid) {
                    if ($key == 0) {
                        if ($item['bid'] > $nextValidBid) {
                            $item['bid'] = $nextValidBid;
                        }
                    }
                    return $item;
                });

                $currentBid = $validBids[0]->bid;
            } else {
                $currentBid = $validBids[0]->bid;
            }
        }
        $auctionLot->current_bid = $currentBid;

        // Check is_reserve_met
        $auctionLot->is_reserve_price_met = $auctionLot->current_bid >= $auctionLot->reserve_price;
        $auctionLot->setHidden(['reserve_price']);

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

        foreach ($auctionLots as $auctionLot) {
            // Correct the bid value of highest bid to the lowest increment possible
            $bids = $auctionLot->bids()
                ->where('is_hidden', false)
                ->get()
                ->sortByDesc('bid')
                ->sortBy('created_at')
                ->sortByDesc('bid');
            $validBidValues = $bids->unique('bid')->pluck('bid')->sort()->values()->all();

            // Get all earliest bid per bid value
            $previousBiddingCustomerID = null;
            $earliestValidBids = new Collection();
            foreach ($validBidValues as $searchingBidValue) {
                $filteredBids = $bids->filter(function ($item) use ($searchingBidValue, $previousBiddingCustomerID) {
                    return $item->bid === $searchingBidValue;
                });
                $earliestBid = $filteredBids->sortBy('created_at')->first();
                if (is_null($earliestBid)) continue;

                // Extract info
                $earliestBid->bid_counter = $filteredBids->count();
                $earliestValidBids->push($earliestBid);
            }

            // Splice all Bid with successive customer_id
            $validBids = new Collection();
            foreach ($earliestValidBids as $item) {
                if ($item->customer_id != $previousBiddingCustomerID || $item->bid_counter >= 2) {
                    $validBids->push($item);
                    $previousBiddingCustomerID = $item->customer_id;
                }
            }

            // Correct the bid value of highest bid to the lowest increment possible
            $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
            $validBids = $validBids->sortByDesc('bid')->values();

            // Finalize the final bid highest value
            if ($validBids->count() >= 2) {
                if (!is_null($incrementRulesDocument) && $validBids->get(0)->bid_counter < 2) {
                    $previousValidBid = $validBids->get(1)->bid;

                    // Calculate next valid minimum bid value
                    $incrementRules = $incrementRulesDocument->bidding_increments;
                    $nextValidBid = $previousValidBid;
                    foreach ($incrementRules as $key => $interval) {
                        if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                            $nextValidBid = $previousValidBid + $interval['increment'];
                        }
                    }

                    $validBids->transform(function (
                        $item,
                        $key
                    ) use ($nextValidBid) {
                        if (
                            $key == 0
                        ) {
                            if ($item['bid'] > $nextValidBid) {
                                $item['bid'] = $nextValidBid;
                            }
                        }
                        return $item;
                    });
                }
            }

            if ($validBids->count() == 1) {
                $validBids[0]->bid = $auctionLot->starting_price;
            }

            // Update current_bid
            if ($validBids->count() > 0) {
                $auctionLot->current_bid = $validBids[0]->bid;
            } else {
                $auctionLot->current_bid = $auctionLot->starting_price;
            }
        }

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
                // 'latestBidCustomer',
                // 'winningBidCustomer'
            ])
            ->get();

        // foreach ($auctionLots as $lot) {
        //     $lot->bid_count = $lot->bids()->count();
        // }

        // Calculate highest bid
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();

        // Correct the bid value of highest bid to the lowest increment possible
        foreach ($auctionLots as $auctionLot) {
            // Get all bids
            $bids = $auctionLot->bids()
                ->where('is_hidden', false)
                ->get()
                ->sortByDesc('bid')
                ->sortBy('created_at')
                ->sortByDesc('bid');
            $validBidValues = $bids->unique('bid')->pluck('bid')->sort()->values()->all();

            // Get all earliest bid per bid value
            $previousBiddingCustomerID = null;
            $earliestValidBids = new Collection();
            foreach ($validBidValues as $searchingBidValue) {
                $filteredBids = $bids->filter(function ($item) use ($searchingBidValue, $previousBiddingCustomerID) {
                    return $item->bid === $searchingBidValue;
                });
                $earliestBid = $filteredBids->sortBy('created_at')->first();
                if (is_null($earliestBid)) continue;

                // Extract info
                $earliestBid->bid_counter = $filteredBids->count();
                $earliestValidBids->push($earliestBid);
            }

            // Splice all Bid with successive customer_id
            $validBids = new Collection();
            foreach ($earliestValidBids as $item) {
                if ($item->customer_id != $previousBiddingCustomerID || $item->bid_counter >= 2) {
                    $validBids->push($item);
                    $previousBiddingCustomerID = $item->customer_id;
                }
            }

            // Finalize highest bid value
            $validBids = $validBids->sortByDesc('bid')->values();

            if ($validBids->count() >= 2) {
                if (!is_null($incrementRulesDocument) && $validBids->get(0)->bid_counter < 2) {
                    $previousValidBid = $validBids->get(1)->bid;

                    // Calculate next valid minimum bid value
                    $incrementRules = $incrementRulesDocument->bidding_increments;
                    $nextValidBid = $previousValidBid;
                    foreach ($incrementRules as $key => $interval) {
                        if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                            $nextValidBid = $previousValidBid + $interval['increment'];
                        }
                    }

                    $validBids->transform(function (
                        $item,
                        $key
                    ) use ($nextValidBid) {
                        if (
                            $key == 0
                        ) {
                            if ($item['bid'] > $nextValidBid) {
                                $item['bid'] = $nextValidBid;
                            }
                        }
                        return $item;
                    });
                }
            }

            $calculatedCurrentBid = null;
            // Update current_bid
            if ($validBids->count() > 0) {
                $calculatedCurrentBid = $validBids[0]->bid;
            } else {
                $calculatedCurrentBid = $auctionLot->starting_price;
            }

            $auctionLot->current_bid = $calculatedCurrentBid;
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

        // Get all visible Bid(s)
        $bids = $auctionLot->bids()
            ->where('is_hidden', false)
            ->get()
            ->sortByDesc('bid')
            ->sortBy('created_at')
            ->sortByDesc('bid');
        $validBidValues = $bids->unique('bid')->pluck('bid')->sort()->values()->all();

        // Get all earliest bid per bid value
        $previousBiddingCustomerID = null;
        $earliestValidBids = new Collection();
        foreach ($validBidValues as $searchingBidValue) {
            $filteredBids = $bids->filter(function ($item) use ($searchingBidValue, $previousBiddingCustomerID) {
                return $item->bid === $searchingBidValue;
            });
            $earliestBid = $filteredBids->sortBy('created_at')->first();
            if (is_null($earliestBid)) continue;

            // Extract info
            $earliestBid->bid_counter = $filteredBids->count();
            $earliestValidBids->push($earliestBid);
        }

        // Splice all Bid with successive customer_id
        $validBids = new Collection();
        foreach ($earliestValidBids as $item) {
            if ($item->customer_id != $previousBiddingCustomerID || $item->bid_counter >= 2) {
                $validBids->push($item);
                $previousBiddingCustomerID = $item->customer_id;
            }
        }

        // Correct the bid value of highest bid to the lowest increment possible
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        $validBids = $validBids->sortByDesc('bid')->values();

        // Finalize the final bid highest value
        if ($validBids->count() >= 2) {
            if (!is_null($incrementRulesDocument) && $validBids->get(0)->bid_counter < 2) {
                $previousValidBid = $validBids->get(1)->bid;

                // Calculate next valid minimum bid value
                $incrementRules = $incrementRulesDocument->bidding_increments;
                $nextValidBid = $previousValidBid;
                foreach ($incrementRules as $key => $interval) {
                    if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                        $nextValidBid = $previousValidBid + $interval['increment'];
                    }
                }

                $validBids->transform(function ($item, $key) use ($nextValidBid) {
                    if ($key == 0) {
                        if ($item['bid'] > $nextValidBid) {
                            $item['bid'] = $nextValidBid;
                        }
                    }
                    return $item;
                });
            }
        }

        if ($validBids->count() == 1) {
            $validBids[0]->bid = $auctionLot->starting_price;
        }

        // For highest bid correction
        if (count($validBidValues) > 0) {
            $highestBid = $validBidValues[0];
            $correctedHighestBid = $validBids[0]->bid;

            $extractedBids = $bids->filter(function ($item) use ($highestBid, $correctedHighestBid) {
                return $item->bid == $highestBid || $item->bid <= $correctedHighestBid;
            });
        }

        // Attach customer information per Bid
        foreach ($extractedBids as $bid) {
            $customer = $bid->customer;
            $account = $customer->account;

            $bid->username = optional($account)->username;
            $bid->avatar = optional($account)->avatar;

            if (
                count($validBidValues) > 0 &&
                $bid->bid == $highestBid &&
                $highestBid != $correctedHighestBid
            ) {
                $bid->bid == $correctedHighestBid;
            }
        }

        // Return validated Bids
        return $extractedBids;
    }

    public function createMaximumBid(Request $request)
    {
        // Extract attributes from $request
        $auctionLotId = $request->route('auction_lot_id');
        $requestedBid = $request->bid;

        // Check auction lot
        $auctionLot = AuctionLot::find($auctionLotId);

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        if ($auctionLot->status == Status::ARCHIVED) {
            return response()->json([
                'message' => 'Auction Lot has been archived'
            ], 404);
        }

        if ($auctionLot->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction Lot not found'
            ], 404);
        }

        // Check time
        $store = $auctionLot->store;

        if ($store->status == Status::ARCHIVED) {
            return response()->json([
                'message' => 'Auction has been archived'
            ], 404);
        }

        if ($store->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction not found'
            ], 404);
        }

        $nowDateTime = now();

        if ($nowDateTime < $store->start_datetime) {
            return response()->json([
                'message' => 'Auction has not started'
            ], 404);
        }

        if ($nowDateTime > $store->end_datetime) {
            return response()->json([
                'message' => 'Auction has already ended'
            ], 404);
        }

        // Get current bid
        $currentBid = optional($auctionLot)->current_bid ?? 0;

        // Get bidding increment, and valid minimum bid 
        $biddingIncrementValue = 0;

        if ($auctionLot->is_bid_placed == true) {
            $slug = 'bidding-increments';
            $biddingIncrementRules = Configuration::slug($slug)->latest()->first();

            if (!is_null($biddingIncrementRules)) {
                $range = $biddingIncrementRules->bidding_increments;
                foreach ($range as $key => $interval) {
                    if ($currentBid >= $interval['from'] && $currentBid < $interval['to']) {
                        $biddingIncrementValue = $interval['increment'];
                        break;
                    }
                }
            }
        }

        $minimumBid = $currentBid + $biddingIncrementValue;

        if ($request->bid <= $minimumBid) {
            return response()->json([
                'message' => 'Your bid is lower than current valid bid ' .  $minimumBid . '.',
                'error_status' => 0,
                'bid' => $minimumBid
            ], 400);
        }

        // Get user's current largest bid
        $customer = $this->customer();

        $userExistingMaximumBid = Bid::where('auction_lot_id', $auctionLotId)
            ->where('customer_id', $customer->_id)
            ->where('is_hidden',  false)
            ->orderBy('bid', 'desc')
            ->first();

        // Determine minimum possible bid for input from Customer
        if (!is_null($userExistingMaximumBid)) {
            $minimumBid = max($minimumBid, $userExistingMaximumBid->bid ?? 0);;
        }

        if ($request->bid <= $minimumBid) {
            return response()->json([
                'message' => 'Your bid is lower than your maximum bid value of ' .  $minimumBid . '.',
                'error_status' => 1,
                'bid' => $minimumBid
            ], 400);
        }

        // Validate Request
        // $validator = Validator::make(
        //     $request->all(),
        //     [
        //         'bid' =>
        //         [
        //             'required',
        //             'numeric',
        //             'gte:' . $minimumBid
        //         ]
        //     ]
        // );

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 400);
        // }

        // Create Bid
        $bid = Bid::create([
            'auction_lot_id' => $auctionLotId,
            'customer_id' => $customer->_id,
            'store_id' => $auctionLot->store_id,
            'product_id' => $auctionLot->product_id,
            'product_variant_id' => $auctionLot->product_variant_id,
            'bid' => $requestedBid
        ]);

        // Extend endDateTime
        $gracePeriodInMins = 2;
        $newEndDateTime = now()->addMinutes($gracePeriodInMins)->ceilMinute();

        if ($newEndDateTime > $store->end_datetime) {
            $store->update([
                'end_datetime' => $newEndDateTime
            ]);
        }

        if ($auctionLot->is_bid_placed == false) {
            $auctionLot->update([
                'is_bid_placed' => true,
                // 'current_bid' => $requestedBid,
                'latest_bid_customer_id' => $customer->_id
            ]);
        }

        // Return Auction Store
        return response()->json([
            'message' => 'Created New maximum Bid successfully',
            '_id' => $bid->_id
        ], 200);
    }
}
