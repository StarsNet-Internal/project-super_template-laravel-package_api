<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;
use StarsNet\Project\TripleGaga\Traits\Controllers\RefillInventoryRequestTrait;

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
        $refill->update(['request_items_qty', $requestItemsCount]);

        // Update relationship
        $requestedWarehouse = Warehouse::find($request->requested_warehouse_id);
        if (!is_null($requestedWarehouse)) $refill->associateRequestedWarehouse($requestedWarehouse);

        return $refill;
    }

    public function getRefillInventoryRequests(Request $request)
    {
        $refills = $this->filterRefillInventoryRequests($request->all());
        return $refills;
    }

    public function getRefillInventoryRequestDetails(Request $request)
    {
        $refill = RefillInventoryRequest::find($request->route('id'));
        $refill = $this->getRefillInventoryRequestFullDetails($refill);
        return $refill;
    }

    public function approveRefillInventoryRequest(Request $request)
    {
        // Associate relationships
        $refill = RefillInventoryRequest::find($request->route('id'));
        $refill->associateApprovedAccount($this->account());

        $approvedWarehouse = Warehouse::find($request->approved_warehouse_id ?? $refill->requested_warehouse_id);
        $refill->associateApprovedWarehouse($approvedWarehouse);

        // Update RefillInventoryRequestItem(s)
        $approvedItemCount = 0;
        foreach ($request->items as $item) {
            $refillItem = $refill->items()->where('product_variant_id', $item['product_variant_id'])->first();
            $refillItem->update(['approved_qty' => $item['approved_qty']]);
            if ($refillItem->requested_qty <= $item['approved_qty']) $approvedItemCount++;
        }

        // Update attributes
        $updateAttributes = [
            'approved_items_qty' => $approvedItemCount,
            'reply_status' => $request->reply_status
        ];
        $refill->update($updateAttributes);

        return $refill;
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
