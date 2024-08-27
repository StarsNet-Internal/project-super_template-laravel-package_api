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
        $bids = Bid::raw(function ($collection) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'auction_lot_id' => $this->_id,
                        'is_hidden' => false
                    ],
                ],
                [
                    '$group' => [
                        '_id' => '$customer_id',
                        'highest_bid_value' => ['$max' => '$bid']
                    ]
                ],
                ['$sort' => ['highest_bid_value' => -1]]
            ]);
        });

        // If 0 bids found
        if ($bids->count() === 0) {
            return $this->starting_price;
        }

        // If same customer only bid found
        // ! Note customer_id grouped as primary key _id on aggregation
        if ($bids->unique('_id')->count() === 1) {
            $customerID = $bids[0]->_id;

            $customerMaximumBid = Bid::where('customer_id', $customerID)
                ->where("auction_lot_id", $this->_id)
                ->where("is_hidden", false)
                ->get()
                ->max('bid');

            return $customerMaximumBid > $this->reserve_price ?
                $this->reserve_price :
                $this->starting_price;
        }

        // If more than 2 bids found
        $highestTwoDistinctValues = $bids->pluck('highest_bid_value')->unique()->sort()->reverse()->take(2)->values();
        $highestTwo = $bids->filter(function ($item) use ($highestTwoDistinctValues) {
            return in_array($item['highest_bid_value'], $highestTwoDistinctValues->toArray());
        })->groupBy('highest_bid_value')->map(function ($items, $key) {
            return ['highest_bid_value' => $key, 'count' => $items->count()];
        })->values();

        // If more than 2 users share the same highest bid
        $highestBidValue = $highestTwo[0];
        if ($highestBidValue['count'] >= 2) {
            return $highestBidValue['highest_bid_value'];
        }

        // If same customer_id for both earliest bid
        $firstHighestEarliestBidCustomerID = Bid::where('auction_lot_id', $this->_id)
            ->where('is_hidden', false)
            ->where('bid', $highestTwo->max('highest_bid_value'))
            ->oldest()
            ->first()
            ->customer_id;

        $secondHighestEarliestBidCustomerID = Bid::where('auction_lot_id', $this->_id)
            ->where('is_hidden', false)
            ->where('bid', $highestTwo->min('highest_bid_value'))
            ->oldest()
            ->first()
            ->customer_id;

        if ($firstHighestEarliestBidCustomerID === $secondHighestEarliestBidCustomerID) {
            return min($highestTwo->min('highest_bid_value'), $this->reserve_price);
        }

        // If different customer_id for both earliest bids
        $incrementRules = $incrementRulesDocument->bidding_increments;
        $previousValidBid = $highestTwo->count() > 1 ?
            $highestTwo[1]['highest_bid_value'] :
            $this->starting_price;

        foreach ($incrementRules as $key => $interval) {
            if ($previousValidBid >= $interval['from'] && $previousValidBid < $interval['to']) {
                $nextValidBid = $previousValidBid + $interval['increment'];
            }
        }

        return $nextValidBid;
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
