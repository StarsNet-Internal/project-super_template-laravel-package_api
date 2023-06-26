<?php

namespace StarsNet\Project;

use App\Http\Controllers\Controller;


class testingController extends Controller
{
    public function healthCheck()
    {
        return response()->json([
            'message' => 'branch package healthy'
        ], 200);
    }
}
