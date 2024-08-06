<?php

namespace StarsNet\Project\SnoreCircle\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;

class DevelopmentController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from snore circle package',
            'url_from' => URL::current()
        ], 200);
    }
}
