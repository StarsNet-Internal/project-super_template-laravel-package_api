<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;
use App\Models\Order;
use App\Models\ShoppingCartItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShoppingCartController extends Controller
{
    public function getShoppingCartItems(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->store_id;
        $customerID = $request->customer_id;

        // Get Order
        $items = ShoppingCartItem::where('store_id', $storeID)
            ->where('customer_id', $customerID)
            ->get();

        return $items;
    }
}
