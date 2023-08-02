<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class ConsignmentRequestController extends Controller
{
    public function getAllConsignmentRequests(Request $request)
    {
        $account = $this->account();

        $forms = ConsignmentRequest::where('requested_by_account_id', $account->id)->get();
        return $forms;
    }

    public function createConsignmentRequest(Request $request)
    {
        // Create ConsignmentRequest
        $form = new ConsignmentRequest();
        $form->associateRequestedAccount($this->account());

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
}
