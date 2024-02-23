<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\LoginType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;

class StaffManagementController extends Controller
{
    use AuthenticationTrait;

    public function updateMerchantDetails(Request $request)
    {
        $userID = $request->route('id');

        $user = User::find($userID);
        $account = $user->account;
        $account->update($request->all());

        return response()->json([
            'message' => 'Updated Merchant successfully'
        ], 200);
    }
}
