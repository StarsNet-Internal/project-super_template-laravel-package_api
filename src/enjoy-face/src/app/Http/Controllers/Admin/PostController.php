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
use StarsNet\Project\EnjoyFace\App\Traits\Controller\ProjectPostTrait;
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
        ReviewTrait,
        ProjectPostTrait;

    public function createPost(Request $request)
    {
        // Extract attributes from $request
        $accountIds = $request->input('category_ids', []);

        // Create Post
        /** @var Post $post */
        $post = $this->createInboxPost($request->except(['category_ids']), $accountIds, false);

        // Return success message
        return response()->json([
            'message' => 'Created New Message successfully',
            '_id' => $post->_id
        ], 200);
    }

    public function updatePostDetails(Request $request)
    {
        // Extract attributes from $request
        $postID = $request->route('id');

        // Get Post, then validate
        /** @var Post $post */
        $post = Post::find($postID);

        if (is_null($post)) {
            return response()->json([
                'message' => 'Post not found'
            ], 404);
        }

        // Update Post
        $post->update($request->except(['category_ids']));

        // Synchronize PostCategory(s) 
        $post->likedAccounts()->sync($request->category_ids);

        // Return success message
        return response()->json([
            'message' => 'Updated Post successfully'
        ], 200);
    }

    public function replyPostReview(Request $request)
    {
        // Extract attributes from $request
        $reviewID = $request->route('review_id');

        // Get PostReview, then validate
        /** @var PostReview $review */
        $review = PostReview::find($reviewID);

        if (is_null($review)) {
            return response()->json([
                'message' => 'Review not found'
            ], 404);
        }

        // Get authenticated User information
        $user = $this->user();

        // Create ReviewReply
        $replyAttribute = [
            'images' => $request->images ?? [],
            'rating' => $request->rating ?? 0,
            'comment' => $request->comment ?? '',
            'status' => Status::ACTIVE
        ];
        $reply = $review->replies()->create($replyAttribute);

        // Update ReviewReply
        $reply->associateUser($user);

        // Return success message
        return response()->json([
            'message' => 'Replied to Review',
            '_id' => $reply->_id
        ], 200);
    }

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
