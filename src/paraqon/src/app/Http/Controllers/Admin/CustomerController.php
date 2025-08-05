<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Auth;
use App\Constants\Model\LoginType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\Bid;
use StarsNet\Project\Paraqon\App\Models\ConsignmentRequest;
use StarsNet\Project\Paraqon\App\Models\PassedAuctionRecord;

class CustomerController extends Controller
{
    public function getAllCustomers(Request $request)
    {
        $users = User::where('type', '!=', 'TEMP')
            ->where('is_deleted', false)
            ->get()
            ->makeHidden(['account'])
            ->keyBy('id')
            ->toArray();
        $userIds = array_keys($users);

        $accounts = Account::whereIn('user_id', $userIds)
            ->get()
            ->keyBy('_id')
            ->toArray();
        $accountIds = array_keys($accounts);

        $customers = Customer::whereIn('account_id', $accountIds)->get()->toArray();

        foreach ($customers as $key => $customer) {
            $account = $accounts[$customer['account_id']];
            $user = $users[$account['user_id']];
            $customers[$key]['account'] = array_merge($account, ['user' => $user]);
        }

        return $customers;
    }

    public function getCustomerDetails(Request $request)
    {
        // Extract attributes from $request
        $customerID = $request->route('id');

        // Get Customer, then validate
        /** @var Customer $customer */
        $customer = Customer::with([
            'account',
            'account.user',
            'account.notificationSetting'
        ])
            ->find($customerID);

        if (is_null($customer)) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        // Return Customer
        return response()->json($customer, 200);
    }

    public function getAllOwnedProducts(Request $request)
    {
        $customerId = $request->route('customer_id');

        $products = Product::statusActive()
            ->where('owned_by_customer_id', $customerId)
            ->get();

        foreach ($products as $product) {
            $product->product_variant_id = optional($product->variants()->latest()->first())->_id;
        }

        return $products;
    }

    public function getAllOwnedAuctionLots(Request $request)
    {
        $customerId = $request->route('customer_id');

        $auctionLots = AuctionLot::where('owned_by_customer_id', $customerId)
            ->where('status', '!=', Status::DELETED)
            ->with([
                'product',
                'productVariant',
                'store',
                'latestBidCustomer',
                'winningBidCustomer'
            ])
            ->get();

        foreach ($auctionLots as $auctionLot) {
            $auctionLot->current_bid = $auctionLot->getCurrentBidPrice();
        }

        return $auctionLots;
    }

    public function getAllBids(Request $request)
    {
        $customerId = $request->route('customer_id');

        $bids = Bid::where('customer_id', $customerId)
            ->with([
                'store',
                'product',
                'auctionLot'
            ])
            ->get();

        return $bids;
    }

    public function hideBid(Request $request)
    {
        // Extract attributes from $request
        $bidId = $request->route('bid_id');

        Bid::where('_id', $bidId)->update(['is_hidden' => true]);

        return response()->json([
            'message' => 'Bid updated is_hidden as true'
        ], 200);
    }

    public function loginAsCustomer(Request $request)
    {
        // Declare local constants
        $roleName = 'customer';

        // Extract attributes from $request
        $loginType = $request->input('type', LoginType::EMAIL);
        $loginType = strtoupper($loginType);

        // Attempt to find User via Account Model
        $user = $this->findUserByCredentials(
            $loginType,
            $request->email,
            $request->area_code,
            $request->phone,
        );

        if (is_null($user)) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        // Create token
        $accessToken = $user->createToken($roleName)->accessToken;

        // Return data
        $data = [
            'token' => $accessToken,
            'user' => $user
        ];

        return response()->json($data, 200);
    }

    private function findUserByCredentials(
        string $loginType,
        ?string $email,
        ?string $areaCode,
        ?string $phone
    ) {
        $userID = $this->findUserIDByCredentials(
            $loginType,
            $email,
            $areaCode,
            $phone
        );
        return User::find($userID);
    }

    private function findUserIDByCredentials(
        string $loginType,
        ?string $email,
        ?string $areaCode,
        ?string $phone
    ) {
        switch ($loginType) {
            case LoginType::EMAIL:
            case LoginType::TEMP:
                $account = Account::where('email', $email)
                    ->first();
                return optional($account)->user_id;
            case LoginType::PHONE:
                $account = Account::where('area_code', $areaCode)
                    ->where('phone', $phone)
                    ->first();
                return optional($account)->user_id;
            default:
                return null;
        }
    }
}
