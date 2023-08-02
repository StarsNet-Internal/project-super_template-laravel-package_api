<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Models\Account;
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

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;

class Bid extends Eloquent
{
    use ObjectIDTrait;

    /**
     * Define database connection.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    protected $attributes = [
        // Relationships
        'account_id' => null,
        'store_id' => null,
        'product_id' => null,

        // Default
        'bid_price' => 0,

        // Timestamps
    ];

    protected $dates = [];

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

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(
            Store::class,
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
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

    public function associateAccount(Account $account): bool
    {
        $this->account()->associate($account);
        return $this->save();
    }

    public function associateProduct(Product $product): bool
    {
        $this->product()->associate($product);
        return $this->save();
    }

    public function associateStore(Store $store): bool
    {
        $this->store()->associate($store);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
