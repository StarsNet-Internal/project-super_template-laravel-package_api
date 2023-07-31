<?php

namespace StarsNet\Project\TripleGaga\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\LoginType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;

class StaffManagementController extends Controller
{
    use AuthenticationTrait;

    public function updateTenantDetails(Request $request)
    {
        $accountID = $request->route('account_id');
        $account = Account::find($accountID);

        $account->update($request->all());

        return response()->json([
            'message' => 'Updated Tenant successfully'
        ], 200);
    }
}
