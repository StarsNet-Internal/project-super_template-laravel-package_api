<?php

namespace StarsNet\Project\SunKwong\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;

class DevelopmentController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from SunKwong package in Customer',
            'url_from' => URL::current()
        ], 200);
    }
}
