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
        // $pendingOrders = $this->customer()->orders()->whereCurrentStatus('pending');
        $pendingOrders = $this->customer()->orders()->whereCurrentStatuses(['submitted', 'pending']);
        $approvedOrders = $this->customer()->orders()->whereCurrentStatus('processing');
        $rejectedOrders = $this->customer()->orders()->whereCurrentStatus('delivering');

        // $pendingOrderCount = $pendingOrders->count();
        $totalPending = $pendingOrders->get()->sum('calculations.price.total');
        $totalApproved = $approvedOrders->get()->sum('calculations.price.total');
        $totalRejected = $rejectedOrders->get()->sum('calculations.price.total');

        return [
            'pending_claims' => $this->roundingValue($totalPending),
            'approved_claims' => $this->roundingValue($totalApproved),
            'rejected_claims' => $this->roundingValue($totalRejected),
        ];
    }
}
