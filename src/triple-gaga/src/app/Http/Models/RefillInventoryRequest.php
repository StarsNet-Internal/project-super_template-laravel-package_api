<?php

namespace StarsNet\Project\TripleGaga\App\Models;

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

use App\Models\Account;
use App\Models\Warehouse;

class RefillInventoryRequest extends Eloquent
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
    protected $collection = 'refill_inventory_requests';

    protected $attributes = [
        // Relationships
        'requested_by_account_id' => null,
        'approved_by_account_id' => null,
        'requested_warehouse_id' => null,
        'approved_warehouse_id' => null,

        // Default
        'items' => [],
        'request_items_qty' => 0,
        'approved_items_qty' => 0,
        'status' => Status::ACTIVE,
        'reply_status' => ReplyStatus::PENDING,

        // Timestamps
        'deleted_at' => null
    ];

    protected $dates = [
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
    // Relationship Begins
    // -----------------------------

    public function requestedAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'requested_by_account_id'
        );
    }

    public function approvedAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'approved_by_account_id'
        );
    }

    public function requestedWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'requested_warehouse_id'
        );
    }

    public function approvedWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'approved_warehouse_id'
        );
    }

    public function items(): EmbedsMany
    {
        return $this->embedsMany(
            RefillInventoryRequestItem::class,
            'items'
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getRequestedAccountAttribute(): array
    {
        $account = $this->requestedAccount()->first();

        return [
            'user_id' => optional($account)->user->id,
            'account_id' => optional($account)->_id,
            'username' => optional($account)->username,
            'avatar' => optional($account)->avatar
        ];
    }

    public function getApprovedAccountAttribute(): array
    {
        $account = $this->approvedAccount()->first();

        return [
            'user_id' => optional($account)->user->id,
            'account_id' => optional($account)->_id,
            'username' => optional($account)->username,
            'avatar' => optional($account)->avatar
        ];
    }

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function associateRequestedAccount(Account $account): bool
    {
        $this->requestedAccount()->associate($account);
        return $this->save();
    }

    public function associateApprovedAccount(Account $account): bool
    {
        $this->approvedAccount()->associate($account);
        return $this->save();
    }

    public function associateRequestedWarehouse(Warehouse $warehouse): bool
    {
        $this->requestedWarehouse()->associate($warehouse);
        return $this->save();
    }

    public function associateApprovedWarehouse(Warehouse $warehouse): bool
    {
        $this->approvedWarehouse()->associate($warehouse);
        return $this->save();
    }

    public function updateReplyStatus(string $status): bool
    {
        $this->reply_status = $status;
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
