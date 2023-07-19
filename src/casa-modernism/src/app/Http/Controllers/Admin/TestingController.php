<?php

namespace StarsNet\Project\CasaModernism\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;

class TestingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'OK from package/casa-modernism',
        ], 200);
    }
}
