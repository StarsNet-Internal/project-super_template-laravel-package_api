<?php

namespace StarsNet\Project\CasaModernism\App\Http\Controllers\Admin;

use App\Constants\Model\LoginType;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Http\Request;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\NotificationSetting;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Support\Collection;

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use StarsNet\Project\CasaModernism\App\Models\TradeRegistration;
use stdClass;

class CustomerController extends AdminCustomerController
{
    public function getTradeRegistration(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        $registration = TradeRegistration::where('customer_id', $customerID)->get()->last();

        if (is_null($registration)) {
            return response()->json(
                new stdClass(),
                200
            );
        }

        return $registration;
    }

    public function approveTradeRegistration(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        // Get latest registration, then validate
        $registration = TradeRegistration::where('customer_id', $customerID)->get()->last();

        if (is_null($registration)) {
            return response()->json([
                'message' => 'TradeRegistration not found'
            ], 404);
        }

        // if ($registration->hasApprovedOrRejected()) {
        //     return response()->json([
        //         'message' => 'TradeRegistration has been approved/rejected'
        //     ], 400);
        // }

        // Update RefundRequest
        $registration->updateReplyStatus($request->reply_status);

        // Get authenticated User information
        $user = $this->user();

        // Create ReviewReply
        $reply = null;
        if ($request->hasAny(['images', 'comment'])) {
            $replyAttribute = [
                'images' => $request->images,
                'comment' => $request->comment,
            ];
            $reply = $registration->reply($user, $replyAttribute);
        }

        // Return success message
        return response()->json([
            'message' => 'Replied to RefundRequest',
            '_id' => optional($reply)->_id
        ], 200);
    }
}
