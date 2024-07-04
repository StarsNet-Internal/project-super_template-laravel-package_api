<?php

namespace StarsNet\Project\EnjoyFace\App\Traits\Controller;

// Default

use App\Models\Account;
use App\Models\Post;
use App\Models\PostCategory;

trait ProjectPostTrait
{
    private function createInboxPost(array $postAttributes, array $accountIds, bool $isCopyable)
    {
        if (count($accountIds) === 0) {
            return;
        }

        $post = Post::create($postAttributes);
        $accounts = Account::find($accountIds);

        foreach ($accounts as $account) {
            $account->likePost($post);
        }

        if ($isCopyable) {
            $category = PostCategory::where('item_type', 'Post')->first();
            $category->attachPosts(collect([$post]));
        }
    }
}
