<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Admin\ShipmentController as AdminShipmentController;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use StarsNet\Project\App\Models\CustomerGroupShipment;

class ShipmentController extends AdminShipmentController
{
    public function getAllShipmentsByCustomerGroups(Request $request)
    {
        // Extract attributes from $request
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get accessible shipment_ids
        $shipmentIDs = CustomerGroupShipment::whereIn('customer_group_ids', $customerGroupIDs)->pluck('shipment_id')->all();

        // Modify request
        $modifiedValues = [
            'include_ids' => $shipmentIDs
        ];
        if (count($customerGroupIDs) > 0) {
            $request->merge($modifiedValues);
        }

        // Get Shipment(s)
        $shipments = parent::getAllShipments($request);

        return $shipments;
    }

    public function createShipmentByCustomerGroup(Request $request)
    {
        // Create Shipment
        /** @var Shipment $shipment */
        $shipment = Shipment::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $groups = $customer->groups()->statusActive()->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupShipment::create([]);
        $access->associateShipment($shipment);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Shipment successfully',
            '_id' => $shipment->_id
        ], 200);
    }

    public function createCustomerGroupShipmentByAdmin(Request $request)
    {
        // Create Shipment
        /** @var Shipment $shipment */
        $shipment = Shipment::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupShipment::create([]);
        $access->associateShipment($shipment);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Shipment successfully',
            '_id' => $shipment->_id
        ], 200);
    }
}
