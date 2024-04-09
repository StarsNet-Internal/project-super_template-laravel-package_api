<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Constants\Model\MembershipPointHistoryType;
use App\Http\Controllers\Controller;
use App\Models\MembershipPoint;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ProfileController extends Controller
{
    use AuthenticationTrait;

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
        $userId = $request->area_code . $request->phone;
        $remarks = $request->remarks;

        // Get authenticated User information
        $user = $this->user();
        $customer = $this->customer();

        if (!$customer->isEnoughMembershipPoints($point)) {
            return response()->json([
                'message' => 'Customer does not have enough membership points for this transaction',
            ], 403);
        }

        $user = User::where('login_id', $userId)->first();
        if (is_null($user)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $description = [
            'en' => 'Transferred from ' . $user->login_id,
            'zh' => 'Transferred from ' . $user->login_id,
            'cn' => 'Transferred from ' . $user->login_id
        ];
        MembershipPoint::createByCustomer(
            $user->account->customer,
            $point,
            MembershipPointHistoryType::GIFT,
            now()->addYears(2),
            $description,
            $remarks
        );

        // Get and filter MembershipPoint records
        $points = $customer->getAvailableMembershipPointRecords();
        $availablePoints =
            $points->filter(function ($point) {
                return $point->earned > $point->used;
            });

        // Create MembershipPointHistory    
        $attributes = [
            'type' => MembershipPointHistoryType::GIFT,
            'value' => -1 * abs($point),
            'description' => [
                'en' => 'Transferred to ' . $userId,
                'zh' => 'Transferred to ' . $userId,
                'cn' => 'Transferred to ' . $userId
            ],
            'remarks' => $remarks,
        ];
        $historyRecord = $customer->membershipPointHistories()->create($attributes);

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
