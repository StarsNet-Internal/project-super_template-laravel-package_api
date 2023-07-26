<?php

namespace StarsNet\Project\Commads\App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Utils 
use Illuminate\Support\Collection;

// Constants
use App\Constants\Model\LoginType;
use App\Constants\Model\StoreType;

// Models
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;

// Traits
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\StoreDependentTrait;
use StarsNet\Project\Commads\App\Traits\Controller\OrderTrait;

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;

class CustomerController extends AdminCustomerController
{
    use AuthenticationTrait,
        StoreDependentTrait,
        OrderTrait;

    protected $model = Customer::class;

    public function getOrdersAndQuotesByAllStores(Request $request)
    {
        $response = $this->getOrdersByAllStores($request);
        $data = json_decode($response->getContent(), true);

        foreach ($data['main'] as $key => $order) {
            $data['main'][$key] = array_merge($order, $this->getQuoteDetails($order));
        }
        foreach ($data['mini'] as $key => $order) {
            $data['mini'][$key] = array_merge($order, $this->getQuoteDetails($order));
        }
        foreach ($data['offline'] as $key => $order) {
            $data['offline'][$key] = array_merge($order, $this->getQuoteDetails($order));
        }

        return response()->json($data, $response->getStatusCode());
    }
}
