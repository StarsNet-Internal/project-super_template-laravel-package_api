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
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionRequest;
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
            'requestedAccount',
            'approvedAccount',
            'productInfo'
        ])->get();
    }

    public function getAllAuctionRequests(Request $request)
    {
        $forms = $this->filterAuctionRequests($request->all());
        return $forms;
    }

    public function approveAuctionRequest(Request $request)
    {
        $form = AuctionRequest::find($request->route('id'));

        $form->update(['reply_status' => $request->reply_status]);

        if ($request->reply_status == ReplyStatus::APPROVED) {
            // Do something, transfer inventory
        }

        return response()->json([
            'message' => 'Updated AuctionRequest successfully',
            '_id' => $form->_id
        ], 200);
    }
}
