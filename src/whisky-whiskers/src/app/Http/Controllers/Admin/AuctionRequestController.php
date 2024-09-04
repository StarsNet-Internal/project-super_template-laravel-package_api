<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionRequest;
use StarsNet\Project\WhiskyWhiskers\App\Models\BidHistory;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class AuctionRequestController extends Controller
{
    private function filterAuctionRequests(array $queryParams = []): Collection
    {
        // Exclude all deleted documents first
        $query = AuctionRequest::where('status', '!=', Status::DELETED);

        // Chain all string matching query
        foreach ($queryParams as $key => $value) {
            $query = $query->where($key, $value);
        }

        return $query->with([
            'requestedCustomer',
            'approvedAccount',
            'product',
        ])->get();
    }

    public function getAllAuctionRequests(Request $request)
    {
        $forms = $this->filterAuctionRequests($request->all());
        return $forms;
    }

    public function updateAuctionRequests(Request $request)
    {
        $form = AuctionRequest::find($request->route('id'));
        $form->update($request->all());

        return response()->json([
            'message' => 'Updated AuctionRequest successfully',
            '_id' => $form->_id,
        ], 200);
    }

    public function approveAuctionRequest(Request $request)
    {
        // Update reply_status
        $form = AuctionRequest::find($request->route('id'));
        $form->update(['reply_status' => $request->reply_status]);
        $wasInAuction = $form->is_in_auction;

        // Update form attributes
        $formAttributes = [
            "requested_by_account_id" => $request->requested_by_account_id,
            "starting_bid" => $request->starting_bid,
            "reserve_price" => $request->reserve_price,
        ];
        $formAttributes = array_filter($formAttributes, function ($value) {
            return !is_null($value) && $value != "";
        });
        $form->update($formAttributes);

        // Update Product listing_status
        $auctionLotId = null;
        $product = Product::find($form->product_id);

        if ($request->reply_status == ReplyStatus::APPROVED) {
            $product->update(['listing_status' => 'LISTED_IN_AUCTION']);
            $form->update(['is_in_auction' => true]);

            // Create auction_lot
            $auctionLotFields = [
                'auction_request_id' => $form->_id,
                'owned_by_customer_id' => $form->requested_by_customer_id,
                'product_id' => $form->product_id,
                'product_variant_id' => $form->product_variant_id,
                'store_id' => $form->store_id,
                'starting_price' => $form->starting_bid ?? 0,
                'current_bid' => $form->starting_bid ?? 0,
                'reserve_price' => $form->reserve_price ?? 0,
            ];

            $auctionLot = AuctionLot::create($auctionLotFields);
            $auctionLotId = $auctionLot->_id;

            BidHistory::create([
                'auction_lot_id' => $auctionLotId,
                'current_bid' => $auctionLot->starting_bid,
                'histories' => []
            ]);
        } else if ($request->reply_status == ReplyStatus::REJECTED) {
            $product->update(['listing_status' => 'AVAILABLE']);
            $form->update(['is_in_auction' => false]);

            if ($wasInAuction) {
                AuctionLot::where('auction_request_id', $form->_id)->update([
                    'status' => Status::DELETED,
                    'is_disabled' => true
                ]);
            }
        }

        return response()->json([
            'message' => 'Updated AuctionRequest successfully',
            '_id' => $form->_id,
            'auction_lot_id' => $auctionLotId
        ], 200);
    }

    public function updateAuctionLotDetailsByAuctionRequest(Request $request)
    {
        // Auction Request ID
        $auctionRequestID = $request->route('id');
        $form = AuctionRequest::find($auctionRequestID);

        if (is_null($form)) {
            return response()->json([
                'message' => 'AuctionRequest not found',
            ], 404);
        }

        // Find AuctionLot
        $auctionLot = AuctionLot::where('auction_request_id', $form->_id)->latest()->first();

        if (is_null($auctionLot)) {
            return response()->json([
                'message' => 'AuctionLot not found',
            ], 404);
        }

        // Update AuctionRequest
        $updateAttributes = [
            'starting_bid' => $request->starting_price,
            'reserve_price' => $request->reserve_price,
        ];
        $updateAttributes = array_filter($updateAttributes, function ($value) {
            return !is_null($value) && $value != "";
        });
        $form->update($updateAttributes);

        // Update AuctionLot
        $updateAttributes = [
            'starting_price' => $request->starting_price,
            'reserve_price' => $request->reserve_price,
        ];
        $updateAttributes = array_filter($updateAttributes, function ($value) {
            return !is_null($value) && $value != "";
        });
        $auctionLot->update($updateAttributes);

        // Return
        return response()->json([
            'message' => 'Updated AuctionLot successfully',
            'auction_request_id' => $auctionRequestID,
            'auction_lot_id' => $auctionLot->_id
        ], 200);
    }
}
