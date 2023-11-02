<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Constants\Model\WarehouseInventoryHistoryType;
use App\Http\Controllers\Admin\WarehouseController as AdminWarehouseController;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\ProductVariant;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Starsnet\Project\App\Models\CustomerGroupWarehouse;

use App\Models\WarehouseInventoryHistory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Starsnet\Project\App\Models\CustomerGroupWarehouseInventoryHistory;

class WarehouseController extends AdminWarehouseController
{
    public function getAllWarehousesByCustomerGroups(Request $request)
    {
        // Extract attributes from $request
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get accessible warehouse_ids
        $warehouseIDs = CustomerGroupWarehouse::whereIn('customer_group_ids', $customerGroupIDs)->pluck('warehouse_id')->all();

        // Modify request
        $modifiedValues = [
            'include_ids' => $warehouseIDs
        ];
        if (count($customerGroupIDs) > 0) {
            $request->merge($modifiedValues);
        }

        // Get Warehouse(s)
        $warehouses = parent::getAllWarehouses($request);

        return $warehouses;
    }

    public function createWarehouseByCustomerGroup(Request $request)
    {
        // Create Warehouse
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $groups = $customer->groups()->statusActive()->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupWarehouse::create([]);
        $access->associateWarehouse($warehouse);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Warehouse successfully',
            '_id' => $warehouse->_id
        ], 200);
    }

    public function createCustomerGroupWarehouseByAdmin(Request $request)
    {
        // Create Warehouse
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupWarehouse::create([]);
        $access->associateWarehouse($warehouse);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Warehouse successfully',
            '_id' => $warehouse->_id
        ], 200);
    }

    public function getAllWarehouseInventoryHistoryByCustomerGroups(Request $request)
    {
        $customerGroupIDs = $request->input('customer_group_ids', []);

        $startDate = Carbon::createFromFormat('Y-m-d', $request->start)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $request->end)->endOfDay();

        $history = CustomerGroupWarehouseInventoryHistory::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('customer_group_ids', $customerGroupIDs)
            ->get();

        return $history;
    }

    public function getReportByCustomerGroups(Request $request)
    {
        $history = $this->getAllWarehouseInventoryHistoryByCustomerGroups($request);

        $customerGroupIDs = $request->input('customer_group_ids', []);

        $period = CarbonPeriod::create($request->start, $request->end);

        $report = [];

        foreach ($period as $date) {
            $startDateTime = $date->copy()->startOfDay();
            $endDateTime = $date->copy()->endOfDay();
            $lastHistory = CustomerGroupWarehouseInventoryHistory::where('created_at', '<', $endDateTime)
                ->whereIn('customer_group_ids', $customerGroupIDs)
                ->latest()
                ->first();

            $current = $history->whereBetween('created_at', [$startDateTime, $endDateTime])->values()->all();
            $in = array_filter($current, function ($var) {
                return $var['type'] === 'DELIVERY_IN';
            });
            $out = array_filter($current, function ($var) {
                return $var['type'] === 'DELIVERY_OUT';
            });

            $report[] = [
                'date' => date_format($date, 'Y-m-d'),
                'balance' => $lastHistory ? $lastHistory['balance'] : '0.00',
                'records' => [
                    'delivery_in' => [
                        'value_pc' => number_format(array_reduce($in, function ($carry, $item) {
                            $carry += $item['value_pc'];
                            return $carry;
                        }), 2, '.', ''),
                        'value_cbm' => number_format(array_reduce($in, function ($carry, $item) {
                            $carry += $item['value_cbm'];
                            return $carry;
                        }), 2, '.', ''),
                    ],
                    'delivery_out' => [
                        'value_pc' => number_format(array_reduce($out, function ($carry, $item) {
                            $carry += $item['value_pc'];
                            return $carry;
                        }), 2, '.', ''),
                        'value_cbm' => number_format(array_reduce($out, function ($carry, $item) {
                            $carry += $item['value_cbm'];
                            return $carry;
                        }), 2, '.', ''),
                    ],
                ]
            ];
        }

        $totalBalance = number_format(array_reduce($report, function ($carry, $item) {
            $carry += $item['balance'];
            return $carry;
        }), 2, '.', '');
        $totalInPc = number_format(array_reduce($report, function ($carry, $item) {
            $carry += $item['records']['delivery_in']['value_pc'];
            return $carry;
        }), 2, '.', '');
        $totalInCbm = number_format(array_reduce($report, function ($carry, $item) {
            $carry += $item['records']['delivery_in']['value_cbm'];
            return $carry;
        }), 2, '.', '');
        $totalOutPc = number_format(array_reduce($report, function ($carry, $item) {
            $carry += $item['records']['delivery_out']['value_pc'];
            return $carry;
        }), 2, '.', '');
        $totalOutCbm = number_format(array_reduce($report, function ($carry, $item) {
            $carry += $item['records']['delivery_out']['value_cbm'];
            return $carry;
        }), 2, '.', '');

        return [
            'entries' => $report,
            'calculations' => [
                'balance' => $totalBalance,
                'in' => ['pc' => $totalInPc, 'cbm' => $totalInCbm],
                'out' => ['pc' => $totalOutPc, 'cbm' => $totalOutCbm]
            ]
        ];
    }

    public function updateWarehouseInventoryAndCreateWarehouseInventoryHistory(Request $request)
    {
        $res = parent::updateWarehouseInventory($request);

        $qtyChange = $request->input('qty_change');
        $variant = ProductVariant::find($request->route('product_variant_id'));
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        $lastHistory = CustomerGroupWarehouseInventoryHistory::whereIn('customer_group_ids', $customerGroupIDs)
            ->latest()
            ->first();
        $currentBalance = $lastHistory['balance'] ?? 0;

        $cbmChange = $variant['box']['length'] * $variant['box']['width'] * $variant['box']['height'] / 1000000 / $variant['cost'] * $qtyChange;

        $history = CustomerGroupWarehouseInventoryHistory::create([
            'remarks' => $request->input('remarks'),
            'type' => $request->input('type'),
            'value_cbm' => number_format(abs($cbmChange), 2, '.', ''),
            'value_pc' => number_format(abs($qtyChange), 2, '.', ''),
            'balance' => number_format($currentBalance + $cbmChange, 2, '.', ''),
        ]);
        $history->syncCustomerGroups($groups);
        $history->associateProductVariant($variant);

        return $res;
    }
}
