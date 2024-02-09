<?php

namespace StarsNet\Project\SunKwong\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
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
use Illuminate\Support\Facades\Http;

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

    protected $model = Post::class;

    public function filterPostsByCategories(Request $request)
    {
        // Extract properties from Controller
        $model = $this->model;

        // Extract attributes from $request
        $collection = $request->input('collection', 'blogs');
        $categoryIDs = $request->input('category_ids', []);
        $keyword = $request->input('keyword');
        if ($keyword === "") $keyword = "*";
        $gate = $request->input('logic_gate', 'OR');
        $slug = $request->input('slug', 'from-new-to-old');

        // Get sorting attributes via slugs
        $sortingValue = $this->getPostSortingAttributesBySlug('post-sorting', $slug);
        switch ($sortingValue['type']) {
            case 'KEY':
                $request['sort_by'] = $sortingValue['key'];
                $request['sort_order'] = $sortingValue['ordering'];
                break;
            case 'KEYWORD':
                break;
            default:
                break;
        }

        // Get matching keywords from Typesense
        $matchingPostIDs = [];
        if (!is_null($keyword)) {
            $typesense = new TypeSenseSearchEngine('posts');
            $matchingPostIDs = $typesense->getIDsFromSearch(
                $keyword,
                'title.en,title.zh,title.cn'
            );
        }

        // Get active Post(s)
        /** @var Collection $posts */
        $posts = Post::when($categoryIDs, function ($query, $categoryIDs) {
            return $query->whereHas('categories', function ($query2) use ($categoryIDs) {
                $query2->objectIDs($categoryIDs);
            });
        })
            ->when($keyword, function ($query) use ($matchingPostIDs) {
                return $query->objectIDs($matchingPostIDs);
            })
            ->statusActive()
            ->get();

        // Handle no results
        if ($posts->count() === 0) return new Collection();

        // Convert _id to MongoDB ObjectId
        $postIDs = $this->extractIDsFromCollection($posts);
        $postIDs = $this->toObjectIDs($postIDs);

        // Get Post(s), and append attributes by MongoDB aggregation
        $posts = $this->getPostsWithLikedAndCommentCount($postIDs);

        $this->appendIsLikedFieldForPosts($posts);

        $posts = $this->getViewCount($posts);

        // Return data
        return $posts;
    }

    public function getPostDetails(Request $request)
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

        if (!$post->isStatusActive()) {
            return response()->json([
                'message' => 'Post is not available for public'
            ], 404);
        }

        // Append attributes on Post(s)
        $posts = collect([$post]);
        $this->appendIsLikedFieldForPosts($posts);

        $data = $this->getAnalytics();
        $count = array_filter($data, function ($datum) use ($post) {
            return $datum[0] === 'select_content' && $datum[2] === $post->_id;
        });
        $commentCount = reset($count) ? reset($count)[3] : '0';
        $post->remarks = $commentCount;

        // Return data
        return response()->json($post);
    }

    public function getRelatedPostsUrls(Request $request)
    {
        // Extract attributes from $request
        $collection = $request->input('collection', 'blogs');
        $postID = $request->input('post_id');
        $excludedPostIDs = $request->input('exclude_ids', []);
        $itemsPerPage = $request->input('items_per_page');

        // Append to excluded Post
        $excludedPostIDs[] = $postID;

        // Initialize a Post collector
        $posts = [];

        /*
        *   Stage 1:
        *   Get Post(s) from System PostCategory, recommended-posts
        */
        /** @var PostCategory $systemCategory */
        $systemCategory = PostCategory::slug('recommended-posts')->first();

        if (!is_null($systemCategory)) {
            // Get Posts(s)
            /** @var Collection $recommendedPosts */
            $recommendedPosts = $systemCategory->posts()
                ->statusActive()
                ->excludeIDs($excludedPostIDs)
                ->get();

            // Randomize ordering
            $recommendedPosts = $recommendedPosts->shuffle();

            // Collect data
            $posts = array_merge($posts, $recommendedPosts->all()); // collect Post(s)
            $excludedPostIDs = array_merge($excludedPostIDs, $recommendedPosts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 2:
        *   Get Post(s) from active, related PostCategory(s)
        */
        /** @var Post $post */
        $post = Post::find($postID);

        if (!is_null($post)) {
            // Get related PostCategory(s) by Post
            $categoryIDs = $post->categories()
                ->statusActive()
                ->pluck('_id')
                ->all();

            // Get Post(s)
            /** @var Collection $relatedPosts */
            $relatedPosts = Post::whereHas('categories', function ($query) use ($categoryIDs) {
                $query->whereIn('_id', $categoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedPostIDs)
                ->get();

            // Randomize ordering
            $relatedPosts = $relatedPosts->shuffle();

            // Collect data
            $posts = array_merge($posts, $relatedPosts->all()); // collect Post(s)
            $excludedPostIDs = array_merge($excludedPostIDs, $relatedPosts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 3:
        *   Get remaining active Post(s) 
        */
        /** @var Collection $remainingPosts */
        $remainingPosts = Post::statusActive()
            ->excludeIDs($excludedPostIDs)
            ->get();

        if ($remainingPosts->count() > 0) {
            // Randomize ordering
            $remainingPosts = $remainingPosts->shuffle();

            // Collect data
            $posts = array_merge($posts, $remainingPosts->all());
        }

        /*
        *   Stage 4:
        *   Generate URLs
        */
        $postIDsSet = collect($posts)->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($postIDsSet as $IDsSet) {
            $urls[] = route('sun-kwong.posts.ids', [
                'ids' => $IDsSet->all()
            ]);
        }

        // Return urls
        return $urls;
    }

    public function getPostsByIDs(Request $request)
    {
        // Extract attributes from $request
        $postIDs = $request->ids;

        // Get Post(s)
        $posts = Post::find($postIDs);

        // TODO: Uncomment during production
        // if ($products->count() !== count($productIDs)) {
        //     abort(404, response()->json([
        //         'message' => 'Product(s) not found'
        //     ]));
        // }

        // Use aggregation to get results
        $postIDs = $this->extractIdsFromCollection($posts);
        $postIDs = $this->toObjectIds($postIDs);

        // Get Posts, and append attributes by MongoDB aggregation
        $posts = $this->getPostsWithLikedAndCommentCount($postIDs);

        $this->appendIsLikedFieldForPosts($posts);

        $posts = $this->getViewCount($posts);

        // Return data
        return $posts;
    }

    public function getViewCount(Collection $posts)
    {
        $data = $this->getAnalytics();

        if (!is_array($data)) {
            return $posts;
        }
        return array_map(function ($post) use ($data) {
            $count = array_filter($data, function ($datum) use ($post) {
                return $datum[0] === 'select_content' && $datum[2] === $post['_id'];
            });
            $commentCount = reset($count) ? intval(reset($count)[3]) : 0;
            $post['comment_count'] = $commentCount;

            return $post;
        }, $posts->toArray());
    }

    public function getAnalytics()
    {
        try {
            $body = [
                'property' => 'properties/426676049',
                'dimensions' => [
                    [
                        'name' => 'eventName'
                    ],
                    [
                        'name' => 'contentType'
                    ],
                    [
                        'name' => 'contentId'
                    ]
                ],
                'metrics' => [
                    [
                        'name' => 'eventCount'
                    ]
                ],
                'dateRanges' => [
                    [
                        'startDate' => '365daysAgo',
                        'endDate' => 'today'
                    ]
                ],
            ];
            $response = Http::post(
                'https://ga.starsnet.com.hk/api/analytics/get',
                $body
            );
        } catch (\Throwable $th) {
            return null;
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
