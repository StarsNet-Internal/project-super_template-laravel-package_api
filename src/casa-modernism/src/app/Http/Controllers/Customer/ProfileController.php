<?php

namespace StarsNet\Project\CasaModernism\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;

use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use StarsNet\Project\CasaModernism\App\Models\TradeRegistration;
use App\Constants\Model\ReplyStatus;
use stdClass;

class ProfileController extends CustomerProfileController
{
    public function getTradeRegistration(Request $request)
    {
        $customer = $this->customer();

        $registration = TradeRegistration::where('customer_id', $customer->_id)->get()->last();

        if (is_null($registration)) {
            return response()->json(new stdClass(), 200);
        }

        return $registration;
    }

    public function createTradeRegistration(Request $request)
    {
        $customer = $this->customer();

        $images = $request->input('images', []);

        $registration = TradeRegistration::where('customer_id', $customer->_id)->get()->last();

        if (!is_null($registration)) {
            if ($registration->reply_status === ReplyStatus::PENDING) {
                return response()->json([
                    'message' => 'Customer has already submitted a TradeRegistration'
                ], 401);
            } else if ($registration->reply_status === ReplyStatus::APPROVED) {
                return response()->json([
                    'message' => 'Customer is already a Trade'
                ], 401);
            }
        }

        $registration = TradeRegistration::create([
            'images' => $images
        ]);
        $registration->associateCustomer($customer);

        // Return response message
        return response()->json([
            'message' => 'Submitted TradeRegistration successfully'
        ], 200);
    }
}
