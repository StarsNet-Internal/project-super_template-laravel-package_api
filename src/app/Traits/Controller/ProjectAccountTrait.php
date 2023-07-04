<?php

namespace StarsNet\Project\App\Traits\Controller;

// Default
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

trait ProjectAccountTrait
{
    private function checkIfAccountIsSuperAdminOrAdmin(Account $account): bool
    {
        $role = $account->role()->first();

        $slug = $role['slug'];

        if ($slug == 'super-admin' && $slug == 'admin') {
            return true;
        }
        return false;
    }
}
