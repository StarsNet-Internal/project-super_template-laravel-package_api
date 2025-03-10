<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\LoginType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    use AuthenticationTrait;

    public function deleteCompanies(Request $request)
    {
        // Extract attributes from $request
        $ids = $request->input('ids', []);

        $customerGroups = CustomerGroup::objectIDs($ids)->get();
        $storeIds = array_merge(...$customerGroups->pluck('store_ids')->all());
        $customerIds = array_merge(...$customerGroups->pluck('item_ids')->all());

        $customers = Customer::find($customerIds);
        $accountIds = $customers->pluck('account_id')->all();
        $accounts = Account::find($accountIds);
        $userIds = $accounts->pluck('user_id')->all();

        // Delete Companies, Stores, Users
        $customerGroups->each(function ($model) {
            $model->status = Status::DELETED;
            $model->deleted_at = now();
            $model->save();
        });
        Store::objectIDs($storeIds)
            ->get()
            ->each(function ($model) {
                $model->status = Status::DELETED;
                $model->deleted_at = now();
                $model->save();
            });
        User::whereIn('id', $userIds)
            ->get()
            ->each(function ($model) {
                $model->is_deleted = true;
                $model->deleted_at = now();
                $model->save();
            });

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . count($ids) . ' Company(s) successfully'
        ], 200);
    }

    public function deleteStores(Request $request)
    {
        // Extract attributes from $request
        $ids = $request->input('ids', []);

        $stores = Store::objectIDs($ids)->get();
        $companyIds = array_merge(...$stores->pluck('customer_group_ids')->all());
        $accountIds = array_merge(...$stores->pluck('staff_account_ids')->all());

        $companies = CustomerGroup::find($companyIds);
        $accounts = Account::find($accountIds);

        // Delete Stores
        $stores->each(function ($model) {
            $model->status = Status::DELETED;
            $model->deleted_at = now();
            $model->save();
        });
        // Remove store_ids from CustomerGroup and Account
        $companies->each(function ($company) use ($ids) {
            $updatedIds = array_values(array_diff($company->store_ids, $ids));
            $company->update(['store_ids' => $updatedIds]);
        });
        $accounts->each(function ($account) use ($ids) {
            $updatedIds = array_values(array_diff($account->store_ids, $ids));
            $account->update(['store_ids' => $updatedIds]);
        });

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . count($ids) . ' Store(s) successfully'
        ], 200);
    }

    public function deleteCustomers(Request $request)
    {
        // Extract attributes from $request
        $ids = $request->input('ids', []);

        $customers = Customer::find($ids);
        $accountIds = $customers->pluck('account_id')->all();
        $accounts = Account::find($accountIds);
        $userIds = $accounts->pluck('user_id')->all();
        $storeIds = array_merge(...$accounts->pluck('store_ids')->all());

        $groups = CustomerGroup::find($request->customer_group_ids);
        $stores = Store::find($storeIds);

        // Delete Users
        User::whereIn('id', $userIds)
            ->get()
            ->each(function ($model) {
                $model->is_deleted = true;
                $model->deleted_at = now();
                $model->save();
            });

        // Remove account_ids from Store and Unassign Customer from CustomerGroup
        $stores->each(function ($store) use ($accountIds) {
            $updatedIds = array_values(array_diff($store->staff_account_ids, $accountIds));
            $store->update(['staff_account_ids' => $updatedIds]);
        });

        foreach ($groups as $group) {
            $group->detachCustomers(collect($customers));
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . count($ids) . ' Account(s) successfully'
        ], 200);
    }
}
