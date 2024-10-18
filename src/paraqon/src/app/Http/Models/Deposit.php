<?php

namespace StarsNet\Project\Paraqon\App\Models;

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
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Model\NestedAttributeTrait;
use StarsNet\Project\Paraqon\App\Models\Bid;
use Illuminate\Support\Str;

class Deposit extends Eloquent
{
    use ObjectIDTrait,
        StatusFieldTrait,
        NestedAttributeTrait;

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
    protected $collection = 'deposits';

    protected $attributes = [
        // Relationships
        'requested_by_customer_id' => null,
        'auction_registration_request_id' => null,
        'approved_by_account_id' => null,

        // Default
        'payment_method' => 'OFFLINE',
        'amount' => null,
        'amount_captured' => null,
        'amount_refunded' => null,
        'currency' => null,
        'online' => [
            'payment_intent_id' => null,
            'client_secret' => null,
            'api_response' => null
        ],
        'offline' => [
            'image' => null,
            'uploaded_at' => null,
            'api_response' => null
        ],
        'payment_information' => [
            'currency' => 'HKD',
            'conversion_rate' => '100'
        ],
        'current_deposit_status' => null,
        'deposit_statuses' => [],
        'status' => Status::ACTIVE,
        'reply_status' => ReplyStatus::PENDING,
        'remarks' => null,

        // Timestamps
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

    public function requestedCustomer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
            'requested_by_customer_id'
        );
    }

    public function approvedAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'approved_by_account_id'
        );
    }

    public function auctionRegistrationRequest(): BelongsTo
    {
        return $this->belongsTo(
            AuctionRegistrationRequest::class,
            'auction_registration_request_id'
        );
    }

    public function depositStatuses(): EmbedsMany
    {
        $localKey = 'deposit_statuses';

        return $this->embedsMany(
            DepositStatus::class,
            $localKey
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

    public function updateStatus(string $slug, ?string $remarks = ""): DepositStatus
    {
        $slug = Str::slug($slug);

        // Update status
        $attributes = [
            'slug' => $slug,
            'remarks' => $remarks
        ];
        $status = $this->depositStatuses()->create($attributes);

        // Update current_status
        $this->updateCurrentStatus($slug);

        return $status;
    }

    public function updateCurrentStatus(string $slug): bool
    {
        $slug = Str::slug($slug);
        $this->current_deposit_status = $slug;
        return $this->save();
    }

    public function updateOnlineResponse($response): bool
    {
        $attributes = [
            'online.api_response' => $response
        ];
        return $this->updateNestedAttributes($attributes);
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
