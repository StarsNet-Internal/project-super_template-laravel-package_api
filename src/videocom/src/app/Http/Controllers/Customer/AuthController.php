<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function getCustomerInfo()
    {
        return $this->customer();
    }
}
