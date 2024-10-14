<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Constants\Model\ProductVariantDiscountType;

use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Product;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;

class DepositController extends Controller
{
    public function getAllDeposits(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Get Items
        $deposits = Deposit::where('requested_by_customer_id', $customer->_id)
            ->with(['auctionRegistrationRequest.store'])
            ->latest()
            ->get();

        return $deposits;
    }

    public function getDepositDetails(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');

        // Get Deposit
        $deposit = Deposit::with(['auctionRegistrationRequest.store', 'depositStatuses'])
            ->find($depositID);

        if (is_null($deposit)) {
            return response()->json([
                'message' => 'Deposit not found'
            ], 404);
        }

        $customer = $this->customer();

        if ($deposit->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'Access denied'
            ], 404);
        }

        return $deposit;
    }

    public function updateDepositDetails(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');

        // Get authenticated User information
        $customer = $this->customer();

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

        if ($deposit->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'You do not have the permission to update this Deposit'
            ], 404);
        }

        // Update Deposit
        $updateAttributes = $request->all();
        $deposit->update($updateAttributes);

        return response()->json([
            'message' => 'Deposit updated successfully'
        ], 200);
    }

    public function cancelDeposit(Request $request)
    {
        // Extract attributes from $request
        $depositID = $request->route('id');

        // Get authenticated User information
        $customer = $this->customer();

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

        if ($deposit->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'You do not have the permission to update this Deposit'
            ], 404);
        }

        if ($deposit->reply_status != ReplyStatus::PENDING) {
            return response()->json([
                'message' => 'This Deposit has already been APPROVED/REJECTED.'
            ], 404);
        }

        $registrationRequest = $deposit->auctionRegistrationRequest()
            ->latest()
            ->first();

        if (is_null($registrationRequest)) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        if ($registrationRequest->status != Status::ACTIVE) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        if ($registrationRequest->reply_status != ReplyStatus::PENDING) {
            return response()->json([
                'message' => 'This AuctionRegistrationRequest has already been APPROVED/REJECTED.'
            ], 404);
        }

        // Update Deposit
        $depositAttributes = [
            'status' => Status::ARCHIVED,
            'reply_status' => ReplyStatus::REJECTED
        ];
        $deposit->update($depositAttributes);

        // Update AuctionRegistrationRequest
        $requestAttributes = [
            'status' => Status::ARCHIVED,
            'reply_status' => ReplyStatus::REJECTED
        ];
        $registrationRequest->update($requestAttributes);

        return response()->json([
            'message' => 'Deposit updated successfully'
        ], 200);
    }
}
