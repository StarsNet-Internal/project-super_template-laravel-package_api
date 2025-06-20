<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;

use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Facades\Http;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;

class AuctionRegistrationRequestController extends Controller
{
    public function updateAuctionRegistrationRequest(Request $request)
    {
        $customer = $this->customer();
        $auctionRegistrationRequest = AuctionRegistrationRequest::find($request->route('auction_registration_request_id'));

        if (is_null($auctionRegistrationRequest)) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        if ($auctionRegistrationRequest->requested_by_customer_id !== $customer->_id) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest does not belong to this customer'
            ], 401);
        }

        $replyStatus = $request->reply_status;

        if (!in_array($replyStatus, [
            ReplyStatus::APPROVED,
            ReplyStatus::REJECTED
        ])) {
            return response()->json([
                'message' => 'reply_status should be either APPROVED/REJECTED'
            ], 400);
        }

        $newPaddleID = null;
        if (!is_null($request->paddle_id)) {
            $newPaddleID = $request->paddle_id;
        } else if (!is_null($auctionRegistrationRequest->paddle_id)) {
            $newPaddleID = $auctionRegistrationRequest->paddle_id;
        } else {
            $allPaddles = AuctionRegistrationRequest::where('store_id', $auctionRegistrationRequest->store_id)
                ->pluck('paddle_id')
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->sort()
                ->values();
            $latestPaddleId = $allPaddles->last();

            if (is_null($latestPaddleId)) {
                $newPaddleID = $store->paddle_number_start_from ?? 1;
            } else {
                $newPaddleID = $latestPaddleId + 1;
            }
        }

        $account = $this->account();
        $requestUpdateAttributes = [
            'approved_by_account_id' => $account->_id,
            'paddle_id' => $newPaddleID,
            'status' => Status::ACTIVE,
            'reply_status' => $replyStatus
        ];
        $auctionRegistrationRequest->update($requestUpdateAttributes);

        // Return client secret to generate link
        return response()->json([
            'message' => 'Updated AuctionRegistrationRequest successfully',
            'auction_registration_request_id' => $auctionRegistrationRequest->_id
        ], 200);
    }
}
