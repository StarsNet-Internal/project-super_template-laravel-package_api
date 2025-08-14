<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use StarsNet\Project\Auction\App\Models\ReferralCode;
use StarsNet\Project\Auction\App\Models\ReferralCodeHistory;

class ReferralCodeController extends Controller
{
    public function massGenerateReferralCodes(Request $request)
    {
        $quotaLeft = (int) $request->input('quota_left', 3);

        $validCustomerIDs = User::where('type', '!=', 'TEMP')
            ->with(['account.customer'])
            ->get()
            ->pluck('account.customer.id')
            ->filter() // Remove null values
            ->unique() // Ensure no duplicates
            ->values() // Reset keys
            ->all();

        $createdCount = 0;
        $existingCodes = ReferralCode::pluck('code')->toArray();

        foreach ($validCustomerIDs as $customerID) {
            do {
                $code = $this->generateUniqueCode(8, $existingCodes);
            } while (in_array($code, $existingCodes));

            ReferralCode::create([
                'customer_id' => $customerID,
                'code' => $code,
                'quota_left' => $quotaLeft
            ]);

            $existingCodes[] = $code; // Add to existing codes to prevent duplicates
            $createdCount++;
        }

        return ['message' => 'Created a total of ' . $createdCount . ' new referral codes'];
    }

    protected function generateUniqueCode(int $length, array $existingCodes): string
    {
        $characters = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ'; // No zero
        $max = strlen($characters) - 1;
        $code = '';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, $max)];
            }
        } while (in_array($code, $existingCodes));

        return $code;
    }

    public function randomlyUseCode(Request $request)
    {
        $customerID = $request->input('customer_id', '6872b937b7b34c87e20fedb1');
        $usedCount = (int) $request->input('used_count', 1);

        $referralCode = ReferralCode::where('customer_id', $customerID)->first();
        $referralCode->update(['quota_left' => max($referralCode->quota_left - $usedCount, 0)]);

        $validCustomerIDs = User::where('type', '!=', 'TEMP')
            ->whereHas('account.customer') // Only users with customer accounts
            ->inRandomOrder() // Database-level randomization
            ->take($usedCount) // Limit early
            ->with(['account.customer:id']) // Only load what we need
            ->get()
            ->pluck('account.customer.id')
            ->filter()
            ->values()
            ->all();

        for ($i = 0; $i < $usedCount; $i++) {
            ReferralCodeHistory::create([
                'owned_by_customer_id' => $customerID,
                'used_by_customer_id' => $validCustomerIDs[$i],
                'referral_code_id' => $referralCode->id,
                'code' => $referralCode->code,
            ]);
        }

        return ['message' => 'Used code for customer_id of ' . $customerID . ' for ' . $usedCount . ' time(s).'];
    }
}
