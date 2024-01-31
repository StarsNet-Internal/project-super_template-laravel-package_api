<?php

namespace StarsNet\Project\HeiFei\App\Http\Controllers\Admin;

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
use StarsNet\Project\HeiFei\App\Models\DailyCashflow;

class DailyCashflowController extends Controller
{
    public function createDailyCashflow(Request $request)
    {
        // Extract attributes from $request
        $attributes = $request->all();

        // Validate
        if ($this->isCreatedToday()) {
            return response()->json([
                'message' => 'Already created'
            ], 404);
        }

        // Create DailyCashflow
        $cashflow = DailyCashflow::create($attributes);

        return response()->json([
            'message' => 'Created today cashflow successfully'
        ], 200);
    }

    private function isCreatedToday()
    {
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();
        $isCreatedToday = DailyCashflow::whereBetween('created_at', [$startOfDay, $endOfDay])->exists();
        return $isCreatedToday;
    }

    public function getLatestDailyCashFlow(Request $request)
    {
        return DailyCashflow::latest()->first();
    }

    public function checkIfCashflowCreatedToday(Request $request)
    {
        $isCreated = $this->isCreatedToday();

        return response()->json(
            [
                'is_created' =>
                $isCreated
            ],
            200
        );
    }
}
