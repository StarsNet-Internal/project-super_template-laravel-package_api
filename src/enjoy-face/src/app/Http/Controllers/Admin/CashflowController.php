<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Controller\CheckoutTrait;
use App\Traits\Controller\ShoppingCartTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;


class CashflowController extends Controller
{
    use ShoppingCartTrait;

    public function getCashFlowDataByDateRange(Request $request)
    {
        // Extract attributes from $request
        $storeId = $request->store_id;
        $startDateTime = $request->input('start_datetime');
        if (!is_null($startDateTime)) $startDateTime = Carbon::parse($startDateTime)->startOfDay();
        $endDateTime = $request->input('end_datetime');
        if (!is_null($endDateTime)) $endDateTime = Carbon::parse($endDateTime)->endOfDay();

        // Get ProductVariant(s)
        $products = ProductVariant::when($startDateTime, function ($query, $startDateTime) {
            $query->where([['created_at', '>=', $startDateTime]]);
        })->when($endDateTime, function ($query, $endDateTime) {
            $query->where([['created_at', '<=', $endDateTime]]);
        })->get();
        $totalPurchasePrice = $products->sum('price');
        $totalBoughtMobileCount = $products->filter(function ($product) {
            return $product->is_mobile;
        })->count();

        // Get Order(s)
        $orders = Order::when($storeId, function ($query, $storeId) {
            $query->where('store_id', $storeId);
        })->when($startDateTime, function ($query, $startDateTime) {
            $query->where([['created_at', '>=', $startDateTime]]);
        })->when($endDateTime, function ($query, $endDateTime) {
            $query->where([['created_at', '<=', $endDateTime]]);
        })->get();
        $totalSoldPrice = $orders->sum('amount_received');
        $totalSoldMobileCount = $orders->filter(function ($order) {
            return $order->is_mobile;
        })->sum('mobile_sold_qty');
        $totalProfit = $orders->sum('change');

        // Return success message
        return response()->json([
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'previous_total_purchase' => $totalPurchasePrice,
            'previous_total_bought_mobile_count' => $totalBoughtMobileCount,
            'previous_total_sold_mobile_count' => $totalSoldMobileCount,
            'previous_owned_mobile_count' => $totalBoughtMobileCount - $totalSoldMobileCount,
            'previous_total_sold' => $totalSoldPrice,
            'previous_total_profit' => $totalProfit,
        ], 200);
    }
}
