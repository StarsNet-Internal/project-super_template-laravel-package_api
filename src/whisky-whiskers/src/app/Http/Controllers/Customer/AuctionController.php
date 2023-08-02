<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class AuctionController extends Controller
{
    public function getAllAuctions(Request $request)
    {
        $auctions = Store::whereType(StoreType::OFFLINE)->statuses([Status::ACTIVE, Status::ARCHIVED])->get();
        return $auctions;
    }

    public function getAuctionDetails(Request $request)
    {
        $storeId = $request->route('store_id');
        $auction = Store::find($storeId);
        return $auction;
    }
}
