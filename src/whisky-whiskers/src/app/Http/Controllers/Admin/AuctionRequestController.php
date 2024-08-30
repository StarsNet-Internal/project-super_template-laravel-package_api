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

        // Update Product listing_status
        $auctionLotId = null;
        $product = Product::find($form->product_id);

        if ($request->reply_status == ReplyStatus::APPROVED) {
            $product->update(['listing_status' => 'LISTED_IN_AUCTION']);

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
                'current_bid' => $$auctionLot->starting_bid,
                'histories' => []
            ]);
        } else if ($request->reply_status == ReplyStatus::REJECTED) {
            $product->update(['listing_status' => 'AVAILABLE']);
        }

        return response()->json([
            'message' => 'Updated AuctionRequest successfully',
            '_id' => $form->_id,
            'auction_lot_id' => $auctionLotId
        ], 200);
    }
}
