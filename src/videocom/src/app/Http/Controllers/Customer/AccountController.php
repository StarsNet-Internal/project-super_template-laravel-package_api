<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;

use App\Models\ProductVariant;
use App\Models\Store;
use Carbon\Carbon;

use StarsNet\Project\Videocom\App\Models\AuctionLot;
use StarsNet\Project\Videocom\App\Models\AuctionRequest;
use StarsNet\Project\Videocom\App\Models\BidHistory;

use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function updateAccountVerification(Request $request)
    {
        $account = $this->account();

        $updateFields = $request->all();
        $account->update($updateFields);

        return response()->json([
            'message' => 'Updated Verification document successfully'
        ], 200);
    }

    public function getAllCustomerGroups(Request $request)
    {
        $customer = $this->customer();

        $groups = $customer->groups()
            ->statusActive()
            ->latest()
            ->get();

        return $groups;
    }
}
