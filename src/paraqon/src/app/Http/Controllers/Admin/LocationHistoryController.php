<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use StarsNet\Project\Paraqon\App\Models\LocationHistory;
use Carbon\Carbon;

// Validator
use Illuminate\Support\Facades\Validator;

class LocationHistoryController extends Controller
{
    public function getAllLocationHistories(Request $request)
    {
        $productId = $request->input('product_id');

        $histories = LocationHistory::when($productId, function ($query, $productId) {
            return $query->where('product_id', $productId);
        })
            ->get();

        return $histories;
    }

    public function createHistory(Request $request)
    {
        $productId = $request->route('product_id');
        $product = Product::find($productId);

        // Create History
        $history = LocationHistory::create($request->all());
        $history->associateProduct($product);

        // Return success message
        return response()->json([
            'message' => 'Success',
            'history' => $history
        ]);
    }

    public function massUpdateLocationHistories(Request $request)
    {
        $historyAttributes = $request->histories;

        foreach ($historyAttributes as $history) {
            $historyId = $history['id'];
            $locationHistory = LocationHistory::find($historyId);

            // Check if the history exists
            if (!is_null($locationHistory)) {
                $updateAttributes = $history;
                unset($updateAttributes['id']);
                $locationHistory->update($updateAttributes);
            }
        }

        return response()->json([
            'message' => 'Location Histories updated successfully'
        ], 200);
    }
}
