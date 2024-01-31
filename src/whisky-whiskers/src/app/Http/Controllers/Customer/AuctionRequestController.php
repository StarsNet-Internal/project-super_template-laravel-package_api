<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionRequest;

class AuctionRequestController extends Controller
{
    public function getAllAuctionRequests(Request $request)
    {
        $customer = $this->customer();

        $forms = AuctionRequest::where('requested_by_customer_id', $customer->_id)
            ->with(['store', 'product'])
            ->get();

        return $forms;
    }

    public function createAuctionRequest(Request $request)
    {
        // Extract attributes from $request
        $productvariantId = $request->product_variant_id;
        $storeId = $request->store_id;

        // Get ProductVariant
        $variant = ProductVariant::find($productvariantId);

        if (is_null($variant)) {
            return response()->json([
                'message' => 'Variant not found'
            ], 404);
        }

        // Get Product
        $product = $variant->product;
        $customer = $this->customer();

        if ($product->owned_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'This product does not belong to the customer'
            ], 404);
        }

        if ($product->listing_status != "AVAILABLE") {
            return response()->json([
                'message' => 'This product can not apply for auction'
            ], 404);
        }

        // Get Auction/Store
        $store = Store::find($storeId);

        if (is_null($store)) {
            return response()->json([
                'message' => 'Auction not found'
            ], 404);
        }

        // Create AuctionRequest
        $updateAuctionRequestFields = [
            'product_id' => $product->_id,
            'product_variant_id' => $variant->_id,
            'store_id' => $store->_id,
            'starting_bid' => $request->input('starting_bid', 0),
            'reserve_price' => $request->input('reserve_price', 0),
        ];

        $form = AuctionRequest::create($updateAuctionRequestFields);
        $form->associateRequestedCustomer($customer);

        // Update Product
        $updateProductFields = [
            'listing_status' => 'PENDING_FOR_AUCTION'
        ];
        $product->update($updateProductFields);

        // Return message
        return response()->json([
            'message' => 'Created New AuctionRequest successfully',
            '_id' => $form->_id
        ], 200);
    }
}
