<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostReview;
use App\Models\ReviewReply;
use App\Constants\Model\Status;
use App\Models\ProductCategory;
use App\Traits\Controller\Categorizable;
use App\Traits\Controller\DetailTrait;
use App\Traits\Controller\MassUpdatable;
use App\Traits\Controller\PostTrait;
use App\Traits\Controller\Reviewable;
use App\Traits\Controller\ReviewTrait;
use App\Traits\Controller\Statusable;
use App\Traits\Utils\MongoDBOperationTrait;
use Illuminate\Support\Collection;

class PostController extends Controller
{
    use
        // Helper Traits
        PostTrait,
        MongoDBOperationTrait,

        // Trait Routes
        Statusable,
        DetailTrait,
        MassUpdatable,
        Categorizable,
        Reviewable,
        ReviewTrait;

    public function updatePostReviewReplyStatus(Request $request)
    {
        // Extract attributes from $request
        $reviewIds = $request->input('ids', []);
        $replyStatus = $request->input('reply_status');

        // Get Enquiry(s)
        /** @var Collection $reviews */
        $reviews = PostReview::find($reviewIds);

        // Update PostReview(s)
        /** @var PostReview $review */
        foreach ($reviews as $review) {
            if ($review->reply_status === 'PENDING') {
                $review->update([
                    'reply_status' => $replyStatus
                ]);
            }
        }

        // Return success message
        return response()->json([
            'message' => 'Updated ' . $reviews->count() . ' PostReview(s) successfully'
        ], 200);
    }
}
