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

class AuctionRegistrationRequestController extends Controller
{
    public function registerAuction(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Extract attributes from $request
        $storeID = $request->store_id;

        // Check CustomerGroup for reply_status value
        $hasWaivedAuctionRegistrationGroup = $customer->groups()
            ->where('is_waived_auction_registration_deposit', true)
            ->exists();
        $replyStatus = $hasWaivedAuctionRegistrationGroup ?
            ReplyStatus::APPROVED :
            ReplyStatus::PENDING;

        // Check if there's existing AuctionRegistrationRequest
        $oldForm =
            AuctionRegistrationRequest::where('requested_by_customer_id', $customer->_id)
            ->where('store_id', $storeID)
            ->first();

        if (!is_null($oldForm)) {
            $oldFormAttributes = [
                'approved_by_account_id' => null,
                'status' => Status::ACTIVE,
                'reply_status' => $replyStatus,
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
            'store_id' => $storeID,
            'status' => Status::ACTIVE,
            'reply_status' => $replyStatus,
        ];
        $newForm = AuctionRegistrationRequest::create($newFormAttributes);

        // Return Auction Store
        return response()->json([
            'message' => 'Created New AuctionRegistrationRequest successfully',
            'id' => $newForm->_id,
        ], 200);
    }

    public function createDeposit(Request $request)
    {
        // Extract attributes from $request
        $formID = $request->route('id');
        $paymentMethod = $request->payment_method;
        $amount = $request->amount;
        $currency = $request->input('currency', 'HKD');
        $conversion_rate = $request->input('conversion_rate', '1.00');

        // Get authenticated User information
        $customer = $this->customer();

        // Check if there's existing AuctionRegistrationRequest
        $form = AuctionRegistrationRequest::find($formID);

        if (is_null($form)) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found',
            ], 200);
        }

        if ($form->status != Status::ACTIVE) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        if ($form->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'You do not have the permission to create Deposit'
            ], 404);
        }

        switch ($paymentMethod) {
            case 'ONLINE':
                $stripeAmount = (int) $amount * 100;
                $data = [
                    "amount" => $stripeAmount,
                    "currency" => 'HKD',
                    "captureMethod" => "manual",
                    "callbackUrl" => "backend.paraqon.hk/api/customer/stripe/payments/callback"
                ];

                $url = 'http://192.168.0.105:3002/payment-intents';
                $res = Http::post(
                    $url,
                    $data
                );

                $paymentIntentID = $res['id'];
                $clientSecret = $res['clientSecret'];

                $depositAttributes = [
                    'requested_by_customer_id' => $customer->_id,
                    'auction_registration_request_id' => $form->_id,
                    'payment_method' => 'ONLINE',
                    'amount' => $amount,
                    'currency' => 'HKD',
                    'online' => [
                        'payment_intent_id' => $paymentIntentID,
                        'client_secret' => $clientSecret,
                        'api_response' => null
                    ],
                    'payment_information' => [
                        'currency' => $currency,
                        'conversion_rate' => $conversion_rate
                    ]
                ];
                $deposit = Deposit::create($depositAttributes);
                $deposit->updateStatus('submitted');

                // Return Auction Store
                return response()->json([
                    'message' => 'Created New Deposit successfully',
                    'deposit' => $deposit
                ], 200);
            case 'OFFLINE':
                $depositAttributes = [
                    'requested_by_customer_id' => $customer->_id,
                    'auction_registration_request_id' => $form->_id,
                    'payment_method' => 'OFFLINE',
                    'amount' => $amount,
                    'currency' => $currency,
                    'offline' => [
                        'image' => $request->image,
                        'uploaded_at' => now(),
                        'api_response' => null
                    ],
                ];
                $deposit = Deposit::create($depositAttributes);
                $deposit->updateStatus('submitted');

                return response()->json([
                    'message' => 'Created New Deposit successfully',
                    'deposit' => $deposit
                ], 200);
            default:
                return response()->json([
                    'message' => 'payment_method ' . $paymentMethod . ' is not supported.',
                ], 200);
        }
    }

    public function registerAuctionAgain(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Extract attributes from $request
        $storeID = $request->store_id;
        $amount = $request->amount;
        $currency = $request->currency;
        $formID = $request->route('auction_registration_request_id');

        // Check if there's existing AuctionRegistrationRequest
        $form = AuctionRegistrationRequest::find($formID);

        if (is_null($form)) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        if ($form->status != Status::ACTIVE) {
            return response()->json([
                'message' => 'AuctionRegistrationRequest not found'
            ], 404);
        }

        if (
            $form->requested_by_customer_id != $customer->_id
        ) {
            return response()->json([
                'message' => 'You do not have the permission to update this AuctionRegistrationRequest'
            ], 404);
        }

        // Create Deposit
        $stripeAmount = (int) $amount * 100;
        $data = [
            "amount" => $stripeAmount,
            "currency" => 'HKD',
            "captureMethod" => "manual",
            "callbackUrl" => "backend.paraqon.hk/api/customer/stripe/payments/callback"
        ];

        $url = 'http://192.168.0.105:3002/payment-intents';
        $res = Http::post(
            $url,
            $data
        );

        $paymentIntentID = $res['id'];
        $clientSecret = $res['clientSecret'];

        $depositAttributes = [
            'customer_id' => $customer->_id,
            'auction_registration_request_id' => $form->_id,
            'payment_method' => 'ONLINE',
            'amount' => $amount,
            'currency' => $currency,
            'online' => [
                'payment_intent_id' => $paymentIntentID,
                'client_secret' => $clientSecret,
                'api_response' => null
            ],
        ];
        $deposit = Deposit::create($depositAttributes);

        // Return Auction Store
        return response()->json([
            'message' => 'Created New AuctionRegistrationRequest successfully',
            'auction_registration_request_id' => $form->_id,
            'deposit_id' => $deposit->_id
        ], 200);
    }


    public function getAllRegisteredAuctions(Request $request)
    {
        // Get authenticated User information
        $customer = $this->customer();

        // Get Items
        $forms = AuctionRegistrationRequest::where('requested_by_customer_id', $customer->_id)
            ->with(['store'])
            ->get();

        return $forms;
    }

    public function getRegisteredAuctionDetails(Request $request)
    {
        // Get AuctionRegistrationRequest
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

        $customer = $this->customer();

        if ($form->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'Access denied'
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

        $customer = $this->customer();

        if ($form->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'Access denied'
            ], 404);
        }

        $form->update(['status' => Status::ARCHIVED]);

        return response()->json([
            'message' => 'Updated AuctionRegistrationRequest status to ARCHIVED',
        ], 200);
    }
}
