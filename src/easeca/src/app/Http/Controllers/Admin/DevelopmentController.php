<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;

class DevelopmentController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from Easeca package in Admin',
            'url_from' => URL::current()
        ], 200);
    }
}
