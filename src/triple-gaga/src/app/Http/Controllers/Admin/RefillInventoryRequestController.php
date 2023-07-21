<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Constants\Model\ReplyStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;
use StarsNet\Project\TripleGaga\Traits\Controllers\RefillInventoryRequestTrait;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\Warehouse;
use Illuminate\Http\Request;

class RefillInventoryRequestController extends Controller
{
    use RefillInventoryRequestTrait;

    public function createRefillInventoryRequest(Request $request)
    {
        // Create RefillInventoryRequest
        $refill = new RefillInventoryRequest();
        $refill->associateRequestedAccount($this->account());

        // Create RefillInventoryRequestItem(s)
        $requestItemsCount = 0;
        foreach ($request->items as $item) {
            // Validate request
            $variant = ProductVariant::find($item['product_variant_id']);
            if (is_null($variant)) continue;

            $product = $variant->product;
            if (is_null($product)) continue;

            // Create RefillInventoryRequestItem
            $refillItemAttribute = [
                'product_id' => $product->_id,
                'product_variant_id' => $variant->_id,
                'requested_qty' => $item['requested_qty']
            ];
            $refill->items()->create($refillItemAttribute);

            $requestItemsCount++;
        }
        $refill->update(['requested_items_qty' => $requestItemsCount]);

        // Update relationship
        $requestedWarehouse = Warehouse::find($request->requested_warehouse_id);
        if (!is_null($requestedWarehouse)) $refill->associateRequestedWarehouse($requestedWarehouse);

        return response()->json([
            'message' => 'Created New RefillInventoryRequest successfully',
            '_id' => $refill->_id
        ], 200);
    }

    public function getRefillInventoryRequests(Request $request)
    {
        $refills = $this->filterRefillInventoryRequests($request->all());
        return $refills;
    }

    public function getRefillInventoryRequestDetails(Request $request)
    {
        $refill = RefillInventoryRequest::find($request->route('id'))
            ->with([
                'requestedAccount',
                'approvedAccount',
                'requestedWarehouse',
                'approvedWarehouse',
                'items'
            ])->first();

        return $refill;
    }

    public function approveRefillInventoryRequest(Request $request)
    {
        $refill = RefillInventoryRequest::find($request->route('id'));
        $validProductVariantIds = collect($refill->items)->pluck('product_variant_id')->all();

        // Validate Request
        $validator = Validator::make($request->all(), [
            'items' => [
                'required',
                'array'
            ],
            'items.*.product_variant_id' => [
                Rule::in($validProductVariantIds)
            ],
            'reply_status' => [
                'required',
                Rule::in(ReplyStatus::$defaultTypes)
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Associate relationships
        $refill->associateApprovedAccount($this->account());

        $approvedWarehouse = Warehouse::find($request->approved_warehouse_id ?? $refill->requested_warehouse_id);
        $refill->associateApprovedWarehouse($approvedWarehouse);

        // Update RefillInventoryRequestItem(s)
        $approvedItemCount = 0;
        foreach ($request->items as $item) {
            $refillItem = $refill->items()->where('product_variant_id', $item['product_variant_id'])->first();
            if (is_null($refillItem)) continue;
            $refillItem->update(['approved_qty' => $item['approved_qty']]);
            if ($refillItem->requested_qty <= $item['approved_qty']) $approvedItemCount++;
        }

        // Update attributes
        $updateAttributes = [
            'approved_items_qty' => $approvedItemCount,
            'reply_status' => $request->reply_status
        ];
        $refill->update($updateAttributes);

        return response()->json([
            'message' => 'Approved RefillInventoryRequest successfully',
            '_id' => $refill->_id,
            'requested_items_qty' => $refill->requested_items_qty,
            'approved_items_qty' => $approvedItemCount
        ], 200);
    }

    public function deleteRefillInventoryRequest(Request $request)
    {
        $refill = RefillInventoryRequest::find($request->route('id'));
        $refill->statusDeletes();

        // Return success message
        return response()->json([
            'message' => 'Deleted RefillInventoryRequest successfully'
        ], 200);
    }
}
