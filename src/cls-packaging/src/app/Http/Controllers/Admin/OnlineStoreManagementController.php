<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Admin\OnlineStoreManagementController as AdminOnlineStoreManagementController;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Http\Request;
use Starsnet\Project\App\Models\CustomerGroupProduct;
use Starsnet\Project\App\Models\CustomerGroupProductCategory;

class OnlineStoreManagementController extends AdminOnlineStoreManagementController
{
    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        // Extract attributes from $request
        $storeID = $request->route('store_id');

        // Assign as properties
        /** @var Store $store */
        $this->store = Store::find($storeID);
    }

    public function getAllProductCategoriesByCustomerGroups(Request $request)
    {
        // Extract attributes from $request
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get accessible product_ids
        $categoryIDs = CustomerGroupProductCategory::whereIn('customer_group_ids', $customerGroupIDs)->pluck('product_category_id')->all();

        // Modify request
        $modifiedValues = [
            'include_ids' => $categoryIDs
        ];
        if (count($customerGroupIDs) > 0) {
            $request->merge($modifiedValues);
        }

        // Get ProductCategory(s)
        $categories = parent::getAllCategories($request);

        return $categories;
    }

    public function createProductCategoryByCustomerGroup(Request $request)
    {
        // Create ProductCategory
        /** @var ProductCategory $category */
        $category = ProductCategory::create($request->all());

        // Update Relationship
        $category->associateStore($this->store);

        // Get authenticated User info
        $customer = $this->customer();
        $groups = $customer->groups()->statusActive()->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupProductCategory::create([]);
        $access->associateProductCategory($category);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New ProductCategory successfully',
            '_id' => $category->_id
        ], 200);
    }

    public function createCustomerGroupProductCategoryByAdmin(Request $request)
    {
        // Create ProductCategory
        /** @var ProductCategory $category */
        $category = ProductCategory::create($request->all());

        // Update Relationship
        $category->associateStore($this->store);

        // Get authenticated User info
        $customer = $this->customer();
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupProductCategory::create([]);
        $access->associateProductCategory($category);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New ProductCategory successfully',
            '_id' => $category->_id
        ], 200);
    }

    public function getCategoryUnassignedProductsByCustomerGroups(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get ProductCategory, then validate
        /** @var ProductCategory $category */
        $category = ProductCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'ProductCategory not found'
            ], 404);
        }

        // Get accessible product_ids
        $accessibleProductIDs = count($customerGroupIDs) > 0 ? CustomerGroupProduct::whereIn('customer_group_ids', $customerGroupIDs)->pluck('product_id')->all() : null;

        // Get assigned Product(s)
        $assignedProductIDs = $category->products()->pluck('_id')->all();
        /** @var Collection $products */
        $products = Product::includeIDs($accessibleProductIDs)
            ->excludeIDs($assignedProductIDs)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->get();

        // Return Product(s)
        return $products;
    }
}
