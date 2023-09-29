<?php

namespace StarsNet\Project\ClsPackaging\App\Models;

// Constants
use App\Models\ShoppingCartItem;
use App\Models\Category;
use App\Models\Customer;

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

class CategoryShoppingCartItem extends Eloquent
{
    use ObjectIDTrait;

    use RoundingTrait;

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
    protected $collection = 'category_shopping_cart_items';

    protected $attributes = [
        // Relationships
        'hash' => null,
        'category_id' => null,
        'shopping_cart_item_id' => null,
        'qty' => 0,

        // Default
        // 'created_by_customer_id' => null,

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

    public function shoppingCartItem(): BelongsTo
    {
        return $this->belongsTo(
            ShoppingCartItem::class
        );
    }

    // TODO category
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
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

    public function associateShoppingCartItem(ShoppingCartItem $item): bool
    {
        $this->shoppingCartItem()->associate($item);
        return $this->save();
    }

    public function associateCategory(Category $category): bool
    {
        $this->category()->associate($category);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
