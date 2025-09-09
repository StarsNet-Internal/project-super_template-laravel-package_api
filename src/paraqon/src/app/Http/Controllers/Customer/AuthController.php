<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

// Laravel built-in
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function getCustomerInfo()
    {
        return $this->customer();
    }
}
