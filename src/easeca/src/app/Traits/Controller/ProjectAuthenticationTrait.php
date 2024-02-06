<?php

namespace StarsNet\Project\Easeca\App\Traits\Controller;

// Default

use App\Models\User;

trait ProjectAuthenticationTrait
{
    private function updateLoginIdOnDelete(User $user)
    {
        $users = User::where('login_id', 'LIKE', '%' . $user->login_id . '%')
            ->get();
        $counter = 999 - $users->count() + 1;
        $user->updateLoginID($counter . $user->login_id);
    }
}
