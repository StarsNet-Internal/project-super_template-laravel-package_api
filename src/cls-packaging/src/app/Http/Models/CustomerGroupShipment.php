<?php

namespace StarsNet\Project\ClsPackaging\App\Models;

// Constants

use App\Models\Address;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Warehouse;
// Traits
use App\Traits\Model\ObjectIDTrait;
use App\Traits\Model\StatusFieldTrait;
use Carbon\Carbon;

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

class CustomerGroupShipment extends Eloquent
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
    protected $collection = 'customer_group_shipments';

    protected $attributes = [
        // Relationships
        'customer_group_ids' => [],
        'shipment_id' => null,

        // Default
        'created_by_customer_id' => null

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

    public function scopeByShipment(Builder $query, Shipment $shipment): Builder
    {
        return $query->where('shipment_id', $shipment->_id);
    }

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

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(
            Shipment::class
        );
    }

    public function createdByCustomer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
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

    public function associateShipment(Shipment $shipment): bool
    {
        $this->shipment()->associate($shipment);
        return $this->save();
    }

    public function dissociateShipment(): bool
    {
        $this->shipment()->dissociate();
        return $this->save();
    }

    public function associateCreatedByCustomer(Customer $customer): bool
    {
        $this->createdByCustomer()->associate($customer);
        return $this->save();
    }

    public function dissociateCreatedByCustomer(): bool
    {
        $this->createdByCustomer()->dissociate();
        return $this->save();
    }

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

    // -----------------------------
    // Action Ends
    // -----------------------------
}
