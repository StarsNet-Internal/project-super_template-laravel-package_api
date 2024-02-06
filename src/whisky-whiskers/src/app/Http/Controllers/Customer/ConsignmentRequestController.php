<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;
use Illuminate\Support\Facades\Auth;

class ConsignmentRequestController extends Controller
{
    public function getAllConsignmentRequests(Request $request)
    {
        $account = $this->account();

        $forms = ConsignmentRequest::where('requested_by_account_id', $account->_id)
            ->with(['items'])
            ->get();

        return $forms;
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
        $form->update(['requested_items_qty' => $requestItemsCount]);

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

        $account = $this->account();

        if ($form->requested_by_account_id != $account->_id) {
            return response()->json([
                'message' => 'Access denied'
            ], 404);
        }

        $form->items = $form->items()->get();

        return $form;
    }
}
