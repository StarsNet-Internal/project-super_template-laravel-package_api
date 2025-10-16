<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Models
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;

// Constants
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;

class AuctionRegistrationRequestController extends Controller
{
    public function registerAuction(Request $request)
    {
        // Check User
        $user = $this->user();
        if ($user->type === 'TEMP') {
            return response()->json([
                'message' => 'Customer is a TEMP user',
                'error_status' => 1,
                'current_user' => $user
            ], 401);
        }

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

        // Check if store exists
        $store = Store::find($storeID);
        if (is_null($store)) {
            return response()->json([
                'message' => 'Auction not found',
            ], 404);
        }

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

            // Calculate paddle_id if not exists in original AuctionRegistrationRequest yet
            if ($oldForm->paddle_id === null) {
                $newPaddleID = null;
                $allPaddles = AuctionRegistrationRequest::where('store_id', $store->id)
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
                if (is_numeric($newPaddleID)) {
                    $oldFormAttributes['paddle_id'] = $newPaddleID;
                }
            }

            $oldForm->update($oldFormAttributes);

            return response()->json([
                'message' => 'Re-activated previously created AuctionRegistrationRequest successfully',
                'id' => $oldForm->_id,
            ], 200);
        }

        $newFormAttributes = [
            'requested_by_customer_id' => $customer->_id,
            'store_id' => $storeID,
            'status' => Status::ACTIVE,
            'paddle_id' => null,
            'reply_status' => $replyStatus,
        ];

        // Calculate paddle_id if APPROVED
        if ($replyStatus === ReplyStatus::APPROVED) {
            $newPaddleID = null;
            $allPaddles = AuctionRegistrationRequest::where('store_id', $store->id)
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
            if (is_numeric($newPaddleID)) {
                $newFormAttributes['paddle_id'] = $newPaddleID;
            }
        }

        $newForm = AuctionRegistrationRequest::create($newFormAttributes);

