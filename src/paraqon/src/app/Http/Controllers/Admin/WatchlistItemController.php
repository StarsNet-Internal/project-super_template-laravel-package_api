<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\LoginType;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Configuration;
use App\Models\Order;
use App\Models\ShoppingCartItem;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\Deposit;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;
use StarsNet\Project\Paraqon\App\Models\WatchlistItem;
use App\Models\Customer;

use Illuminate\Http\Request;

class WatchlistItemController extends Controller
{
    public function getAllWatchlistedCustomers(Request $request)
    {
        $queryParams = $request->query();

        $watchlistItemQuery = WatchlistItem::query();

        foreach ($queryParams as $key => $value) {
            if (in_array($key, ['per_page', 'page', 'sort_by', 'sort_order'])) {
                continue;
            }

            $watchlistItemQuery->where($key, $value);
        }

        $customerIDs = $watchlistItemQuery->get()
            ->pluck('customer_id')
            ->unique()
            ->values()
            ->all();

        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::objectIDs($customerIDs)
            ->whereHas('account', function ($query) {
                $query->whereHas('user', function ($query2) {
                    $query2->where('type', '!=', LoginType::TEMP);
                });
            })
            ->with([
                'account',
                'account.user'
            ])
            ->get();

        // Return Customer(s)
        return $customers;
    }
}
