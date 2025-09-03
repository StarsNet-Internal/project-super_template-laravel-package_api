<?php

namespace StarsNet\Project\Auction\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TestingController extends Controller
{
    public function healthCheck()
    {
        $discountCalculator = function ($amount) {
            if ($amount <= 1000) return $amount;
            return $amount <= 10000
                ? $amount % 1000 + (intval($amount / 1000)) * 1000 / 5 // remainder + thousands
                : $amount % 1000 + (intval($amount / 1000)) * 1000 / 4; // remainder + thousands
        };

        echo $discountCalculator(939) . "\n";
        echo $discountCalculator(1339) . "\n";
        echo $discountCalculator(1539) . "\n";
        echo $discountCalculator(1939) . "\n";
        echo $discountCalculator(2439) . "\n";
        echo $discountCalculator(9939) . "\n";
        echo $discountCalculator(58939) . "\n";
        return response()->json([
            'message' => 'OK from package/auction'
        ], 200);
    }
}
