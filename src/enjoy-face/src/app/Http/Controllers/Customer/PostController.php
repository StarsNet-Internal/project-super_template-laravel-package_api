<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostReview;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Categorizable;
use App\Traits\Controller\DetailTrait;
use App\Traits\Controller\Paginatable;
use App\Traits\Controller\PostTrait;
use App\Traits\Controller\Reviewable;
use App\Traits\Controller\Sortable;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use App\Traits\Utils\MongoDBOperationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PostController extends Controller
{
    use
        // Helper Traits
        AuthenticationTrait,
        PostTrait,
        Paginatable,
        MongoDBOperationTrait,

        // Trait Routes
        Categorizable,
        DetailTrait,
        Reviewable,
        Sortable;

    public function getPostReviews(Request $request)
    {
        // Extract attributes from $request
        $postID = $request->route('id');

        // Get Post, then validate
        $post = Post::find('6618edd2fe73d9309d0d31e2');

        // if (is_null($post)) {
        //     return response()->json([
        //         'message' => 'Post not found'
        //     ], 404);
        // }

        // if (!$post->isStatusActive()) {
        //     return response()->json([
        //         'message' => 'Post is not available for public'
        //     ], 404);
        // }

        // Get active Review(s) by Post
        $reviews = $post->reviews()
            ->where('user_id', $this->user()->id)
            ->statusActive()
            ->get();

        // Return data
        return $reviews;
    }

    public function getAllLikedPosts(Request $request)
    {
        // Get authenticated User information
        $account = $this->account();

        // Get liked Post(s) by Account
        $posts = $account
            ->likedPosts()
            ->statusActive()
            ->get();

        // Convert _id to MongoDB ObjectId
        $postIDs = $this->extractIDsFromCollection($posts);
        $postIDs = $this->toObjectIDs($postIDs);

        // Get Post(s), and append attributes by MongoDB aggregation
        $posts = $this->getPostsWithLikedAndCommentCount($postIDs);

        foreach ($posts as $post) {
            if ($post->comment_count === 0) {
                $review = PostReview::create([
                    'model_type' => 'Post',
                    'model_type_id' => $post->_id,
                    'rating' => 5,
                    'comment' => 'Inbox read'
                ]);
                $review->associateUser($this->user());
            }
        }

        $posts->map(function ($post) {
            $post->is_liked = count($post->category_ids) > 0;
            return $post;
        });

        // Return data
        return $posts;
    }

    public function getNumberOfUnreadMessages(Request $request)
    {
        // Get authenticated User information
        $account = $this->account();

        // Get liked Post(s) by Account
        $posts = $account
            ->likedPosts()
            ->statusActive()
            ->get();

        // Convert _id to MongoDB ObjectId
        $postIDs = $this->extractIDsFromCollection($posts);
        $postIDs = $this->toObjectIDs($postIDs);

        // Get Post(s), and append attributes by MongoDB aggregation
        $posts = $this->getPostsWithLikedAndCommentCount($postIDs);

        return [
            'count' => $posts->filter(function ($post) {
                return $post->comment_count === 0;
            })->count()
        ];
    }
}
