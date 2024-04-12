<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    protected $model = Enquiry::class;

    public function createEnquiry(Request $request)
    {
        // Extract attributes
        $attributes = $request->all();

        // Get authenticated User information
        $customer = $this->customer();
        $attributes = array_merge($attributes, [
            'customer_id' => $customer->_id
        ]);

        // Create Enquiry
        $enquiry = Enquiry::create($attributes);

        // Return success message
        return response()->json([
            'message' => 'Submitted Enquiry successfully'
        ], 200);
    }
}
