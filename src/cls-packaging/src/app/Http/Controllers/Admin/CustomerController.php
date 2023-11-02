<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Admin\WarehouseController as AdminWarehouseController;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Starsnet\Project\App\Models\CustomerGroupWarehouse;
use App\Models\CustomerGroup;

class CustomerController extends AdminWarehouseController
{
    public function getCustomerGroups(Request $request)
    {
        $customer = $this->customer();
        $groups = $customer->groups()->statusActive()->get();

        return $groups;
    }

    public function getCustomerDetails()
    {
        $customer = $this->customer();

        return $customer;
    }
}
