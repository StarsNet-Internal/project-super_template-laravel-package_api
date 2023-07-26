<?php

namespace StarsNet\Project\Commads\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Constants\Model\VerificationCodeType;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\VerificationCode;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Customer\AuthenticationController as CustomerAuthenticationController;

class AuthenticationController extends CustomerAuthenticationController
{
    use AuthenticationTrait;

    protected $model = User::class;

    public function migrateToTyped(Request $request)
    {
        $res = $this->migrateToRegistered($request);

        $slug = $request->input('slug', 'service-user');
        $customer = $this->customer();
        $category = CustomerGroup::where('slug', $slug)->first();
        $category->attachCustomers(collect([$customer]));

        return $res;
    }
}
