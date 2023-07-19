<?php

namespace StarsNet\Project\Capi\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\Status;
use App\Models\Category;
use App\Models\Store;

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

class DealCategory extends Category
{
    protected $mainModelClass = Store::class;
    protected $itemModelClass = Deal::class;

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
    protected $collection = CollectionName::CATEGORY;

    protected $attributes = [
        // Relationships
        'parent_id' => null,
        'model_type' => 'Store',
        'model_type_id' => null,
        'item_type' => 'Deal',
        'item_ids' => [],

        // Default
        'slug' => null,
        'title' => [
            'en' => null,
            'zh' => null
        ],
        'description' => [
            'en' => null,
            'zh' => null
        ],
        'images' => [],
        'remarks' => null,
        'status' => Status::ACTIVE,

        // Booleans
        'is_system' => false,

        // Timestamps
        'deleted_at' => null
    ];

    protected $dates = [
        'deleted_at'
    ];

    protected $casts = [
        'is_system' => 'boolean'
    ];

    // protected $appends = [
    //     'parent_category'
    // ];

    /**
     * Blacklisted model properties from doing mass assignment.
     * None are blacklisted by default for flexibility.
     * 
     * @var array
     */
    protected $guarded = [];

    protected $hidden = [
        'model_type',
        'model_type_id',
        'item_type',
        'item_ids'
    ];

    // -----------------------------
    // Scope Begins
    // -----------------------------

    public function scopeStoreID(Builder $query, Store $store): Builder
    {
        $storeID = $store->_id;
        return $query->where('model_type_id', $storeID);
    }

    public function scopeByStore(Builder $query, Store $store): Builder
    {
        return $query->where('model_type_id', $store->_id);
    }

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function store(): BelongsTo
    {
        return $this->modelType();
    }

    public function deals(): BelongsToMany
    {
        return $this->items($this->itemModelClass);
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getDealCountAttribute()
    {
        // return $this->posts()->count();
        return count($this->item_ids);
    }

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function associateStore(Store $store): bool
    {
        return $this->associateModelType($store);
    }

    public function attachDeals(Collection $deals): void
    {
        $dealIDs = $deals->pluck('_id')->all();
        $this->deals()->attach($dealIDs);
        return;
    }

    public function detachDeals(Collection $deals): int
    {
        $dealIDs = $deals->pluck('_id')->all();
        return $this->deals()->detach($dealIDs);
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
