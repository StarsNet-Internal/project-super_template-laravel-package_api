<?php

namespace StarsNet\Project\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\ProductVariantDiscountType;

use App\Models\Order;

// Traits
use App\Traits\Model\ObjectIDTrait;
use App\Traits\Utils\RoundingTrait;

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

class CustomOrderImage extends Eloquent
{
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
    protected $collection = 'custom_order_images';

    protected $attributes = [
        // Relationships
        'order_id' => null,
        'images' => [],

        // Default

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
    // Scope Begins
    // -----------------------------

    public function scopeByCustomer(Builder $query, Customer $customer): Builder
    {
        return $query->where('customer_id', $customer->_id);
    }

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
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

    public function associateOrder(Order $order): bool
    {
        $this->order()->associate($order);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
