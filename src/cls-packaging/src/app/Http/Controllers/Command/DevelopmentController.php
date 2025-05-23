<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;

class DevelopmentController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package in Command',
            'url_from' => URL::current()
        ], 200);
    }
}
