<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Configuration;
use App\Models\ProductVariant;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Constants\Model\OrderPaymentMethod;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Models\CustomerGroup;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Str;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;
use StarsNet\Project\Paraqon\App\Models\Deposit;

class CustomerGroupController extends Controller
{
    public function getCustomerGroupAssignedCustomers(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('id');

        // Get CustomerGroup, then validate
        /** @var CustomerGroup $category */
        $category = CustomerGroup::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'CustomerGroup not found'
            ], 404);
        }

        // Get Customer
        $customers = $category->customers()
            ->with([
                'account',
            ])
            ->get();

        // Return Customer(s)
        return $customers;
    }

    public function getCustomerGroupUnassignedCustomers(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('id');

        // Get CustomerGroup, then validate
        /** @var CustomerGroup $category */
        $category = CustomerGroup::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'CustomerGroup not found'
            ], 404);
        }

        // Get Assigned Post(s)
        $assignedCustomerIDs = $category->customers()->pluck('_id')->all();
        /** @var Collection $posts */
        $customers = Customer::excludeIDs($assignedCustomerIDs)
            ->with([
                'account',
            ])
            ->get();

        // Return success message
        return $customers;
    }
}
