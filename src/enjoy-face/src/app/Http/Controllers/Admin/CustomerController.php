<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Customer;

use App\Constants\Model\MembershipPointHistoryType;
use App\Models\MembershipPoint;
use Illuminate\Support\Facades\Validator;
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectPostTrait;

class CustomerController extends Controller
{
    use ProjectPostTrait;

    public function distributeMembershipPoint(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'point' => [
                'required',
                'integer',
                'min:1'
            ],
            'customer_ids' => [
                'required',
                'array'
            ],
            'customer_ids.*' => [
                'exists:App\Models\Customer,_id'
            ],
            'account_ids' => [
                'required',
                'array'
            ],
            'account_ids.*' => [
                'exists:App\Models\Account,_id'
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
        $remarks = $request->remarks;

        $customers = Customer::find($request->customer_ids);
        foreach ($customers as $customer) {
            MembershipPoint::createByCustomer(
                $customer,
                $point,
                MembershipPointHistoryType::GIFT,
                now()->addYears(2),
                [
                    'en' => 'Received points from Administrator',
                    'zh' => '收到來自管理員的積分',
                    'cn' => '收到来自管理员的积分'
                ],
                $remarks,
            );
        }

        // Inbox
        $inboxAttributes = [
            'title' => [
                'en' => 'You have received ' . $point . ' membership points from the administrator',
                'zh' => '您已收到管理員贈送的 ' . $point . ' 會員積分',
                'cn' => '您已收到管理员赠送的 ' . $point . ' 会员积分',
            ],
            'short_description' => [
                'en' => $remarks,
                'zh' => $remarks,
                'cn' => $remarks,
            ],
        ];
        $this->createInboxPost($inboxAttributes, [$request->account_ids], false);

        // Return success message
        return response()->json([
            'message' => 'Distributed MembershipPoint successfully',
        ], 200);
    }
}
