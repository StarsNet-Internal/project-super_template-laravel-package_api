<?php

namespace StarsNet\Project\WhiskyWhiskers\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use StarsNet\Project\WhiskyWhiskers\App\Models\AuctionRequest;
use StarsNet\Project\WhiskyWhiskers\App\Models\ConsignmentRequest;

class AuctionRequestController extends Controller
{
    public function getAllAuctionRequests(Request $request)
    {
        $account = $this->account();

        $forms = AuctionRequest::where('account_id', $account->_id)->get();
        return $forms;
    }

    public function createAuctionRequest(Request $request)
    {
        $account = $this->account();

        // Create AuctionRequest
        $form = new AuctionRequest();
        $form->associateRequestedAccount($account);

        $form->update($request->all());

        return response()->json([
            'message' => 'Created New AuctionRequest successfully',
            '_id' => $form->_id
        ], 200);
    }
}
