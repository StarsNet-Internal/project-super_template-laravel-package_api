<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Models
use App\Models\Store;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\Deposit;

// Constants
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;

// Traits
use App\Traits\Utils\RoundingTrait;

class DepositController extends Controller
{
    use RoundingTrait;

    public function getAllDeposits(Request $request)
    {
        // Extract attributes from $request
        $auctionType = $request->input('auction_type', 'ONLINE');

        // Get Items
        $deposits = Deposit::with([
            'auctionRegistrationRequest.store',
            'depositStatuses'
        ])
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
            'approvedAccount',
            'depositStatuses'
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

                // Get and Update paddle_id
                $storeID = $auctionRegistrationRequest->store_id;
                $store = Store::find($storeID);

                $newPaddleID = null;
                if (!is_null($store)) {
                    $auctionType = $store->auction_type;

                    if ($auctionType == 'ONLINE') {
                        if (is_numeric($request->paddle_id)) {
                            $newPaddleID = $request->paddle_id;
                        } else if (is_null($auctionRegistrationRequest->paddle_id)) {
                            $allPaddles = AuctionRegistrationRequest::where('store_id', $storeID)
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
                        } else {
                            $newPaddleID = $auctionRegistrationRequest->paddle_id;
                        }
                    } else if ($auctionType == 'LIVE') {
                        $newPaddleID = $request->paddle_id ?? $auctionRegistrationRequest->paddle_id;
                    } else {
                        $newPaddleID = $request->paddle_id ?? $auctionRegistrationRequest->paddle_id;
                    }
                }

                $requestUpdateAttributes = [
                    'approved_by_account_id' => $account->_id,
                    'paddle_id' => $newPaddleID,
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

        // Return Deposit
        if ($deposit->payment_method == OrderPaymentMethod::ONLINE) {

            try {
                $paymentIntentID = $deposit->online['payment_intent_id'];
                $url = env('PARAQON_STRIPE_BASE_URL', 'https://payment.paraqon.starsnet.hk') . '/payment-intents/' . $paymentIntentID . '/cancel';

                $response = Http::post(
                    $url
                );

                if ($response->status() === 200) {
                    $deposit->update([
                        'refund_id' => $response['id']
                    ]);
                    $deposit->updateStatus('cancelled');
                } else {
                    $deposit->updateStatus('return-failed');
                }
            } catch (\Throwable $th) {
                $deposit->updateStatus('return-failed');
            }
        }

        return response()->json([
            'message' => 'Deposit cancelled successfully'
        ], 200);
    }
}
