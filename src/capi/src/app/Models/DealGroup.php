<?php

namespace StarsNet\Project\Capi\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\Status;
use App\Models\Order;
use App\Models\OrderCartItem;

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

class DealGroup extends Eloquent
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
    protected $collection = 'deal_groups';

    protected $attributes = [
        // Relationships
        'deal_id' => null,
        'order_ids' => [],
        'order_cart_item_ids' => [],
    ];

    protected $dates = [];

    protected $casts = [];

    protected $appends = [
        'quantity_sold',
    ];

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

    public function deal(): BelongsTo
    {
        return $this->belongsTo(
            Deal::class,
        );
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(
            Order::class
        );
    }

    public function orderCartItems(): BelongsToMany
    {
        return $this->belongsToMany(
            OrderCartItem::class
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getQuantitySoldAttribute()
    {
        $items = [];
        foreach ($this->orders()->get() as $index => $order) {
            $cartItem = array_column($order['cart_items']->toArray(), null, '_id')[$this['order_cart_item_ids'][$index]];
            $items[] = $cartItem;
        }

        return collect($items)->sum('qty');
    }

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function associateDeal(Deal $deal): bool
    {
        $this->deal()->associate($deal);
        return $this->save();
    }

    public function attachOrders(Collection $orders): void
    {
        $itemIDs = $orders->pluck('_id')->all();
        $this->orders()->attach($itemIDs);
        return;
    }

    public function attachOrderCartItems(Collection $items): void
    {
        $itemIDs = $items->pluck('_id')->all();
        $this->orderCartItems()->attach($itemIDs);
        return;
    }

    public function getTierUserCounts(): array
    {
        $deal = $this->deal()->first();
        $tiers = $deal->tiers()->get()->toArray();
        $counts = array_map(function ($tier) {
            return $tier['user_count'];
        }, $tiers);

        return $counts;
    }

    public function isDealGroupFull(): bool
    {
        $count = $this->quantity_sold;
        $max = max($this->getTierUserCounts());

        return $count >= $max;
    }

    public function isDealGroupValid(): bool
    {
        return $this->deal()->first()->isDealOngoing() && !$this->isDealGroupFull();
    }

    public function isDealGroupSuccessful(): bool
    {
        $count = $this->quantity_sold;
        $min = min($this->getTierUserCounts());

        return $count >= $min;
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
