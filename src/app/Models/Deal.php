<?php

namespace StarsNet\Project\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\Status;
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

class Deal extends Eloquent
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
    protected $collection = 'deals';

    protected $attributes = [
        // Relationships
        'category_ids' => [],
        'product_id' => null,
        'active_product_variant_ids' => [],

        // Default
        'title' => [
            'en' => null,
            'zh' => null
        ],
        'short_description' => [
            'en' => null,
            'zh' => null
        ],
        'long_description' => [
            'en' => null,
            'zh' => null
        ],
        // 'discount' => null,
        'images' => [],
        'remarks' => null,
        'commission' => 0,
        'status' => Status::DRAFT,

        // Timestamps
        'start_datetime' => null,
        'end_datetime' => null,
        'deleted_at' => null
    ];

    protected $dates = [
        'start_datetime',
        'end_datetime',
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
    // Scope Begins
    // -----------------------------

    public function scopeStatusOngoing(Builder $query): Builder
    {
        $current = Carbon::now();

        return $query->status(Status::ACTIVE)
            ->where('start_datetime', '<=', $current)
            ->where('end_datetime', '>=', $current);
    }

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function categories(): BelongsToMany
    {
        $foreignKey = 'item_ids';
        $localKey = 'category_ids';

        return $this->belongsToMany(
            DealCategory::class,
            null,
            $foreignKey,
            $localKey
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
        );
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(
            Tier::class
        );
    }

    public function dealGroups(): HasMany
    {
        return $this->hasMany(
            DealGroup::class
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getMinDiscountedPriceAttribute()
    {
        return $this->tiers()->get()->min('discounted_price');
    }

    public function getMaxDiscountedPriceAttribute()
    {
        return $this->tiers()->get()->max('discounted_price');
    }

    public function getSuccessfulDealGroupsAttribute()
    {
        return $this->dealGroups()->get()->filter(function ($group) {
            return $group->isDealGroupSuccessful();
        });
    }

    public function getFailedDealGroupsAttribute()
    {
        return $this->dealGroups()->get()->filter(function ($group) {
            return !$group->isDealGroupSuccessful();
        });
    }

    public function getIsEditableAttribute()
    {
        return !$this->isDealOngoing();
    }

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function attachCategories(Collection $categories): void
    {
        $categoryIDs = $categories->pluck('_id')->all();
        $this->categories()->attach($categoryIDs);
        return;
    }

    public function detachCategories(Collection $categories): int
    {
        $categoryIDs = $categories->pluck('_id')->all();
        return $this->categories()->detach($categoryIDs);
    }

    public function syncCategories(Collection $categories): array
    {
        $categoryIDs = $categories->pluck('_id')->all();
        return $this->categories()->sync($categoryIDs);
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

    public function createTier(array $attributes): Tier
    {
        $tier = $this->tiers()->create($attributes);
        return $tier;
    }

    public function createDealGroup(array $attributes): DealGroup
    {
        $group = $this->dealGroups()->create($attributes);
        return $group;
    }

    public function isDealOngoing(): bool
    {
        $start = Carbon::parse($this->start_datetime);
        $end = Carbon::parse($this->end_datetime);
        $current = Carbon::now();

        return $this->isStatusActive() && $current->between($start, $end);
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
