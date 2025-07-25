<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;
use Illuminate\Support\Facades\Auth;

class ConsignmentRequestController extends Controller
{
    public function createConsignmentRequest(Request $request)
    {
        $customer = $this->customer();
        $data = array_merge($request->all(), ['requested_by_customer_id' => $customer->id]);
        $form = ConsignmentRequest::create($data);

        return response()->json([
            'message' => 'Created New ConsignmentRequest successfully',
            '_id' => $form->_id
        ], 200);
    }
}
