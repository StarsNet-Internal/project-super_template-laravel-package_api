<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;
use StarsNet\Project\Paraqon\App\Models\PassedAuctionRecord;

class ServiceController extends Controller
{
    public function archiveStores(Request $request)
    {
        $now = now();

        Store::where('end_datetime', '<=', $now)
            ->where('status', Status::ACTIVE)
            ->update(['status' => Status::ARCHIVED]);

        return response()->json([
            'message' => 'Archived stores'
        ]);
    }
}
