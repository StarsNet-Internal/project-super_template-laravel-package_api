<?php
// app/Services/TemporaryUserCleanupService.php

namespace StarsNet\Project\Paraqon\App\Services;

use App\Models\User;
use App\Models\Account;
use App\Models\Address;
use App\Models\Category;
use App\Models\Customer;
use Carbon\Carbon;
use StarsNet\Project\Paraqon\App\Models\AuctionRegistrationRequest;

class TemporaryUserCleanupService
{
    public function __construct(?Carbon $cutOffDate = null)
    {
        $this->cutoffDate = $cutOffDate ?? Carbon::now()->subDays(2);
    }

    public function cleanup()
    {
        // Get TEMP user, account, customer
        $tempUserIDs = $this->getTemporaryUsers();
        $tempAccountIDs = $this->getTemporaryAccounts($tempUserIDs);
        $tempCustomerIDs = $this->getTemporaryCustomers($tempAccountIDs);

        // Delete Records
        Category::where('slug', 'personal-customer-group')
            ->whereIn('item_ids', $tempCustomerIDs)
            ->delete();

        $deletedUserCount = User::whereIn('id', $tempUserIDs)->delete();
        $deletedAccountCount = Account::whereIn('id', $tempAccountIDs)->delete();
        $deletedCustomerCount = Customer::whereIn('id', $tempCustomerIDs)->delete();

        return [
            'user_deleted_count' => $deletedUserCount,
            'account_deleted_count' => $deletedAccountCount,
            'customer_deleted_count' => $deletedCustomerCount,
        ];
    }

    public function getTemporaryUsers()
    {
        $tempUserIDs = User::where('type', 'TEMP')
            ->where('created_at', '<=', $this->cutoffDate)
            ->select('id')
            ->get()
            ->pluck('id')
            ->all();

        return $tempUserIDs;
    }

    public function getTemporaryAccounts(array $tempUserIDs)
    {
        $tempAccountIDs = Account::whereIn('user_id', $tempUserIDs)
            ->select('_id')
            ->get()
            ->pluck('id')
            ->all();

        return $tempAccountIDs;
    }

    public function getTemporaryCustomers(array $tempAccountIDs)
    {
        $tempCustomerIDs = Customer::whereIn('account_id', $tempAccountIDs)
            ->select('_id')
            ->get()
            ->pluck('id')
            ->all();

        return $tempCustomerIDs;
    }
}
