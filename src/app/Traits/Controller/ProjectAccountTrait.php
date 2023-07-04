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

        if ($role['slug'] == 'super-admin' || $role['slug'] == 'admin') {
            return true;
        }
        return false;
    }
}
