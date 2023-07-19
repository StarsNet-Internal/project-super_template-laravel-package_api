<?php

namespace StarsNet\Project\TuenSir\App\Models;

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
        'product_variant_id' => null,
        'qty' => 0,
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

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class
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

    public function associateProductVariant(ProductVariant $variant): bool
    {
        $this->productVariant()->associate($variant);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
