<?php

namespace StarsNet\Project\Ops\App\Models;

// Constants
use App\Constants\CollectionName;

// Traits
use App\Traits\Model\ObjectIDTrait;

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

class TemplateComponent extends Eloquent
{
    use ObjectIDTrait;

    /**
     * Define database connection.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    protected $attributes = [
        // Default
        'x' => 0,
        'y' => 0,
        'w' => 0,
        'h' => 0,
        'component' => null,
        'component_props' => [],
        'store' => null,
        'action' => null,
        'payload' => [],
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

    // -----------------------------
    // Action Ends
    // -----------------------------
}
