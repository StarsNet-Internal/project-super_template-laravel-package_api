<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionRequest;
use StarsNet\Project\WhiskyWhiskers\App\Models\Bid;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;
use StarsNet\Project\WhiskyWhiskers\App\Models\PassedAuctionRecord;

class CustomerController extends Controller
{
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

        $products = AuctionLot::where('owned_by_customer_id', $customerId)
            ->with([
                'product',
                'productVariant',
                'store',
                'latestBidCustomer',
                'winningBidCustomer'
            ])
            ->get();

        return $products;
    }

    public function getAllBids(Request $request)
    {
        $customerId = $request->route('customer_id');

        $products = Bid::where('customer_id', $customerId)
            ->with([
                'product',
                'productVariant',
                'store',
            ])
            ->get();

        return $products;
    }
}