        // Return Auction Store
        return response()->json([
            'message' => 'Created New AuctionRegistrationRequest successfully',
            'id' => $newForm->_id,
        ], 200);
    }

    public function createDeposit(Request $request)
    {
        $user = $this->user();
        if ($user->type === 'TEMP') {
            return response()->json([
                'message' => 'Customer is a TEMP user',
                'error_status' => 1,
                'current_user' => $user
            ], 401);
        }

        // Extract attributes from $request
        $formID = $request->route('id');
        $paymentMethod = $request->payment_method;
        $amount = $request->amount;
        $currency = $request->input('currency', 'HKD');
        $conversionRate = $request->input('conversion_rate', '1.00');
        $auctionLotID = $request->auction_lot_id;

        // Get authenticated User information
        $customer = $this->customer();

        // Check if there's existing AuctionRegistrationRequest
        $form = AuctionRegistrationRequest::find($formID);

        if (is_null($form)) {
            return response()->json([
                'message' => 'Auction Registration Request not found',
                'error_status' => 2,
            ], 404);
        }

        if ($form->requested_by_customer_id != $customer->_id) {
            return response()->json([
                'message' => 'You do not have the permission to create Deposit'
            ], 404);
        }

        // If auction_lot_id is provided, find the correct deposit amount written in Store deposit_permissions
        $lotPermissionType = null;
        if (!is_null($auctionLotID)) {
            $auctionLot = AuctionLot::find($auctionLotID);

            if (is_null($auctionLot)) {
                return response()->json([
                    'message' => 'Invalid auction_lot_id'
                ], 404);
            }

            if ($auctionLot->status == Status::DELETED) {
                return response()->json([
                    'message' => 'Auction Lot not found'
                ], 404);
            }

            $lotPermissionType = $auctionLot->permission_type;

            if (!is_null($lotPermissionType)) {
                $storeID = $form->store_id;
                $store = Store::find($storeID);

                if (is_null($store)) {
                    return response()->json([
                        'message' => 'Invalid Store'
                    ], 404);
                }

                if ($store->status == Status::DELETED) {
                    return response()->json([
                        'message' => 'Store not found'
                    ], 404);
                }

                $depositPermissions = $store->deposit_permissions;

                if (!empty($depositPermissions)) {
                    foreach ($depositPermissions as $permission) {
                        if ($permission['permission_type'] === $lotPermissionType) {
                            $amount = $permission['amount'];
                            break;
                        }
                    }
                }
            }
        }

        switch ($paymentMethod) {
            case 'ONLINE':
                // Create Deposit
                $depositAttributes = [
                    'requested_by_customer_id' => $customer->_id,
                    'auction_registration_request_id' => $form->_id,
                    'payment_method' => 'ONLINE',
                    'amount' => $amount,
                    'currency' => 'HKD',
                    'payment_information' => [
                        'currency' => $currency,
                        'conversion_rate' => $conversionRate
                    ],
                    'permission_type' => $lotPermissionType
                ];
                $deposit = Deposit::create($depositAttributes);
                $deposit->updateStatus('submitted');

                // Create Stripe payment intent
                $stripeAmount = (int) $amount * 100;
                $data = [
                    "amount" => $stripeAmount,
                    "currency" => 'HKD',
                    "captureMethod" => "manual",
                    "metadata" => [
                        "model_type" => "deposit",
                        "model_id" => $deposit->_id
                    ]
                ];

                try {
                    $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents';
                    $res = Http::post(
                        $url,
                        $data
                    );

                    // Update Deposit
                    $paymentIntentID = $res['id'];
                    $clientSecret = $res['clientSecret'];

                    $deposit->update([
                        'online' => [
                            'payment_intent_id' => $paymentIntentID,
                            'client_secret' => $clientSecret,
                            'api_response' => null
                        ]
                    ]);
                } catch (\Throwable $th) {
                    return response()->json([
                        'message' => 'Connection to Payment API Failed',
                        'deposit' => null
                    ], 404);
                }

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
                    'currency' => 'HKD',
                    'offline' => [
                        'image' => $request->image,
                        'uploaded_at' => now(),
                        'api_response' => null
                    ],
                    'payment_information' => [
                        'currency' => $currency,
                        'conversion_rate' => $conversionRate
                    ],
                    'permission_type' => $lotPermissionType
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
        $conversionRate = $request->conversion_rate;
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
        $depositAttributes = [
            'customer_id' => $customer->_id,
            'auction_registration_request_id' => $form->_id,
            'payment_method' => 'ONLINE',
            'amount' => $amount,
            'currency' => 'HKD',
            'payment_information' => [
                'currency' => $currency,
                'conversion_rate' => $conversionRate
            ]
        ];
        $deposit = Deposit::create($depositAttributes);
        $deposit->updateStatus('submitted');

        // Create payment-intent
        $stripeAmount = (int) $amount * 100;
        $data = [
            "amount" => $stripeAmount,
            "currency" => 'HKD',
            "captureMethod" => "manual",
            "metadata" => [
                "model_type" => "checkout",
                "model_id" => $deposit->_id
            ]
        ];

        $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents';
        $res = Http::post(
            $url,
            $data
        );

        // Update Deposit
        $paymentIntentID = $res['id'];
        $clientSecret = $res['clientSecret'];

        $deposit->update([
            'online' => [
                'payment_intent_id' => $paymentIntentID,
                'client_secret' => $clientSecret,
                'api_response' => null
            ]
        ]);

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
        $customer = $this->customer();

        if ($request->exists('id')) {
            $form = AuctionRegistrationRequest::objectID($request->id)
                ->with(['store', 'deposits'])
                ->latest()
                ->first();
        }

        if ($request->exists('store_id')) {
            $form = AuctionRegistrationRequest::where('store_id', $request->store_id)
                ->where('requested_by_customer_id', $customer->_id)
                ->with(['store', 'deposits'])
                ->latest()
                ->first();
        }

        if (is_null($form)) {
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
