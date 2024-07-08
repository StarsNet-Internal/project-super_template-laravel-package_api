<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot;

// Validator
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuctionController extends Controller
{
    // public function createAuctionStore(Request $request)
    // {
    //     // Extract attributes from $request
    //     $attributes = $request->all();

    //     // Create Store
    //     /** @var Store $store */
    //     $store = Store::createOfflineStore($attributes);

    //     // Create Warehouse
    //     $warehouseTitle = 'auction_warehouse_' . $store->_id;
    //     $warehouse = $store->warehouses()->create([
    //         'type' => 'AUCTION',
    //         'slug' => Str::slug($warehouseTitle),
    //         'title' => [
    //             'en' => $warehouseTitle,
    //             'zh' => $warehouseTitle,
    //             'cn' => $warehouseTitle
    //         ],
    //         'is_system' => true,
    //     ]);

    //     // Create one default Category
    //     $categoryTitle = 'all_products' . $store->_id;;
    //     $category = $store->productCategories()->create([
    //         'slug' => Str::slug($categoryTitle),
    //         'title' => [
    //             'en' => $categoryTitle,
    //             'zh' => $categoryTitle,
    //             'cn' => $categoryTitle
    //         ],
    //         'is_system' => true,
    //     ]);

    //     // Return success message
    //     return response()->json([
    //         'message' => 'Created new Auction successfully',
    //         '_id' => $store->_id,
    //         'warehouse_id' => $warehouse->_id,
    //         'category_id' => $category->_id,
    //     ], 200);
    // }

    public function getAllUnpaidAuctionLots(Request $request)
    {
        $storeID = $request->route('store_id');

        // Query
        $unpaidAuctionLots = AuctionLot::where('store_id', $storeID)
            ->whereNotNull('winning_bid_customer_id')
            ->where('is_paid', false)
            ->with([
                'product',
                'store',
                // 'winningBidCustomer',
                // 'winningBidCustomer.account'
            ])
            ->get();

        // Return success message
        return $unpaidAuctionLots;
    }

    public function returnAuctionLotToOriginalCustomer(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'ids' => [
                'required',
                'array'
            ],
            'ids.*' => [
                'exists:StarsNet\Project\WhiskyWhiskers\App\Models\AuctionLot,_id'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Extract attributes from $request
        $auctionLotIDs = $request->input('ids', []);

        // Query
        $unpaidAuctionLots = AuctionLot::objectIDs($auctionLotIDs)
            ->with([
                'product',
                'store',
                'winningBidCustomer',
            ])
            ->get();

        // Validate AuctionLot(s)
        foreach ($unpaidAuctionLots as $key => $lot) {
            $lotID = $lot->_id;

            if (is_null($lot->winning_bid_customer_id)) {
                return response()->json([
                    'message' => 'Auction lot ' . $lotID . ' does not have a winning customer.'
                ]);
            }

            if ($lot->is_paid === true) {
                return response()->json([
                    'message' => 'Auction lot ' . $lotID . ' has already been paid.'
                ]);
            }
        }

        // Update Product status, and reset AuctionLot WinningCustomer
        $productIDs = $unpaidAuctionLots->pluck('product_id');

        AuctionLot::objectIDs($auctionLotIDs)->update(["winning_bid_customer_id" => null]);
        Product::objectIDs($productIDs)->update(['listing_status' => 'AVAILABLE']);

        // Return success message
        return response()->json([
            'message' => 'Updated listing_status for ' . count($auctionLotIDs) . ' Product(s).'
        ], 200);
    }
}
