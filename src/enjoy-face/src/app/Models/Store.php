<?php

namespace StarsNet\Project\EnjoyFace\App\Models;

// Constants
use App\Constants\Model\Status;
use App\Constants\CollectionName;
use App\Constants\Model\StoreType;
use App\Traits\Model\Excludable;

use App\Models\Store as BaseStore;
use App\Models\Product;

// Traits
use App\Traits\Model\ObjectIDTrait;
use App\Traits\Model\Sluggable;
use App\Traits\Model\StatusFieldTrait;
use App\Traits\Model\Systemizable;
use App\Traits\Model\Typeable;

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

class Store extends BaseStore
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
    protected $collection = CollectionName::STORE;

    protected $attributes = [
        // Relationships
        'discount_template_ids' => [],
        'warehouse_ids' => [],
        'category_ids' => [],

        // Default
        'slug' => null,
        'title' => [
            'en' => null,
            'zh' => null
        ],
        'type' => null,
        'images' => [],
        'remarks' => null,
        'status' => Status::ACTIVE,

        'location' => [
            'latitude' => null,
            'longitude' => null,
            'address' => [
                'en' => null,
                'zh' => null
            ],
            'google_map_link' => null,
        ],
        'opening_hours' => [],

        // Booleans
        'is_system' => false,

        // Timestamps
        'deleted_at' => null
    ];

    protected $dates = [
        'deleted_at'
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected $appends = [];

    /**
     * Blacklisted model properties from doing mass assignment.
     * None are blacklisted by default for flexibility.
     * 
     * @var array
     */
    protected $guarded = [];

    protected $hidden = [
        'discount_template_ids',
        'cashier_ids',
        'warehouse_ids'
    ];

    // -----------------------------
    // Scope Begins
    // -----------------------------

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
            StoreCategory::class,
            null,
            $foreignKey,
            $localKey
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
    // Actions Begins
    // -----------------------------

    // -----------------------------
    // Action Ends
    // -----------------------------
}
