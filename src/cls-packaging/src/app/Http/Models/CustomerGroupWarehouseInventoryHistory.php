<?php

namespace StarsNet\Project\ClsPackaging\App\Models;

// Constants
use App\Constants\CollectionName;

// Traits
use App\Traits\Model\ObjectIDTrait;

// Required for MYSQL and MongoDB cross-database hybrid relationships
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Jenssegers\Mongodb\Relations\EmbedsMany;
use Jenssegers\Mongodb\Relations\EmbedsOne;

use App\Models\WarehouseInventoryHistory;
use App\Models\CustomerGroup;
use App\Models\ProductVariant;

class CustomerGroupWarehouseInventoryHistory extends WarehouseInventoryHistory
{
    /**
     * The database collection used by the model.
     *
     * @var string
     */
    protected $collection = 'customer_group_warehouse_inventory_history';

    protected $attributes = [
        // Relationships
        'product_variant_id' => null,
        'customer_group_ids' => [],
        'created_by_user_id' => null,

        // Default
        'type' => null,
        'value_cbm' => null,
        'value_pc' => null,
        'balance' => null,
        'remarks' => null,

        // Timestamps
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

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerGroup::class,
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

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function attachCustomerGroups(Collection $groups): void
    {
        $customerGroupIDs = $groups->pluck('_id')->all();
        $this->customerGroups()->attach($customerGroupIDs);
        return;
    }

    public function detachCustomerGroups(Collection $groups): int
    {
        $customerGroupIDs = $groups->pluck('_id')->all();
        return $this->customerGroups()->detach($customerGroupIDs);
    }

    public function syncCustomerGroups(Collection $groups): array
    {
        $customerGroupIDs = $groups->pluck('_id')->all();
        return $this->customerGroups()->sync($customerGroupIDs);
    }

    public function associateProductVariant(ProductVariant $productVariant): bool
    {
        $this->productVariant()->associate($productVariant);
        return $this->save();
    }

    public function dissociateProductVariant(): bool
    {
        $this->productVariant()->dissociate();
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
