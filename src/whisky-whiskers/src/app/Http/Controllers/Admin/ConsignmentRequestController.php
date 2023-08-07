<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class ConsignmentRequestController extends Controller
{
    private function filterConsignmentRequests(array $queryParams = []): Collection
    {
        // Exclude all deleted documents first
        $query = ConsignmentRequest::where('status', '!=', Status::DELETED);

        // Chain all string matching query
        foreach ($queryParams as $key => $value) {
            $query = $query->where($key, $value);
        }

        return $query->with([
            'requestedAccount',
            'approvedAccount',
            'items'
        ])->get();
    }

    public function getAllConsignmentRequests(Request $request)
    {
        $forms = $this->filterConsignmentRequests($request->all());
        return $forms;
    }

    public function getConsignmentRequestDetails(Request $request)
    {
        $request = ConsignmentRequest::with([
            'requestedAccount',
            'approvedAccount',
            'items'
        ])->find($request->route('id'));

        return $request;
    }

    public function approveConsignmentRequest(Request $request)
    {
        $form = ConsignmentRequest::find($request->route('id'));

        // Associate relationships
        $form->associateApprovedAccount($this->account());

        // Update ConsignmentRequestItem(s)
        $approvedItemCount = 0;
        foreach ($request->items as $item) {
            $formItem = $form->items()->where('_id', $item['_id'])->first();
            if (is_null($formItem)) continue;
            unset($item['_id']);
            $formItem->update($item);
            // if ($formItem->requested_qty <= $item['approved_qty']) $approvedItemCount++;
            if ($item['is_approved'] == true) $approvedItemCount++;
        }
        $form->update([
            'approved_items_qty' => $approvedItemCount,
            'reply_status' => $request->reply_status
        ]);

        return response()->json([
            'message' => 'Approved ConsignmentRequest successfully',
            '_id' => $form->_id,
            'requested_items_qty' => $form->requested_items_qty,
            'approved_items_qty' => $approvedItemCount
        ], 200);
    }
}
