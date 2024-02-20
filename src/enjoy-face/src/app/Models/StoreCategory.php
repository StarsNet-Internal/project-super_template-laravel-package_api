<?php

namespace StarsNet\Project\EnjoyFace\App\Models;

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

class StoreCategory extends Category
{
    protected $itemModelClass = Store::class;

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
        'model_type' => null,
        'model_type_id' => null,
        'item_type' => 'Store',
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

        'store_category_type' => null,

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

    public function stores(): BelongsToMany
    {
        return $this->items($this->itemModelClass);
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getStoreCountAttribute()
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

    public function attachStores(Collection $stores): void
    {
        $storeIDs = $stores->pluck('_id')->all();
        $this->stores()->attach($storeIDs);
        return;
    }

    public function detachStores(Collection $stores): int
    {
        $storeIDs = $stores->pluck('_id')->all();
        return $this->stores()->detach($storeIDs);
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
