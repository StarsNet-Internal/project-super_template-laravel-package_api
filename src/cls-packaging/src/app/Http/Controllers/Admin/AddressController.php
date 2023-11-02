<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Admin\AddressController as AdminAddressController;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Starsnet\Project\App\Models\CustomerGroupAddress;

class AddressController extends AdminAddressController
{
    public function getAllAddressesByCustomerGroups(Request $request)
    {
        // Extract attributes from $request
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get accessible address_ids
        $addressIDs = CustomerGroupAddress::whereIn('customer_group_ids', $customerGroupIDs)->pluck('address_id')->all();

        // Modify request
        $modifiedValues = [
            'include_ids' => $addressIDs
        ];
        if (count($customerGroupIDs) > 0) {
            $request->merge($modifiedValues);
        }

        // Get Address(s)
        $warehouses = parent::getAllAddresses($request);

        return $warehouses;
    }

    public function createAddressByCustomerGroup(Request $request)
    {
        // Create Address
        /** @var Address $address */
        $address = Address::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $groups = $customer->groups()->statusActive()->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupAddress::create([]);
        $access->associateAddress($address);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Address successfully',
            '_id' => $address->_id
        ], 200);
    }

    public function createCustomerGroupAddressByAdmin(Request $request)
    {
        // Create Address
        /** @var Address $address */
        $address = Address::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupAddress::create([]);
        $access->associateAddress($address);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Address successfully',
            '_id' => $address->_id
        ], 200);
    }
}
