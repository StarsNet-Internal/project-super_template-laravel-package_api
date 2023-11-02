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
use StarsNet\Project\ClsPackaging\App\Models\CustomerGroupProduct;
use StarsNet\Project\ClsPackaging\App\Models\CustomerGroupProductCategory;
use StarsNet\Project\ClsPackaging\App\Constants\Model\ProductType;

class ProductController extends AdminProductController
{
    public function getAllProductsByCustomerGroups(Request $request)
    {
        // Extract attributes from $request
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);
        $type = $request->type;

        // Get accessible product_ids
        $addressIDs = CustomerGroupProduct::whereIn('customer_group_ids', $customerGroupIDs)
            ->where('type', $type)
            ->pluck('product_id')
            ->all();

        if (count($customerGroupIDs) == 0) {
            $addressIDs = CustomerGroupProduct::where('type', $type)
                ->pluck('product_id')
                ->all();
        }

        // Modify request
        $modifiedValues = [
            'include_ids' => $addressIDs
        ];
        // if (count($customerGroupIDs) > 0) {
        $request->merge($modifiedValues);
        // }

        // Get Product(s)
        $products = parent::getAllProducts($request);

        return $products;
    }

    public function filterAllProducts(Request $request)
    {
        // Extract attributes from $request
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $keyword = $request->input('keyword');
        // $categoryIDs = $request->input('category_id', []);
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get accessible product_ids
        $productIDs = CustomerGroupProduct::whereIn('customer_group_ids', $customerGroupIDs)->pluck('product_id')->all();

        // Get matching keywords from Typesense
        if (!is_null($keyword)) {
            $typesense = new TypeSenseSearchEngine('products');
            $matchingProductIDs = $typesense->getIDsFromSearch(
                $keyword,
                'title.en,title.zh'
            );
            $productIDs = array_intersect($productIDs, $matchingProductIDs);
        }

        // Get Product(s)
        $products = Product::includeIDs($productIDs)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->with(['variants'])
            ->get();

        return $products;
    }

    public function createProductByCustomerGroup(Request $request)
    {
        // Create Product
        /** @var Product $product */
        $product = Product::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $groups = $customer->groups()->statusActive()->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupProduct::create(['type' => ProductType::PRODUCT]);
        $access->associateProduct($product);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Product successfully',
            '_id' => $product->_id
        ], 200);
    }

    public function createCustomerGroupProductByAdmin(Request $request)
    {
        // Create Product
        /** @var Product $product */
        $product = Product::create($request->all());

        // Get authenticated User info
        $customer = $this->customer();
        $customerGroupIDs = (array) $request->input('customer_group_ids', []);
        $groups = CustomerGroup::whereIn('_id', $customerGroupIDs)->get();

        // Define accessibility by CustomerGroup
        $access = CustomerGroupProduct::create(['type' => ProductType::PRODUCT]);
        $access->associateProduct($product);
        $access->associateCreatedByCustomer($customer);
        $access->syncCustomerGroups($groups);

        // Return success message
        return response()->json([
            'message' => 'Created New Product successfully',
            '_id' => $product->_id
        ], 200);
    }

    public function getAllProductVariantsByCustomerGroups(Request $request)
    {
        $product_variants = [];

        $products = $this->getAllProductsByCustomerGroups($request);

        // Get Product, then validate
        /** @var Product $product */
        foreach ($products as $product) {
            // Get ProductVariant(s)
            /** @var Collection $variants */
            $variants = $product->variants()->statusActive()->get();

            // Append attributes
            /** @var ProductVariant $variant */
            foreach ($variants as $variant) {
                $variant->appendTotalWarehouseInventoryAttributes();
                $variant->appendLatestDiscount();
                $product_variants[] = $variant;
            }
        }

        return $product_variants;
    }

    public function getProductType(Request $request)
    {
        $type = CustomerGroupProduct::where('product_id', $request->route('id'))->first();

        if (is_null($type)) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        return $type;
    }
}
