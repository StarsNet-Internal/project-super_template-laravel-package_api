<?php

namespace StarsNet\Project\Paraqon\App\Models;

// Constants
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;

// Traits
use App\Traits\Model\ObjectIDTrait;
use App\Traits\Model\StatusFieldTrait;

// Laravel classes and MongoDB relationships, default import
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Jenssegers\Mongodb\Relations\EmbedsMany;
use Jenssegers\Mongodb\Relations\EmbedsOne;

use App\Models\Account;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\Log;
use StarsNet\Project\Paraqon\App\Models\Bid;

class AuctionLot extends Eloquent
{
    use ObjectIDTrait,
        StatusFieldTrait;

    /**
     * Define database connection.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The database collection used by the model.
     *
     * @var string
     */
    protected $collection = 'auction_lots';

    protected $attributes = [
        // Relationships
        'auction_request_id' => null,
        'owned_by_customer_id' => null,
        'product_id' => null,
        'product_variant_id' => null,
        'store_id' => null,
        'latest_bid_customer_id' => null,
        'winning_bid_customer_id' => null,

        // Default
        'starting_price' => 0,
        'reserve_price' => 0,
        'current_bid' => 0,
        'permission_requests' => [],

        'status' => Status::ACTIVE,
        'reply_status' => ReplyStatus::PENDING,
        'remarks' => null,

        // Booleans
        'is_disabled' => false,
        'is_closed' => false,
        'is_permission_required' => false,
        'is_paid' => false,
        'is_bid_placed' => false,

        'start_datetime' => null,
        'end_datetime' => null,
        // Timestamps
        'deleted_at' => null
    ];

    protected $dates = [
        'deleted_at'
    ];

    protected $casts = [];

    protected $appends = [];

    /**
     * Blacklisted model properties from doing mass assignment.
     * None are blacklisted by default for flexibility.
     * 
     * @var array
     */
    protected $guarded = [];

    protected $hidden = [];

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function auctionRequest(): BelongsTo
    {
        return $this->belongsTo(
            AuctionRequest::class,
        );
    }

