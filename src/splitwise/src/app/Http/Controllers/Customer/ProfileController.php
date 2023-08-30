<?php

namespace StarsNet\Project\Splitwise\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use AuthenticationTrait, RoundingTrait;

    public function getOrderStatistics()
    {
        // Get authenticated User information
        $pendingOrders = $this->customer()->orders()->whereCurrentStatus('pending');
        $unsettledOrders = $this->customer()->orders()->whereCurrentStatuses(['submitted', 'pending', 'processing', 'delivering']);

        $totalSpent = $unsettledOrders->get()->sum('calculations.price.total');
        $pendingOrderCount = $pendingOrders->count();

        return [
            'unsettled_claims' => $this->roundingValue($totalSpent),
            'pending_approval' => $pendingOrderCount,
        ];
    }
}
