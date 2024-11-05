<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;

use App\Models\ProductVariant;
use App\Models\Store;
use Carbon\Carbon;

use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;

use Illuminate\Http\Request;

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

        // Check if auto-approve needed
        $now = now();
        $upcomingStores = Store::where(
            'type',
            StoreType::OFFLINE
        )
            ->statuses([Status::ARCHIVED, Status::ACTIVE])
            ->orderBy('start_datetime')
            ->get();

        $nearestUpcomingStore = null;
        foreach ($upcomingStores as $store) {
            $startTime = $store->start_datetime;
            $startTime = Carbon::parse($startTime);
            if ($now < $startTime) {
                $nearestUpcomingStore = $store;
                break;
            }
        }

        if (!is_null($nearestUpcomingStore) && $nearestUpcomingStore->_id == $storeId) {
            $form->update([
                'reply_status' => ReplyStatus::APPROVED,
                'is_in_auction' => true
            ]);

            $updateProductFields = [
                'listing_status' => 'LISTED_IN_AUCTION'
            ];
            $product->update($updateProductFields);

            // Create auction_lot
            $auctionLotFields = [
                'auction_request_id' => $form->_id,
                'owned_by_customer_id' => $customer->_id,
                'product_id' => $form->product_id,
                'product_variant_id' => $form->product_variant_id,
                'store_id' => $form->store_id,
                'starting_price' => $form->starting_bid ?? 0,
                'current_bid' => $form->starting_bid ?? 0,
                'reserve_price' => $form->reserve_price ?? 0,
            ];
            $auctionLot = AuctionLot::create($auctionLotFields);

            BidHistory::create([
                'auction_lot_id' => $auctionLot->_id,
                'current_bid' => $auctionLot->starting_price,
                'histories' => []
            ]);
        } else {
            // Update Product
            $updateProductFields = [
                'listing_status' => 'PENDING_FOR_AUCTION',
            ];
            $product->update($updateProductFields);
        }

        // Return message
        return response()->json([
            'message' => 'Created New AuctionRequest successfully',
            '_id' => $form->_id
        ], 200);
    }
}
