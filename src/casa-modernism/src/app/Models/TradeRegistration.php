<?php

namespace StarsNet\Project\CasaModernism\App\Models;

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

use App\Models\Customer;
use App\Models\RefundRequestReply;
use App\Models\User;

class TradeRegistration extends Eloquent
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
    protected $collection = 'trade_registrations';

    protected $attributes = [
        // Relationships
        'customer_id' => null,

        // Default
        'reason' => null,
        'images' => [],
        'remarks' => null,
        'status' => Status::ACTIVE,
        'reply_status' => ReplyStatus::PENDING,

        // Sub-documents
        'replies' => [],

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function replies(): EmbedsMany
    {
        $localKey = 'replies';

        return $this->embedsMany(
            RefundRequestReply::class,
            $localKey
        );
    }

    // -----------------------------
    // Relationship Ends
    // -----------------------------

    // -----------------------------
    // Accessor Begins
    // -----------------------------

    public function getRepliesAttribute(): Collection
    {
        return $this->replies()->get();
    }

    // -----------------------------
    // Accessor Ends
    // -----------------------------

    // -----------------------------
    // Action Begins
    // -----------------------------

    public function associateCustomer(Customer $customer): bool
    {
        $this->customer()->associate($customer);
        return $this->save();
    }

    // TODO: Modify reply_status
    public function reply(User $user, array $attributes): RefundRequestReply
    {
        $replyAttributes = [
            'images' => $attributes['images'],
            'comment' => $attributes['comment'],
            'status' => Status::ACTIVE
        ];

        /** @var RefundRequestReply $reply */
        $reply = $this->replies()->create($replyAttributes);

        // Update RefundRequestReply
        $reply->associateUser($user);

        return $reply;
    }

    public function updateReplyStatus(string $status): bool
    {
        $this->reply_status = $status;
        return $this->save();
    }

    public function hasApprovedOrRejected(): bool
    {
        if (is_null($this->reply_status)) return false;
        return in_array($this->reply_status, [
            ReplyStatus::APPROVED,
            ReplyStatus::REJECTED,
        ]);
    }

    // -----------------------------
    // Action Ends
    // -----------------------------

}
