<?php

namespace StarsNet\Project\Green360\App\Http\Controllers\Customer;

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

class OrderController extends Controller
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
            case 'checkout':
                $checkout = Checkout::find($modelId);

                if (is_null($checkout)) {
                    return response()->json(['message' => 'Checkout not found'], 400);
                }

                // Save response
                $allResponse = (object) $request->all();
                $checkout->updateOnlineResponse($allResponse);

                // Update Checkout and Order
                $status = $isPaid ? CheckoutApprovalStatus::APPROVED : CheckoutApprovalStatus::REJECTED;
                $reason = $isPaid ? 'Payment verified by System' : 'Payment failed';
                $checkout->createApproval($status, $reason);

                // Get Order and Customer
                /** @var Order $order */
                $order = $checkout->order;
                /** @var Customer $customer */
                $customer = $order->customer;

                // Update Order status
                if (
                    $isPaid && $order->current_status !== ShipmentDeliveryStatus::PROCESSING
                ) {
                    $order->setTransactionMethod($paymentMethod);
                    $order->updateStatus(ShipmentDeliveryStatus::PROCESSING);
                }

                if (
                    !$isPaid && $order->current_status !== ShipmentDeliveryStatus::CANCELLED
                ) {
                    $order->updateStatus(ShipmentDeliveryStatus::CANCELLED);
                    return;
                }

                // Delete ShoppingCartItem(s)
                /** @var Store $store */
                $store = $order->store;
                $variantIDs = collect($order->cart_items)->pluck('product_variant_id')->all();
                $variants = ProductVariant::objectIDs($variantIDs)->get();
                $customer->clearCartByStore($store, $variants);

                // Create Employee Account
                $controller = new AuthenticationController();
                $emails = $order->delivery_details['remarks'];
                $existingEmails = User::whereIn('login_id', $emails)->pluck('login_id')->all();
                $missingEmails = array_diff($emails, $existingEmails);

                foreach ($missingEmails as $email) {
                    try {
                        $createRequest = new Request();
                        $createRequest->replace([
                            'type' => 'EMAIL',
                            'username' => $email,
                            'email' => $email,
                            'password' => 'Fastgreen360',
                        ]);
                        // Log::info(['r' => $createRequest]);
                        $controller->register($createRequest);
                    } catch (\Exception $e) {
                        Log::info($e->getMessage());
                        Log::info("Exception at $email");
                    } finally {
                        $url = 'https://mail.green360.hk/send';
                        $response = Http::post($url, [
                            'to' => $email,
                            'from' => `NO REPLY Green360`,
                            'subject' => 'Green360 Video Course',
                            'content' => 'Your company has purchased a Green360 Video Course. Use the following link to view the course materials. <a href="https://www.green360.hk/greenmasters/course-video/list?email=' . $email . '">Link</a>',
                        ]);
                    }
                }

                return response()->json(['message' => 'SUCCESS'], 200);
            default:
                return response()->json(['message' => 'Invalid model_type for metadata'], 400);
        }
    }
}
