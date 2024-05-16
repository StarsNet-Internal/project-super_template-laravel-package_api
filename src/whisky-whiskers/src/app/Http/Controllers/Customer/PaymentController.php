<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Events\Common\Payment\PaidFromPinkiePay;

class PaymentController extends Controller
{
    public function onlinePaymentCallback(Request $request)
    {
        event(new PaidFromPinkiePay($request));
        return response()->json('SUCCESS', 200);
    }
}
