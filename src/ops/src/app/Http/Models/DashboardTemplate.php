<?php

namespace StarsNet\Project\Ops\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\Status;

use App\Models\Account;
use StarsNet\Project\Ops\App\Models\TemplateComponent;

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

class DashboardTemplate extends Eloquent
{
    use ObjectIDTrait, StatusFieldTrait;

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
    protected $collection = 'dashboard_templates';

    protected $attributes = [
        // Relationships
        'account_ids' => [],

        // Default
        'title' => null,
        'components' => [],
        'status' => Status::ACTIVE,

        // Timestamps
        'deleted_at' => null,
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
    // Scope Begins
    // -----------------------------

    // -----------------------------
    // Scope Ends
    // -----------------------------

    // -----------------------------
    // Relationship Begins
    // -----------------------------

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(
            Account::class
        );
    }

    public function templateComponents(): EmbedsMany
    {
        $localKey = 'components';

        return $this->embedsMany(
            TemplateComponent::class,
            $localKey
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

    public function createComponent(array $attributes): TemplateComponent
    {
        $item = $this->templateComponents()->create($attributes);
        return $item;
    }

    public function attachAccounts(Collection $accounts): void
    {
        $accountIds = $accounts->pluck('_id')->all();
        $this->accounts()->attach($accountIds);
        return;
    }

    // -----------------------------
    // Action Ends
    // -----------------------------
}
