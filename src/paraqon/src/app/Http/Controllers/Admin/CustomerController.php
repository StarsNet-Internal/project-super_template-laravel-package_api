<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\LoginType;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;
use StarsNet\Project\Paraqon\App\Models\PassedAuctionRecord;

class CustomerController extends Controller
{
    public function getAllCustomers(Request $request)
    {
        // Get Customer(s)
        /** @var Collection $customers */
        $customers = Customer::whereIsDeleted(false)
            ->whereHas('account', function ($query) {
                $query->whereHas('user', function ($query2) {
                    $query2->where('type', '!=', LoginType::TEMP);
                });
            })
            ->with([
                'account',
            ])
            ->get();

        // Return Customer(s)
        return $customers;
    }

    public function getCustomerDetails(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        // Get Customer, then validate
        /** @var Customer $customer */
        $customer = Customer::with([
            'account',
        ])
            ->find($customerID);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Return Customer
        return response()->json($customer, 200);
    }

    public function getAllOwnedProducts(Request $request)
    {
        $customerId = $request->route('customer_id');

        $products = Product::statusActive()
            ->where('owned_by_customer_id', $customerId)
            ->get();

        foreach ($products as $product) {
            $product->product_variant_id = optional($product->variants()->latest()->first())->_id;

            $passedAuctionCount = PassedAuctionRecord::where(
                'customer_id',
                $customerId
            )->where(
                'product_id',
                $product->_id
            )->count();
            $product->passed_auction_count = $passedAuctionCount;
        }

        return $products;
    }

    public function getAllOwnedAuctionLots(Request $request)
    {
        $customerId = $request->route('customer_id');

        $auctionLots = AuctionLot::where('owned_by_customer_id', $customerId)
            ->with([
                'product',
                'productVariant',
                'store',
                'latestBidCustomer',
                'winningBidCustomer'
            ])
            ->get();

        $incrementRulesDocument = Configuration::where('slug', 'bidding-increments')->latest()->first();
        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();
        }

        return $auctionLots;
    }

    public function getAllBids(Request $request)
    {
        $customerId = $request->route('customer_id');

        $products = Bid::where('customer_id', $customerId)
            ->where('is_hidden', false)
            ->with([
                'product',
                'productVariant',
                'store',
            ])
            ->get();

        return $products;
    }

    public function hideBid(Request $request)
    {
        // Extract attributes from $request
        $bidId = $request->route('bid_id');

        Bid::where('_id', $bidId)->update(['is_hidden' => true]);

        return response()->json([
            'message' => 'Bid updated is_hidden as true'
        ], 200);
    }
}