    public function ownedCustomer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
            'owned_by_customer_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
        );
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(
            Store::class,
        );
    }

    public function latestBidCustomer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
            'latest_bid_customer_id'
        );
    }

    public function winningBidCustomer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
            'winning_bid_customer_id'
        );
    }

    public function bids(): HasMany
    {
        return $this->hasMany(
            Bid::class
        );
    }

    public function passedAuctionRecords(): HasMany
    {
        return $this->hasMany(
            PassedAuctionRecord::class
        );
    }


    public function bidHistory(): HasOne
    {
        return $this->hasOne(
            BidHistory::class,
            'auction_lot_id'
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function getCurrentMaximumBidValue(
        $allBids,
        $bidHistory,
        $newBidCustomerID = null,
        $newBidValue = null
    ) {
        $startingPrice = $this->starting_price;
        $reservePrice = $this->reserve_price;

        if (count($allBids) > 2 && !is_null($newBidCustomerID)) {
            $winningBid = $bidHistory->histories()->last();

            if ($winningBid->winning_bid_customer_id == $newBidCustomerID) {
                if (
                    max($reservePrice, $winningBid->current_bid, $newBidValue) == $reservePrice // Case 0A
                    || min($reservePrice, $winningBid->current_bid, $newBidValue) == $reservePrice // Case 0C
                ) {
                    return $bidHistory->current_bid;
                }
            }
        }

        // Get all highest maximum bids per customer_id
        $allCustomerHighestBids = $allBids
            ->groupBy('customer_id')
            ->map(function ($item) {
                return $item->sortByDesc('bid')->first();
            })
            ->sortByDesc('bid')
            ->values();

        // Case 1: If 0 bids
        $allCustomerHighestBidsCount = $allCustomerHighestBids->count();

        if ($allCustomerHighestBidsCount === 0) return $startingPrice; // Case 1

        // If 1 bids
        $maxBidValue = $allCustomerHighestBids->max('bid');
        $isReservedPriceMet = $maxBidValue >= $reservePrice;

        if ($allCustomerHighestBidsCount === 1) {
            return $isReservedPriceMet ?
                $reservePrice : // Case 3A
                $startingPrice; // Case 2A
        }

        // If more than 1 bids
        $maxBidCount = $allCustomerHighestBids->where('bid', $maxBidValue)->count();
        if ($maxBidCount >= 2) return $maxBidValue; // Case 2B (ii) & 3B (ii)

        // For Case 2B(i) & 3B (i) Calculations
        $incrementRules = optional($this->bid_incremental_settings)['increments'];

        $maxBidValues = $allCustomerHighestBids->sortByDesc('bid')->pluck('bid')->values()->all();
        $secondHighestBidValue = $maxBidValues[1];

        $incrementalBid = 0;
        if (!is_null($incrementRules)) {
            foreach ($incrementRules as $interval) {
                if ($secondHighestBidValue >= $interval['from'] && $secondHighestBidValue < $interval['to']) {
                    $incrementalBid = $interval['increment'];
                    break;
                }
            }
        }

        if ($isReservedPriceMet) {
            // Case 3B (i)
            return max($reservePrice, min($maxBidValue, $secondHighestBidValue + $incrementalBid));
        } else {
            // Case 2B (i)
            return min($maxBidValue, $secondHighestBidValue + $incrementalBid);
        }
    }

    public function getCurrentBidPrice(
        $isCalculationNeeded = false,
        $newBidCustomerID = null,
        $newBidValue = null,
        $bidType = null
    ) {
        // Ensure BidHistory exists
        $auctionLotId = $this->id;
        $bidHistory = BidHistory::where('auction_lot_id', $auctionLotId)->first();
        $startingPrice = $this->starting_price;

        if ($bidHistory == null) {
            $bidHistory = BidHistory::create([
                'auction_lot_id' => $auctionLotId,
                'current_bid' => $startingPrice,
                'histories' => []
            ]);
        }

        // Return price
        if (!$isCalculationNeeded) {
            if ($bidHistory->histories()->count() == 0) return $startingPrice;
            return $bidHistory->current_bid;
        }

        // Get all bids 
        $allBids = $this->bids()
            ->where('is_hidden', false)
            ->orderByDesc('bid')
            ->orderBy('created_at')
            ->get();


        if (in_array($bidType, ['MAX', 'ADVANCED'])) {
            $maximumMaxBidValue = $this->getCurrentMaximumBidValue(
                $allBids,
                $bidHistory,
                $newBidCustomerID,
                $newBidValue,
            );
            return $maximumMaxBidValue;
        }

        // get maximum Bids value
        $maximumDirectBid = $allBids
            ->first(function ($value) {
                return $value->type == 'DIRECT';
            });
        $maximumDirectBidValue = optional($maximumDirectBid)->bid;

        $maximumMaxBid = $allBids
            ->first(function ($value) {
                return $value->type == 'MAX';
            });
        $maximumMaxBidValue = optional($maximumMaxBid)->bid;

        // Validations
        if (is_null($maximumDirectBidValue) && is_null($maximumMaxBidValue)) return $this->starting_price;
        if (is_null($maximumMaxBidValue)) return $maximumDirectBidValue;
        if ($maximumDirectBidValue > $maximumMaxBidValue) return $maximumDirectBidValue; // Case 5

        if ($maximumDirectBidValue <= $maximumMaxBidValue) {
            $winningBid = $bidHistory->histories()->last();
            $winningCustomerID = $winningBid->winning_bid_customer_id;

            if ($winningCustomerID == $newBidCustomerID) return $maximumDirectBidValue; // Case 4A, 6A
        }

        // Case 4B, 6B
        $maximumMaxBidValue = $this->getCurrentMaximumBidValue(
            $allBids,
            $bidHistory,
            $newBidCustomerID,
            $newBidValue,
        );
        return $maximumMaxBidValue;
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
