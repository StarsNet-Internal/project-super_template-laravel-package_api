<?php

namespace StarsNet\Project\Splitwise\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Http\Request;
use StarsNet\Project\Splitwise\App\Traits\Controller\ProjectCustomerTrait;

class ProfileController extends Controller
{
    use AuthenticationTrait, RoundingTrait, ProjectCustomerTrait;

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

    public function getStaffInGroup(Request $request)
    {
        $customer = $this->customer();
        $groups = $customer->groups()->where('is_system', false)->first();

        $customers = $groups->customers()->with(
            [
                'account',
                'account.user'
            ]
        )->get();

        return $customers->first(function ($customer) {
            return $customer->account->user->is_staff;
        });
    }

    public function getMembershipStatus(Request $request)
    {
        // Get authenticated User information
        $staff = $this->getStaffInGroup($request);

        $points = $staff->getAllMembershipPointRecords();
        $totalPointsEarned = $points->sum(function ($point) {
            return $point->earned > 0 ? $point->earned : 0;
        });

        // Get MembershipPoints
        $data = [
            'membership_status' => 'VIP',
            'available_points' => $staff->getTotalMembershipPointsEarned(),
            'total_points_earned' => $totalPointsEarned,
        ];

        // Return success message
        return response()->json($data);
    }

    public function addCreditToAccount(Request $request)
    {
        // Extract attributes from $request
        $points = $request->points;
        $type = $request->input('type', 'OTHERS');

        $staff = $this->getStaffInGroup($request);

        $this->addOrDeductCredit($staff, $points, $type);

        // Return success message
        return response()->json([
            'message' => 'Updated Credit successfully'
        ], 200);
    }
}
