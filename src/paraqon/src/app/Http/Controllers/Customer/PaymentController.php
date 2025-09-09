<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function onlinePaymentCallback(Request $request)
    {
        return response()->json('SUCCESS', 200);
    }
}
