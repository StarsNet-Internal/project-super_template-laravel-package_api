<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;

class ConsignmentRequestController extends Controller
{
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
            'requested_items_qty' => $requestItemsCount
        ]);

        return response()->json([
            'message' => 'Created New ConsignmentRequest successfully',
            '_id' => $form->_id
        ], 200);
    }

    private function filterConsignmentRequests(array $queryParams = []): Collection
    {
        // Exclude all deleted documents first
        $query = ConsignmentRequest::where('status', '!=', Status::DELETED);

        // Chain all string matching query
        foreach ($queryParams as $key => $value) {
            if (in_array($key, ['per_page', 'page', 'sort_by', 'sort_order'])) {
                continue;
            }

            $query = $query->where($key, $value);
        }

        return $query->with([
            'requestedCustomer',
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
        $consignment = ConsignmentRequest::with([
            'requestedCustomer',
            'approvedAccount',
            'items'
        ])->find($request->route('id'));

        return $consignment;
    }

    public function updateConsignmentRequestDetails(Request $request)
    {
        $consignmentID = $request->route('id');

        $consignment = ConsignmentRequest::find($consignmentID);

        if (is_null($consignment)) {
            return response()->json([
                'message' => 'ConsignmentRequest not found'
            ], 404);
        }

        $attributes = $request->all();
        $consignment->update($attributes);

        return response()->json([
            'message' => 'ConsignmentRequest updated successfully'
        ]);
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
            if ($item['is_approved'] == true) $approvedItemCount++;
        }

        // Update ConsignmentRequest
        $formAttributes = [
            "requested_by_account_id" => $request->requested_by_account_id,
            "approved_items_qty" => $approvedItemCount,
            "reply_status" => $request->reply_status,
        ];
        $formAttributes = array_filter($formAttributes, function ($value) {
            return !is_null($value) && $value != "";
        });
        $form->update($formAttributes);

        return response()->json([
            'message' => 'Approved ConsignmentRequest successfully',
            '_id' => $form->_id,
            'requested_items_qty' => $form->requested_items_qty,
            'approved_items_qty' => $approvedItemCount
        ], 200);
    }
}
