<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;

class ConsignmentRequestController extends Controller
{
    public function getAllConsignmentRequests(Request $request)
    {
        return ConsignmentRequest::where('requested_by_customer_id', $this->customer()->_id)
            ->with(['items'])
            ->get();
    }

    public function createConsignmentRequest(Request $request)
    {
        // Create ConsignmentRequest
        $form = ConsignmentRequest::create($request->except('items'));

        // Create ConsignmentRequestItem(s)
        $requestItemsCount = 0;
        foreach ($request->items as $item) {
            $form->items()->create($item);
            $requestItemsCount++;
        }
        $form->update([
            'requested_by_customer_id' => $this->customer()->_id,
            'requested_items_qty' => $requestItemsCount
        ]);

        return response()->json([
            'message' => 'Created New ConsignmentRequest successfully',
            '_id' => $form->_id
        ], 200);
    }

    public function getConsignmentRequestDetails(Request $request)
    {
        $consignmentRequestId = $request->route('consignment_request_id');

        $form = ConsignmentRequest::find($consignmentRequestId);

        if (is_null($form)) {
            return response()->json([
                'message' => 'Consignment Request not found'
            ], 404);
        }

        $customer = $this->customer();

        if ($form->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'Access denied'
            ], 404);
        }

        $form->items = $form->items()->get();

        return $form;
    }
}
