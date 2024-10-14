<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Configuration;
use App\Models\ProductVariant;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;

use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Str;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\Deposit;

class DepositController extends Controller
{
    use RoundingTrait;

    public function getAllDeposits(Request $request)
    {
        // Extract attributes from $request
        $auctionType = $request->input('auction_type', 'ONLINE');

        // Get Items
        $deposits = Deposit::with(['auctionRegistrationRequest.store'])
            ->whereHas('auctionRegistrationRequest', function ($query) use ($auctionType) {
                $query->whereHas('store', function ($query2) use ($auctionType) {
                    $query2->where('auction_type', $auctionType);
                });
            })
            ->latest()
            ->get();

        return $deposits;
    }

    public function getDepositDetails(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');

        // Get Deposit
        $deposit = Deposit::with([
            'auctionRegistrationRequest.store',
            'requestedCustomer',
            'approvedAccount'
        ])
            ->find($depositID);

        if (is_null($deposit)) {
            return response()->json([
                'message' => 'Deposit not found'
            ], 404);
        }

        return $deposit;
    }

    public function updateDepositDetails(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');

        // Get Deposit
        $deposit = Deposit::find($depositID);

        if (is_null($deposit)) {
            return response()->json([
                'message' => 'Deposit not found'
            ], 404);
        }

        // Update Deposit
        $updateAttributes = $request->all();
        $deposit->update($updateAttributes);

        return response()->json([
            'message' => 'Deposit updated successfully'
        ], 200);
    }

    public function approveDeposit(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');
        $replyStatus = $request->reply_status;

        // Validation 
        $replyStatus = strtoupper($replyStatus);

        if (!in_array($replyStatus, [
            ReplyStatus::APPROVED,
            ReplyStatus::REJECTED
        ])) {
            return response()->json([
                'message' => 'reply_status invalid'
            ], 400);
        }

        // Get Deposit
        $deposit = Deposit::find($depositID);

        if (is_null($deposit)) {
            return response()->json([
                'message' => 'Deposit not found'
            ], 404);
        }

        if (in_array($deposit->reply_status, [
            ReplyStatus::APPROVED,
            ReplyStatus::REJECTED
        ])) {
            return response()->json([
                'message' => 'Deposit has already been APPROVED/REJECTED'
            ], 400);
        }

        // Get AuctionRegistrationRequest
        $auctionRegistrationRequest = $deposit->auctionRegistrationRequest;

        if (is_null($auctionRegistrationRequest)) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        // Get current Account
        $account = $this->account();

        // Update Deposit and AuctionRegistrationRequest
        switch ($replyStatus) {
            case ReplyStatus::APPROVED:
                // Update Deposit
                $depositUpdateAttributes = [
                    'approved_by_account_id' => $account->_id,
                    'reply_status' => ReplyStatus::APPROVED
                ];
                $deposit->update($depositUpdateAttributes);
                $deposit->updateStatus('on-hold');

                // Update AuctionRegistrationRequest
                $storeID = $auctionRegistrationRequest->store_id;
                $assignedPaddleID = $auctionRegistrationRequest->paddle_id;

                if (is_null($assignedPaddleID)) {
                    $highestPaddleID = optional(AuctionRegistrationRequest::where('store_id', $storeID)
                        ->orderByDesc('paddle_id')
                        ->first())
                        ->paddle_id
                        ?? 0;
                    $assignedPaddleID = $highestPaddleID + 1;
                }

                $requestUpdateAttributes = [
                    'approved_by_account_id' => $account->_id,
                    'paddle_id' => $assignedPaddleID,
                    'status' => Status::ACTIVE,
                    'reply_status' => ReplyStatus::APPROVED
                ];
                $auctionRegistrationRequest->update($requestUpdateAttributes);
                break;
            case ReplyStatus::REJECTED:
                // Update Deposit
                $depositUpdateAttributes = [
                    'approved_by_account_id' => $account->_id,
                    'reply_status' => ReplyStatus::REJECTED
                ];
                $deposit->update($depositUpdateAttributes);
                $deposit->updateStatus('rejected');

                // Update AuctionRegistrationRequest
                $requestUpdateAttributes = [
                    'approved_by_account_id' => $account->_id,
                    'status' => Status::ACTIVE,
                    'reply_status' => ReplyStatus::REJECTED
                ];
                $auctionRegistrationRequest->update($requestUpdateAttributes);
                break;
            default:
                break;
        }

        return response()->json([
            'message' => 'Deposit updated successfully'
        ], 200);
    }

    public function cancelDeposit(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');

        // Get Deposit
        $deposit = Deposit::objectID($depositID)->first();

        if (is_null($deposit)) {
            return response()->json([
                'message' => 'Deposit not found'
            ], 404);
        }

        if ($deposit->status != Status::ACTIVE) {
            return response()->json([
                'message' => 'Deposit not found'
            ], 404);
        }

        if ($deposit->reply_status != ReplyStatus::PENDING) {
            return response()->json([
                'message' => 'This Deposit has already been APPROVED/REJECTED.'
            ], 404);
        }

        // Update Deposit
        $depositAttributes = [
            'status' => Status::ARCHIVED,
            'reply_status' => ReplyStatus::REJECTED
        ];
        $deposit->update($depositAttributes);

        return response()->json([
            'message' => 'Deposit updated successfully'
        ], 200);
    }
}
