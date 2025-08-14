<?php

namespace StarsNet\Project\Auction\App\Models;

// Traits
use App\Traits\Model\ObjectIDTrait;

// Laravel classes and MongoDB relationships, default import
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

use App\Models\Customer;

class ReferralCodeHistory extends Eloquent
{
    use ObjectIDTrait;

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
    protected $collection = 'referral_code_histories';

    protected $attributes = [
        // Relationships
        'owned_by_customer_id' => null,
        'used_by_customer_id' => null,
        'referral_code_id' => null,
        'code' => null,

        // Booleans
        'is_disabled' => false,
        'is_deleted' => false,

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

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(
            ReferralCode::class,
        );
    }

    public function ownedByCustomer(): HasMany
    {
        return $this->hasMany(
            Customer::class,
            'owned_by_customer_id'
        );
    }

    public function usedByCustomer(): HasMany
    {
        return $this->hasMany(
            Customer::class,
            'used_by_customer_id'
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------
}
