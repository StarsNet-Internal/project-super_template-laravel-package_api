<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Auth
use Illuminate\Support\Facades\Auth;

// Validator
// use Illuminate\Support\Facades\Validator;
// use Illuminate\Validation\Rule;

// Constants
use App\Constants\Model\LoginType;
use App\Constants\Model\VerificationCodeType;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;

// Models
use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use App\Models\VerificationCode;

// Traits
use App\Traits\Controller\AuthenticationTrait;

class AuthenticationController extends Controller
{
    use AuthenticationTrait;

    protected $model = User::class;

    public function getAuthUserInfo()
    {
        $user = User::with([
            'account',
            'account.role',
            'account.customer',
            'account.customer.groups'
        ])
            ->find($this->user()->id)
            ->toArray();

        $user['role'] = $user['account']['role'];
        unset($user['account']['role']);

        // Return data
        $data = [
            'user' => $user
        ];

        return response()->json($data, 200);
    }
}
