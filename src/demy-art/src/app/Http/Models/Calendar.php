<?php

namespace StarsNet\Project\DemyArt\App\Models;

// Constants

use App\Models\Address;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;

// Traits
use App\Traits\Model\ObjectIDTrait;
use App\Traits\Model\StatusFieldTrait;

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

class Calendar extends Eloquent
{
    use ObjectIDTrait,
        StatusFieldTrait;

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
    protected $collection = 'calendar';

    protected $attributes = [
        // Relationships
        'google_event_id' => null,

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

    public function scopeWhereGoogleEventID(Builder $query, string $id): Builder
    {
        return $query->where('google_event_id', $id);
    }

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

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

    public function setGoogleEventID(string $id)
    {
        $this->google_event_id = $id;
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
