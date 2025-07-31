<?php

namespace StarsNet\Project\Paraqon\App\Models;

// Constants

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

use App\Models\Product;

class LocationHistory extends Eloquent
{
    use ObjectIDTrait;

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
    protected $collection = 'location_histories';

    protected $attributes = [
        // Relationships
        'product_id' => null,

        // Default
        'purpose' => [
            'purpose' => 'PRIVATE_SALE',
            'remarks' => ''
        ],
        'status' => [
            'status' => '',
            'remarks' => ''
        ],
        'location' => [
            'location' => 'IN_OFFICE',
            'remarks' => ''
        ],

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
    // Relationship Begins
    // -----------------------------

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

    public function associateProduct(Product $product): bool
    {
        $this->product()->associate($product);
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
