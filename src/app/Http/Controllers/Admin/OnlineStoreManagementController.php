<?php

namespace StarsNet\Project\App\Http\Controllers\Admin;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use StarsNet\Project\App\Models\Deal;
use StarsNet\Project\App\Models\DealCategory;
use App\Traits\Controller\StoreDependentTrait;

class OnlineStoreManagementController extends Controller
{
    use StoreDependentTrait;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $this->store = $this->getStoreByValue($request->route('store_id'));
    }

    public function getAllCategories(Request $request)
    {
        // Extract attributes from $request
        $statuses = (array) $request->input('status', Status::$typesForAdmin);
        $includeIDs = $request->include_ids;
        $excludeIDs = $request->input('exclude_ids', []);

        // Get all active DealCategory(s)
        $categories = DealCategory::whereItemType('Deal')
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->get()
            ->append('deal_count');

        // Return DealCategory(s)
        return $categories;
    }

    public function deleteCategories(Request $request)
    {
        // Extract attributes from $request
        $categoryIDs = $request->input('ids', []);

        // Get DealCategory(s)
        /** @var Collection $categories */
        $categories = DealCategory::find($categoryIDs);

        // Update DealCategory(s)
        /** @var DealCategory $category */
        foreach ($categories as $category) {
            $category->statusDeletes();
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $categories->count() . ' DealCategory(s) successfully'
        ], 200);
    }

    public function recoverCategories(Request $request)
    {
        // Extract attributes from $request
        $categoryIDs = $request->input('ids', []);

        // Get DealCategory(s)
        /** @var Collection $categories */
        $categories = DealCategory::find($categoryIDs);

        // Update DealCategory(s)
        /** @var DealCategory $category */
        foreach ($categories as $category) {
            $category->statusRecovers();
        }

        // Return success message
        return response()->json([
            'message' => 'Recovered ' . $categories->count() . ' DealCategory(s) successfully'
        ], 200);
    }

    public function updateCategoryStatus(Request $request)
    {
        // Extract attributes from $request
        $categoryIDs = $request->input('ids', []);
        $status = $request->input('status');

        // Get DealCategory(s)
        /** @var Collection $categories */
        $categories = DealCategory::find($categoryIDs);

        // Update DealCategory(s)
        /** @var DealCategory $category */
        foreach ($categories as $category) {
            $category->updateStatus($status);
        }

        // Return success message
        return response()->json([
            'message' => 'Updated ' . $categories->count() . ' DealCategory(s) successfully'
        ], 200);
    }

    public function getParentCategoryList(Request $request)
    {
        // Get all DealCategory(s)
        $categories = DealCategory::whereItemType('Deal')
            ->noParent()
            ->statusesAllowed(Status::$typesForAdmin)
            ->get();

        // Return DealCategory(s)
        return $categories;
    }

    public function createCategory(Request $request)
    {
        // Create DealCategory
        /** @var DealCategory $category */
        $category = DealCategory::create($request->all());

        // Update Relationship
        $category->associateStore($this->store);

        // Return success message
        return response()->json([
            'message' => 'Created New DealCategory successfully',
            '_id' => $category->_id
        ], 200);
    }

    public function getCategoryDetails(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');

        // Get DealCategory, then validate
        /** @var DealCategory $category */
        $category = DealCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'DealCategory not found'
            ], 404);
        }

        // Return success message
        return response()->json($category, 200);
    }

    public function updateCategoryDetails(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $parentCategoryID = $request->input('parent_id');

        // Get DealCategory, then validate
        /** @var DealCategory $category */
        $category = DealCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'DealCategory not found'
            ], 404);
        }

        // Get Parent DealCategory, then validate
        if (!is_null($parentCategoryID)) {
            /** @var DealCategory $category */
            $parentCategory = DealCategory::find($parentCategoryID);

            if (is_null($parentCategory)) {
                return response()->json([
                    'message' => 'Parent DealCategory not found'
                ], 404);
            }
        }

        // Update DealCategory
        $request->request->remove('parent_id');
        $category->update($request->all());

        if (isset($parentCategory)) {
            $category->associateParent($parentCategory);
        } else {
            $category->dissociateParent();
        }

        // Return success message
        return response()->json([
            'message' => 'Updated DealCategory successfully'
        ], 200);
    }

    public function getCategoryAssignedDeals(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');

        // Get DealCategory, then validate
        /** @var DealCategory $category */
        $category = DealCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'DealCategory not found'
            ], 404);
        }

        // Get Deal(s)
        $deals = $category->deals()
            ->statuses(Status::$typesForAdmin)
            ->get();

        // Return Deal(s)
        return $deals;
    }

    public function getCategoryUnassignedDeals(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Get DealCategory, then validate
        /** @var DealCategory $category */
        $category = DealCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'DealCategory not found'
            ], 404);
        }

        // Get assigned Deal(s)
        $assignedDealIDs = $category->deals()->pluck('_id')->all();
        /** @var Collection $deals */
        $deals = Deal::excludeIDs($assignedDealIDs)
            ->statusesAllowed(Status::$typesForAdmin, $statuses)
            ->get();

        // Return Deal(s)
        return $deals;
    }

    public function assignDealsToCategory(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $dealIDs = $request->input('ids', []);

        // Get DealCategory, then validate
        /** @var DealCategory $category */
        $category = DealCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'DealCategory not found'
            ], 404);
        }

        // Get Deal(s)
        /** @var Collection $deals */
        $deals = Deal::find($dealIDs);

        // Update relationships
        $category->attachDeals(collect($deals));

        // Return success message
        return response()->json([
            'message' => 'Assigned ' . $deals->count() . ' Deal(s) successfully'
        ], 200);
    }

    public function unassignDealsFromCategory(Request $request)
    {
        // Extract attributes from $request
        $categoryID = $request->route('category_id');
        $dealIDs = $request->input('ids', []);

        // Get DealCategory, then validate
        /** @var DealCategory $category */
        $category = DealCategory::find($categoryID);

        if (is_null($category)) {
            return response()->json([
                'message' => 'DealCategory not found'
            ], 404);
        }

        // Get Deal(s)
        /** @var Collection $deals */
        $deals = Deal::find($dealIDs);

        // Update relationships
        $category->detachDeals(collect($deals));

        // Return success message
        return response()->json([
            'message' => 'Unassigned ' . $deals->count() . ' Deal(s) successfully'
        ], 200);
    }
}
