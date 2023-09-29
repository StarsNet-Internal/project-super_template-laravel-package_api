<?php

namespace StarsNet\Project\DemyArt\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
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

use App\Models\Review;

class OrderReview extends Review
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
    protected $collection = CollectionName::REVIEW;

    protected $attributes = [
        // Relationships
        'user_id' => null,
        'model_type' => 'Order',
        'model_type_id' => null,

        'store_id' => null,
        'product_id' => null,
        'product_variant_id' => null,

        // Default
        'images' => [],
        'rating' => 0,
        'comment' => null,
        'status' => Status::ACTIVE,
        'reply_status' => ReplyStatus::PENDING,

        // Sub-documents
        'replies' => [],

        // Timestamps
        'deleted_at' => null,
        'replied_at' => null
    ];

    protected $dates = [
        'deleted_at',
        'replied_at'
    ];

    protected $casts = [];

    protected $appends = [
        'user',
        // 'product_title',
        // 'product_variant_title',
        // 'image'
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'model_type_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
        );
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getOrderAttribute(): ?Order
    {
        return $this->order()->first();
    }

    public function getProductTitleAttribute(): ?array
    {
        return optional($this->getProduct())->title;
    }

    public function getProductVariantTitleAttribute(): ?array
    {
        return optional($this->getProductVariant())->title;
    }

    public function getImageAttribute(): ?string
    {
        // Check if variant images exist
        $variantImages = optional($this->getProductVariant())->images;
        if (!is_null($variantImages) && count($variantImages) > 0) return $variantImages[0];

        // Check if product images exist
        $productImages = optional($this->getProduct())->images;
        if (!is_null($productImages) && count($productImages) > 0) return $productImages[0];

        return null;
    }

    public function getCustomerAttribute()
    {
        return optional($this->getAccountAttribute())->customer;
    }

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function associateOrder(Order $order): bool
    {
        $this->order()->associate($order);
        return $this->save();
    }

    public function associateProduct(Product $product): bool
    {
        $this->product()->associate($product);
        return $this->save();
    }

    public function associateProductVariant(ProductVariant $variant): bool
    {
        $this->productVariant()->associate($variant);
        return $this->save();
    }

    public function getProduct(): ?Product
    {
        return $this->product()->first();
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant()->first();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
