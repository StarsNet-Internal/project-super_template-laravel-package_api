<?php

namespace StarsNet\Project\Capi\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;

use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use StarsNet\Project\Capi\App\Models\RefundCredit;
use StarsNet\Project\Capi\App\Models\RefundCreditHistory;

class ProfileController extends CustomerProfileController
{
    use AuthenticationTrait;

    public function getCreditStatus(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Get RefundCredits
        $data = [
            'membership_status' => 'VIP',
            'available_points' => $this->getAvailableRefundCredits(),
            'total_points_earned' => $this->getTotalRefundCreditsEarned(),
        ];

        // Return success message
        return response()->json($data);
    }

    public function getCreditBalance(Request $request)
    {
        return $this->getAvailableRefundCreditRecords()
            ->filter(function ($point) {
                return $point->available > 0;
            });
    }

    public function getCreditTransactions(Request $request)
    {
        $history = RefundCreditHistory::where('customer_id', $this->customer()->_id)
            ->get();

        return $history;
    }

    public function getAllRefundCreditRecords()
    {
        $records = RefundCredit::where('customer_id', $this->customer()->_id)
            ->get();

        return $records;
    }

    public function getAvailableRefundCreditRecords()
    {
        $records = RefundCredit::where('customer_id', $this->customer()->_id)
            ->whereAvailableForUse()
            ->get();

        return $records;
    }

    public function getTotalRefundCreditsEarned()
    {
        $points = $this->getAllRefundCreditRecords();
        return
            $points->sum(function ($point) {
                return $point->earned;
            });
    }

    public function getAvailableRefundCredits(): int
    {
        $points = $this->getAvailableRefundCreditRecords();
        return
            $points->sum(function ($point) {
                return $point->earned - $point->used;
            });
    }
}
