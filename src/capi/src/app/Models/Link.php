<?php

namespace StarsNet\Project\Capi\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\Status;
use App\Models\Customer;
use App\Models\Product;

// Traits
use App\Traits\Model\ObjectIDTrait;
use App\Traits\Model\Categorizable;
use App\Traits\Model\Excludable;
use App\Traits\Model\NestedAttributeTrait;
use App\Traits\Model\Schedulable;
use App\Traits\Model\StatusFieldTrait;
use App\Traits\Model\Systemizable;

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

use Carbon\Carbon;

class Link extends Eloquent
{
    use ObjectIDTrait,
        Excludable,
        StatusFieldTrait,
        Systemizable,
        Schedulable,
        NestedAttributeTrait;

    use Categorizable;

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
    protected $collection = 'links';

    protected $attributes = [
        // Relationships
        'customer_id' => null,
        'deal_id' => null,
        'product_id' => null,

        // Default
        'value' => null,
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
        );
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(
            Deal::class,
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

    public function associateCustomer(Customer $customer): bool
    {
        $this->customer()->associate($customer);
        return $this->save();
    }

    public function dissociateCustomer(): bool
    {
        $this->customer()->dissociate();
        return $this->save();
    }

    public function associateDeal(Deal $deal): bool
    {
        $this->deal()->associate($deal);
        return $this->save();
    }

    public function dissociateDeal(): bool
    {
        $this->deal()->dissociate();
        return $this->save();
    }

    public function associateProduct(Product $product): bool
    {
        $this->product()->associate($product);
        return $this->save();
    }

    public function dissociateProduct(): bool
    {
        $this->product()->dissociate();
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
