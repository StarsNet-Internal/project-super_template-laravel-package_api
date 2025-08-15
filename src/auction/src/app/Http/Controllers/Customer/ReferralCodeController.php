<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Customer;

use App\Constants\Model\ReplyStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Customer;
use Illuminate\Support\Collection;

use StarsNet\Project\Auction\App\Models\ReferralCode;
use StarsNet\Project\Auction\App\Models\ReferralCodeHistory;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;

class ReferralCodeController extends Controller
{
    protected $storeID = '689578c16a72a5fd1f0ee7c6';

    private function getReferralCodeInfo($customerIDs, ?string $storeID = null): Collection
    {
        $customerIDs = (array) $customerIDs;
        if (count($customerIDs) === 0) return new Collection();

        $customers = Customer::objectIDs($customerIDs)
            ->with(['account.user'])
            ->get();

        $storeID = $storeID ?? $this->storeID;
        $auctionRegistrationRequests = AuctionRegistrationRequest::whereIn('requested_by_customer_id', $customerIDs)
            ->where('store_id', $storeID)
            ->latest()
            ->get()
            ->keyBy('requested_by_customer_id');

        // Append Keys
        foreach ($customers as $customer) {
            // Auction Registration status
            $customerRequest = $auctionRegistrationRequests[$customer->id] ?? null;
            if (!is_null($customerRequest) && $customerRequest->reply_status === ReplyStatus::APPROVED) {
                $customer->auction_registered_at = optional($customerRequest)->updated_at;
                $customer->is_registered_auction = true;
            } else {
                $customer->auction_registered_at = null;
                $customer->is_registered_auction = false;
            }

            // Bind card status
            $customer->stripe_card_binded_at = $customer->stripe_card_binded_at;
            $customer->is_binded_card = !is_null($customer->stripe_card_binded_at);

            // If no card binded, then also check for APPROVED Deposit
            if (!is_null($customerRequest) && $customer->is_binded_card === false) {
                $isApprovedDepositExists = Deposit::where('requested_by_customer_id', $customer->id)
                    ->where('auction_registration_request_id', $customerRequest->id)
                    ->where('reply_status', ReplyStatus::APPROVED)
                    ->exists();
                $customer->is_binded_card = $isApprovedDepositExists;
            }

            // Referral validity
            $customer->is_referral_valid = $customer->is_registered_auction && $customer->is_binded_card;
        }

        return $customers;
    }

    public function getReferralCodeDetails(Request $request): array
    {
        $customer = $this->customer();
        $referralCode = ReferralCode::where('customer_id', $customer->id)
            ->latest()
            ->first();

        if (is_null($referralCode)) abort(404, 'ReferralCode not found');

        $referralCodeInfo = $this->getReferralCodeInfo($customer->id, $request->store_id);

        if ($referralCodeInfo->count() > 0) {
            $progress = $referralCodeInfo[0];
            if ($progress['is_referral_valid'] === false)  $referralCode = null;

            return [
                'referral_code' => $referralCode,
                'personal_progress' => $progress,
            ];
        }

        return [
            'referral_code' => null,
            'personal_progress' => null,
        ];
    }

    public function getAllReferralCodeHistories(Request $request): Collection
    {
        $customer = $this->customer();

        $referralCode = ReferralCode::where('customer_id', $customer->id)
            ->latest()
            ->first();

        if (is_null($referralCode)) return new Collection();

        $referredCustomerIDs = ReferralCodeHistory::where('owned_by_customer_id', $customer->id)
            ->where('code', $referralCode->code)
            ->pluck('used_by_customer_id')
            ->unique()
            ->values()
            ->all();

        return $this->getReferralCodeInfo($referredCustomerIDs, $request->store_id);
    }
}
