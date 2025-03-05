<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Review;
use StarsNet\Project\Videocom\App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductReviewController extends Controller
{
    public function getAllProductReviews(Request $request)
    {
        $queryParams = $request->query();

        /** @var ProductReview $reviewQuery */
        $reviewQuery = ProductReview::where('model_type', 'Product')
            ->where('status', Status::ACTIVE)
            ->whereHas('product', function ($q) {
                $q->where('status', Status::ACTIVE);
                return;
            });

        foreach ($queryParams as $key => $value) {
            if (in_array($key, ['per_page', 'page', 'sort_by', 'sort_order'])) {
                continue;
            }

            if (is_array($value) && !is_string($value)) {
                $reviewQuery->whereIn($key, $value);
            } else {
                $reviewQuery->where($key, $value);
            }
        }

        /** @var Collection $reviews */
        $reviews = $reviewQuery->with([
            'user',
            'product',
            'productVariant',
            'store',
        ])
            ->get();

        foreach ($reviews as $review) {
            $productID = $review->model_type_id;
            $review->review_count = $aggregates[$productID]->review_count ?? 0;
            $review->average_rating = $aggregates[$productID]->average_rating ?? 0;
        }

        return $reviews;
    }

    public function getProductReviewDetails(Request $request)
    {
        $reviewID = $request->route('review_id');

        /** @var ProductReview $review */
        $review = ProductReview::with([
            'user',
            'product',
            'productVariant',
            'store',
        ])
            ->find($reviewID);

        if (is_null($review)) {
            return response()->json([
                'message' => 'Review not found'
            ], 404);
        }

        if ($review->status == Status::DELETED) {
            return response()->json([
                'message' => 'Review not found'
            ], 404);
        }

        if ($review->status != Status::ACTIVE) {
            return response()->json([
                'message' => 'Review is not available for public'
            ], 404);
        }

        return response()->json($review);
    }
}
