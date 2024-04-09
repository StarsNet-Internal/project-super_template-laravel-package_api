<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Customer;

use App\Constants\Model\MembershipPointHistoryType;
use App\Models\MembershipPoint;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
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
                    'en' => 'Distributed by Admin',
                    'zh' => 'Distributed by Admin',
                    'cn' => 'Distributed by Admin'
                ],
                $remarks,
            );
        }

        // Return success message
        return response()->json([
            'message' => 'Distributed MembershipPoint successfully',
        ], 200);
    }
}
