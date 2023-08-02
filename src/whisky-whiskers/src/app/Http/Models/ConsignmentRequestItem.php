<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;

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
use App\Models\ProductVariant;

class ConsignmentRequestItem extends Eloquent
{
    use ObjectIDTrait;

    /**
     * Define database connection.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    protected $attributes = [
        // Relationships
        'product_id' => null,

        // Default
        'title' => null,
        'description' => null,

        'is_approved' => false,
        'evaluated_price' => 0,
        'evaluated_currency' => 'HKD',
        'remarks' => null

        // Timestamps
    ];

    protected $dates = [];

    protected $casts = [];

    protected $appends = [
        // Product-related
        'product_title',
        'product_variant_title',
        'image'
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

    public function getProductTitleAttribute(): ?array
    {
        return optional($this->getProduct())->title;
    }

    public function getImageAttribute(): ?string
    {
        $productImages = optional($this->getProduct())->images;
        if (!is_null($productImages) && count($productImages) > 0) return $productImages[0];

        return null;
    }

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

    public function getProduct(): Product
    {
        return $this->product()->first();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
