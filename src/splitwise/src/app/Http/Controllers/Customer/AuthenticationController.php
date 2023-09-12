<?php

namespace StarsNet\Project\Splitwise\App\Http\Controllers\Customer;

use App\Constants\Model\LoginType;
use App\Constants\Model\VerificationCodeType;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Models\VerificationCode;
use App\Traits\Controller\AuthenticationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    use AuthenticationTrait;

    protected $model = User::class;

    public function getAuthUserInfo()
    {
        // Get User, then validate
        $user = $this->user();

        if (is_null($user)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // if ($user->isDisabled()) {
        //     return response()->json([
        //         'message' => 'This account is disabled'
        //     ], 403);
        // }

        // Get Role 
        $user->role = $user->getRole();

        // Return data
        $data = [
            'user' => $user
        ];

        return response()->json($data, 200);
    }
}
