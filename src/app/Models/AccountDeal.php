<?php

namespace StarsNet\Project\App\Models;

// Constants
use App\Models\Account;

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

class AccountDeal extends Eloquent
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
    protected $collection = 'account_deals';

    protected $attributes = [
        // Relationships
        'account_id' => null,
        'deal_ids' => [],

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

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(
            Deal::class,
        );
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            Account::class
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

    public function associateAccount(Account $account): bool
    {
        $this->account()->associate($account);
        return $this->save();
    }

    public function dissociateAccount(): bool
    {
        $this->account()->dissociate();
        return $this->save();
    }

    public function attachDeals(Collection $deals): void
    {
        $dealIDs = $deals->pluck('_id')->all();
        $this->deals()->attach($dealIDs);
        return;
    }

    public function detachDeals(Collection $deals): int
    {
        $dealIDs = $deals->pluck('_id')->all();
        return $this->deals()->detach($dealIDs);
    }

    public function syncDeals(Collection $deals): array
    {
        $dealIDs = $deals->pluck('_id')->all();
        return $this->deals()->sync($dealIDs);
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
