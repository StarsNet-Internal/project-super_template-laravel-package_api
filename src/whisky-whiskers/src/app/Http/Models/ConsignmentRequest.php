<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Models;

// Constants
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

class ConsignmentRequest extends Eloquent
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
    protected $collection = 'consignment_requests';

    protected $attributes = [
        // Relationships
        'requested_by_account_id' => null,
        'approved_by_account_id' => null,

        // Default
        'requester_name' => null,
        'email' => null,
        'area_code' => null,
        'phone' => null,
        'shipping_address' => null,
        'items' => [],
        'requested_items_qty' => 0,
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

    public function items(): EmbedsMany
    {
        return $this->embedsMany(
            ConsignmentRequestItem::class,
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

    public function updateReplyStatus(string $status): bool
    {
        $this->reply_status = $status;
        return $this->save();
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
