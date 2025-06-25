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

class CreditCardController extends Controller
{
    public function bindCard(Request $request)
    {
        $customer = $this->customer();
        $account = $this->account();

        // Create payment-intent
        $data = [
            "metadata" => [
                "model_type" => "customer",
                "model_id" => $customer->_id
            ]
        ];

        // Create Stripe setup intent
        $url = env('TCG_BID_STRIPE_BASE_URL', 'http://192.168.0.83:8082') . '/setup-intents';
        $response = Http::post(
            $url,
            $data
        );
        $clientSecret = $response['client_secret'];

        // Return client secret to generate link
        return response()->json([
            'message' => 'Created Setup Intent on Stripe successfully',
            'client_secret' => $clientSecret,
            'account' => $account
        ], 200);
    }

    public function validateCard(Request $request)
    {
        $customer = $this->customer();

        // Get Card info
        $stripeCardData = $customer->stripe_card_data;

        if (is_null($stripeCardData)) {
            return [
                'message' => 'Customer stripe payment info not found',
                'is_card_valid' => false,
                'stripe_card_data' => null
            ];
        }

        // Validate date
        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');

        $expYear = (int) $stripeCardData['exp_year'];
        $expMonth = (int) $stripeCardData['exp_month'];

        if ($expYear > $currentYear) {
            return [
                'message' => 'Customer stripe payment is valid',
                'is_card_valid' => true,
                'stripe_card_data' => $stripeCardData,
            ];
        }

        if ($expYear === $currentYear && $expMonth >= $currentMonth) {
            return [
                'message' => 'Customer stripe payment is valid',
                'is_card_valid' => true,
                'stripe_card_data' => $stripeCardData,
            ];
        }

        return [
            'message' => 'Customer stripe payment is expired',
            'is_card_valid' => false,
            'stripe_card_data' => $stripeCardData,
        ];
    }
}
