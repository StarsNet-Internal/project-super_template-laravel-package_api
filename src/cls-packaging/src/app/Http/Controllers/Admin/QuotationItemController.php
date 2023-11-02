<?php

namespace StarsNet\Project\ClsPackaging\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\CustomerGroup;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Http\Request;
use StarsNet\Project\App\Models\CustomerGroupProduct;
use StarsNet\Project\App\Models\CustomerGroupProductCategory;

use Illuminate\Support\Facades\Validator;
use StarsNet\Project\App\Constants\Model\ProductType;

class QuotationItemController extends AdminProductController
{
    public function createCustomerGroupQuotationItemByAdmin(Request $request)
    {
        // Create Product
        /** @var Product $product */
        $product = Product::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupProduct::create(['type' => ProductType::QUOTATION_ITEM]);
        $access->associateProduct($product);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New QuotationItem successfully',
            '_id' => $product->_id
        ], 200);
    }
}
