<?php

namespace StarsNet\Project\App\Models;

// Constants
use App\Constants\CollectionName;
use App\Constants\Model\MembershipPointHistoryType;
use App\Models\Customer;
use App\Models\MembershipPoint;
// Traits
use App\Traits\Model\DisableTrait;
use App\Traits\Model\Expirable;
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

class RefundCredit extends MembershipPoint
{
    // use ObjectIDTrait,
    //     DisableTrait,
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
    protected $collection = 'refund_credits';

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

    public static function createByCustomer(
        Customer $customer,
        int $points,
        string $type = MembershipPointHistoryType::OTHERS,
        Carbon $expiresAt,
        ?array $description = null,
        ?string $remarks = null
    ): ?self {
        // Validate parameters
        if ($points === 0) return null;

        // Create MembershipPoint
        $pointAttributes = [
            'earned' => $points,
            'remarks' => $remarks,
            'expires_at' => $expiresAt
        ];
        $pointAttributes = array_filter($pointAttributes); // Remove all null values
        $point = self::create($pointAttributes);
        $point->associateCustomer($customer);

        // Create MembershipPointHistory
        RefundCreditHistory::createByCustomer(
            $customer,
            $points,
            $type,
            $expiresAt,
            $description,
            $remarks
        );

        return $point;
    }
    // -----------------------------
    // Action Ends
    // -----------------------------
}
