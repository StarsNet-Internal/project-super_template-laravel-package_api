<?php

namespace StarsNet\Project\WhiskyWhiskersUpgrade\App\Http\Controllers\Customer;

use Illuminate\Http\Request;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\ShipmentDeliveryStatus;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Http\Controllers\Customer\AuthenticationController;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function onlinePaymentCallback(Request $request)
    {
        // Extract attributes from $request
        $isPaid = $request->type == 'payment_intent.succeeded';
        $paymentMethod = 'CREDIT CARD';

        if ($isPaid === false) return;

        // Extract attributes from $request
        $model = $request->data['object']['metadata']['model_type'] ?? null;
        $modelId = $request->data['object']['metadata']['model_id'] ?? null;

        if (is_null($model) || is_null($modelId)) {
            return response()->json(
                ['message' => 'Callback success, but metadata contains null value for either model_type or model_id.'],
                400
            );
        }

        switch ($model) {
            case 'user':
                $user = User::find($modelId);
                if (is_null($user)) {
                    return response()->json(['message' => 'User not found'], 400);
                }
                $user->update([
                    'is_verified' => true
                ]);

                return response()->json(['message' => 'SUCCESS'], 200);
            default:
                return response()->json(['message' => 'Invalid model_type for metadata'], 400);
        }
    }
}
