<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;

class AuctionRegistrationRequestController extends Controller
{
    public function registerAuction(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->store_id;
        $customerID = $request->customer_id;

        // Auth
        $account = $this->account();

        // Find Store and Customer
        $store = Store::find($storeID);

        if (is_null($store)) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        $customer = Customer::find($customerID);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Check if there's existing AuctionRegistrationRequest
        $oldForm =
            AuctionRegistrationRequest::where('requested_by_customer_id', $customer->_id)
            ->where('store_id', $store->_id)
            ->first();

        if (!is_null($oldForm)) {
            $oldFormAttributes = [
                'approved_by_account_id' => $account->_id,
                'status' => Status::ACTIVE,
                'reply_status' => ReplyStatus::APPROVED,
            ];
            $oldForm->update($oldFormAttributes);

            return response()->json([
                'message' => 'Re-activated previously created AuctionRegistrationRequest successfully',
                'id' => $oldForm->_id,
            ], 200);
        }

        // Create AuctionRegistrationRequest
        $newFormAttributes = [
            'requested_by_customer_id' => $customer->_id,
            'store_id' => $store->_id,
        ];
        $newForm = AuctionRegistrationRequest::create($newFormAttributes);

        // Return Auction Store
        return response()->json([
            'message' => 'Created New AuctionRegistrationRequest successfully',
            'id' => $newForm->_id,
        ], 200);
    }

    public function getAllRegisteredAuctions(Request $request)
    {
        // Get Items
        $forms = AuctionRegistrationRequest::with(['store'])
            ->get();

        return $forms;
    }

    public function getRegisteredAuctionDetails(Request $request)
    {
        $form = null;

        if ($request->exists('id')) {
            $form = AuctionRegistrationRequest::objectID($request->id)
                ->with(['store', 'deposits'])
                ->latest()
                ->first();
        }

        if ($request->exists('store_id')) {
            $form = AuctionRegistrationRequest::where('store_id', $request->store_id)
                ->with(['store', 'deposits'])
                ->latest()
                ->first();
        }

        if (is_null($form)) {
            return response()->json([
                'message' => 'Auction Registration Request not found'
            ], 404);
        }

        if ($form->status == Status::DELETED) {
            return response()->json([
                'message' => 'Auction Registration Request not found'
            ], 404);
        }

        return $form;
    }

    public function archiveAuctionRegistrationRequest(Request $request)
    {
        // Extract attributes from $request
        $formID = $request->route('auction_registration_request_id');

        // Get AuctionRegistrationRequest
        $form = AuctionRegistrationRequest::find($formID);

        if (is_null($form)) {
            return response()->json([
                'message' => 'Auction Registration Request not found'
            ], 404);
        }

        $form->update(['status' => Status::ARCHIVED]);

        return response()->json([
            'message' => 'Updated AuctionRegistrationRequest status to ARCHIVED',
        ], 200);
    }
}
