<?php

namespace StarsNet\Project\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\MembershipPointHistoryType;
use App\Traits\Model\Expirable;
use App\Models\MembershipPointHistory;
// Traits
use App\Traits\Model\ObjectIDTrait;
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

class RefundCreditHistory extends MembershipPointHistory
{
    // use ObjectIDTrait,
    //     Expirable;

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
    protected $collection = 'refund_credit_history';

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
    // Action Begins
    // -----------------------------

    // -----------------------------
    // Action Ends
    // -----------------------------
}
