<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Constants\Model\MembershipPointHistoryType;
use App\Http\Controllers\Controller;
use App\Models\MembershipPoint;
use App\Models\MembershipPointHistory;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectPostTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ProfileController extends Controller
{
    use AuthenticationTrait, ProjectPostTrait;

    public function transferMembershipPoint(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'point' => [
                'required',
                'integer',
                'min:1'
            ],
            'area_code' => [
                'required',
                'numeric'
            ],
            'phone' => [
                'required',
                'numeric'
            ],
            'remarks' => [
                'nullable'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes
        $point = $request->input('point', 1);
        $toLoginId = $request->area_code . $request->phone;
        $remarks = $request->remarks;
        $remarks = strval($remarks);

        // Get authenticated User information
        $fromAccount = $this->account();
        $fromCustomer = $this->customer();

        if (!$fromCustomer->isEnoughMembershipPoints($point)) {
            return response()->json([
                'message' => 'Customer does not have enough membership points for this transaction',
            ], 403);
        }

        if ($fromAccount->login_id === $toLoginId) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $toUser = User::where('login_id', $toLoginId)->first();
        if (is_null($toUser)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $toAccount = $toUser->account;
        $toCustomer = $toAccount->customer;
        $membershipPoint = MembershipPoint::create([
            'earned' => $point,
            'remarks' => $remarks,
            'expires_at' => now()->addYears(2)
        ]);
        $membershipPoint->associateCustomer($toCustomer);

        // Create MembershipPointHistory
        $history = MembershipPointHistory::create([
            'type' => MembershipPointHistoryType::GIFT,
            'value' => $point,
            'description' => [
                'en' => 'Received points from ' . $fromAccount->username,
                'zh' => '收到來自' . $fromAccount->username . '的積分',
                'cn' => '收到来自' . $fromAccount->username . '的积分',
            ],
            'remarks' => $remarks,
        ]);
        $history->setExpiresAt(now()->addYears(2));
        $history->associateCustomer($toCustomer);

        // Get and filter MembershipPoint records
        $points = $fromCustomer->getAvailableMembershipPointRecords();
        $availablePoints =
            $points->filter(function ($point) {
                return $point->earned > $point->used;
            });

        // Create MembershipPointHistory    
        $attributes = [
            'type' => MembershipPointHistoryType::GIFT,
            'value' => -1 * abs($point),
            'description' => [
                'en' => 'Gave points to ' . $toAccount->username,
                'zh' => '給予' . $toAccount->username . '積分',
                'cn' => '给予' . $toAccount->username . '积分',
            ],
            'remarks' => $remarks,
        ];
        $fromCustomer->membershipPointHistories()->create($attributes);

        // Inbox
        $inboxAttributes = [
            'title' => [
                'en' => 'You have received ' . $point . ' membership points from ' . $fromAccount->username,
                'zh' => '您已收到來自' . $fromAccount->username . '的 ' . $point . ' 會員積分',
                'cn' => '您已收到来自' . $fromAccount->username . '的 ' . $point . ' 会员积分',
            ],
            'short_description' => [
                'en' => $remarks,
                'zh' => $remarks,
                'cn' => $remarks,
            ],
        ];
        $this->createInboxPost($inboxAttributes, [$toAccount->_id], false);

        // Deduct required points 
        $remainder = $point;
        foreach ($availablePoints as $record) {
            if ($remainder <= 0) break;

            $point = $record['earned'] - $record['used'];

            // Deduct points
            $usedPoints = $remainder > $point ?
                $point :
                $remainder;

            // Update MembershipPoint    
            $record->usePoints($usedPoints);

            // Update remainder points
            $remainder -= $usedPoints;
        }

        // Return success message
        return response()->json([
            'message' => 'Transferred MembershipPoint successfully',
        ], 200);
    }
}
