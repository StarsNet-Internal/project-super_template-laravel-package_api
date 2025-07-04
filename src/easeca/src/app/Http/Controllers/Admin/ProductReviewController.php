<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\ProductReview;
use App\Models\Store;
use App\Traits\Controller\ReviewTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\ProductReviewController as AdminProductReviewController;

class ProductReviewController extends AdminProductReviewController
{
    public function getReviewDetails(Request $request)
    {
        // Extract attributes from $request
        $reviewId = $request->route('id');

        // Get ProductReview, then validate
        /** @var ProductReview $review */
        $review = ProductReview::find($reviewId)->makeHidden([
            'product_title',
            'product_variant_title',
            'image'
        ]);

        if (is_null($review)) {
            return response()->json([
                'message' => 'Review not found'
            ], 404);
        }

        // Return ProductReview
        return response()->json($review);
    }
}
