<?php

namespace StarsNet\Project\Commads\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\ProductVariantDiscountType;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;

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

class CustomStoreQuote extends Eloquent
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
    protected $collection = 'custom_store_quotes';

    protected $attributes = [
        // Relationships
        'quote_order_id' => null,
        'purchase_order_id' => null,
        'cart_items' => [],
        'total_price' => 0,

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

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function quoteOrder(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'quote_order_id'
        );
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'purchase_order_id'
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

    public function associateQuoteOrder(Order $order): bool
    {
        $this->quoteOrder()->associate($order);
        return $this->save();
    }

    public function associatePurchaseOrder(Order $order): bool
    {
        $this->purchaseOrder()->associate($order);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
