<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Models;

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

use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;

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

        'status' => Status::ACTIVE,
        'reply_status' => ReplyStatus::PENDING,
        'remarks' => null,

        // Booleans
        'is_disabled' => false,
        'is_paid' => false,
        'is_bid_placed' => false,

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

    public function getCurrentBidPrice(Configuration $incrementRulesDocument)
    {
        // Get all highest maximum bids per customer_id
        $allBids = $this->bids()
            ->where('is_hidden', false)
            ->get()
            ->groupBy('customer_id')
            ->map(function ($item) {
                return $item->sortByDesc('bid')->first();
            })
            ->sortByDesc('bid')
            ->values();

        // Case 1: If 0 bids
        $allBidsCount = $allBids->count();
        $startingPrice = $this->starting_price;

        if ($allBidsCount === 0) return $startingPrice; // Case 1

        // If 1 bids
        $maxBidValue = $allBids->max('bid');
        $reservePrice = $this->reserve_price;
        $isReservedPriceMet = $maxBidValue >= $reservePrice;

        if ($allBidsCount === 1) {
            return $isReservedPriceMet ?
                $reservePrice : // Case 3A
                $startingPrice; // Case 2A
        }

        // If more than 1 bids
        $maxBidCount = $allBids->where('bid', $maxBidValue)->count();
        if ($maxBidCount >= 2) return $maxBidValue; // Case 2B(ii) & 3B (ii)

        // For Case 2B(ii) & 3B (ii) Calculations
        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        $incrementRules = $incrementRulesDocument->bidding_increments;

        $maxBidValues = $allBids->sortByDesc('bid')->pluck('bid')->values()->all();
        $secondHighestBidValue = $maxBidValues[1];

        $incrementalBid = 0;
        foreach ($incrementRules as $interval) {
            if ($secondHighestBidValue >= $interval['from'] && $secondHighestBidValue < $interval['to']) {
                $incrementalBid = $interval['increment'];
                break;
            }
        }

        // Case 3B (i)
        if ($isReservedPriceMet) {
            return max($reservePrice, $secondHighestBidValue + $incrementalBid);
        } else {
            // Case 2B (i)
            return min($maxBidValue, $secondHighestBidValue + $incrementalBid);
        }
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
