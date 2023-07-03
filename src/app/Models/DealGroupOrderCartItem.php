<?php

namespace Starsnet\Project\App\Models;

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
use Starsnet\Project\App\Constants\Model\OrderCartItemStatus;

class DealGroupOrderCartItem extends Eloquent
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
    protected $collection = 'deal_group_order_cart_items';

    protected $attributes = [
        // Relationships
        'deal_group_id' => null,
        'order_id' => null,
        'order_cart_item_id' => null,

        // Default
        'status' => OrderCartItemStatus::PENDING,
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

    public function dealGroup(): BelongsTo
    {
        return $this->belongsTo(
            DealGroup::class,
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
        );
    }

    public function orderCartItem(): BelongsTo
    {
        return $this->belongsTo(
            OrderCartItem::class
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

    public function associateDealGroup(DealGroup $group): bool
    {
        $this->dealGroup()->associate($group);
        return $this->save();
    }

    public function associateOrder(Order $order): bool
    {
        $this->order()->associate($order);
        return $this->save();
    }

    public function associateOrderCartItem(OrderCartItem $item): bool
    {
        $this->orderCartItem()->associate($item);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
