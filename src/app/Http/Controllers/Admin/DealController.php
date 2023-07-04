<?php

namespace StarsNet\Project\App\Http\Controllers\Admin;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use StarsNet\Project\App\Models\AccountDeal;
use StarsNet\Project\App\Models\Deal;
use StarsNet\Project\App\Models\DealCategory;
use StarsNet\Project\App\Models\Tier;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\ReviewTrait;
use StarsNet\Project\App\Traits\Controller\ProjectAccountTrait;
use App\Traits\Utils\Flattenable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DealController extends Controller
{
    use ProductTrait, ReviewTrait, ProjectAccountTrait;

    use Flattenable, Cacheable;

    public function getAllDeals(Request $request)
    {
        $account = $this->account();

        $statuses = (array) $request->input('status', Status::$typesForAdmin);

        // Retrieve required models
        $deals = Deal::statusesAllowed(Status::$typesForAdmin, $statuses)->with([
            'product' => function ($product) {
                $product->select('_id', 'title', 'short_description', 'images');
            },
            'tiers',
        ])->get();

        if ((bool) $this->checkIfAccountIsSuperAdminOrAdmin($account)) {
            return $deals;
        }

        $access = AccountDeal::where('account_id', $account->_id)->first();
        if ($access) {
            $ids = $access['deal_ids'];

            return array_filter($deals->toArray(), function ($deal) use ($ids) {
                return in_array($deal['_id'], $ids);
            });
        }
        return new Collection();
    }

    public function deleteDeals(Request $request)
    {
        // Extract attributes from $request
        $dealIDs = $request->input('ids', []);

        // Get Deal(s)
        /** @var Collection $deals */
        $deals = Deal::find($dealIDs);

        // Filter non SystemCategory(s)
        $deals = $deals->filter(function ($deal) {
            return !$deal->isDealOngoing();
        });

        // Update Deal(s)
        /** @var Deal $deal */
        foreach ($deals as $deal) {
            $deal->statusDeletes();
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $deals->count() . ' Deal(s) successfully'
        ], 200);
    }

    public function recoverDeals(Request $request)
    {
        // Extract attributes from $request
        $dealIDs = $request->input('ids', []);

        // Get Deal(s)
        /** @var Collection $deals */
        $deals = Deal::find($dealIDs);

        // Update Deal(s)
        /** @var Deal $deal */
        foreach ($deals as $deal) {
            $deal->statusRecovers();
        }

        // Return success message
        return response()->json([
            'message' => 'Recovered ' . $deals->count() . ' Deal(s) successfully'
        ], 200);
    }

    public function updateDealStatus(Request $request)
    {
        // Extract attributes from $request
        $dealIDs = $request->input('ids', []);
        $status = $request->input('status');

        // Get Deal(s)
        /** @var Collection $deals */
        $deals = Deal::find($dealIDs);

        // Filter non SystemCategory(s)
        $deals = $deals->filter(function ($deal) {
            return !$deal->isDealOngoing();
        });

        // Update Deal(s)
        /** @var Deal $deal */
        foreach ($deals as $deal) {
            $deal->updateStatus($status);
        }

        // Return success message
        return response()->json([
            'message' => 'Recovered ' . $deals->count() . ' Deal(s) successfully'
        ], 200);
    }

    public function createDeal(Request $request)
    {
        $account = $this->account();

        // Create Deal
        /** @var Deal $deal */
        $deal = Deal::create($request->all());

        $access = AccountDeal::create([]);
        $access->associateAccount($account);
        $access->attachDeals(collect($deal));

        // Return success message
        return response()->json([
            'message' => 'Created New Deal successfully',
            '_id' => $deal->_id
        ], 200);
    }

    public function getDealDetails(Request $request)
    {
        // Extract attributes from $request
        $dealID = $request->route('id');

        // Get Deal, then validate
        /** @var Deal $deal */
        $deal = Deal::with([
            'product',
            'tiers',
        ])->find($dealID)
            ->append(['is_editable']);

        // Return Deal
        return response()->json($deal, 200);
    }

    public function updateDealDetails(Request $request)
    {
        // Validate Request
        $validator = Validator::make(array_merge($request->all(), [
            'id' => $request->route('id')
        ]), [
            'id' => [
                'required',
                'exists:StarsNet\Project\App\Models\Deal,_id'
            ],
            'product_id' => [
                'exists:App\Models\Product,_id',
                'required_if:status,' . Status::ACTIVE
            ],
            'start_datetime' => 'required_if:status,' . Status::ACTIVE,
            'end_datetime' => [
                'after:start_datetime',
                'required_if:status,' . Status::ACTIVE
            ],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Get Deal
        /** @var Deal $deal */
        $deal = Deal::find($request->route('id'));

        if ($deal->isDealOngoing()) {
            return response()->json([
                'message' => 'Active Deal is not editable'
            ], 400);
        }

        $deal->update($request->except(['category_ids', 'product_id', 'tiers']));

        // Synchronize PostCategory(s) 
        $categoryIDs = $request->category_ids;
        /** @var Collection $categories */
        $categories = DealCategory::objectIDs($categoryIDs)->get();
        $deal->syncCategories($categories);

        // Update Associated Product
        $product = Product::find($request->product_id);
        $deal->associateProduct($product);

        // Update Tier
        /** @var array $input */
        $tiers = $deal->tiers()->delete();

        foreach ($request->tiers as $input) {
            // Extract attributes
            $tierAttributes = [];
            foreach ($input as $key => $value) {
                $tierAttributes[$key] = $value;
            }

            $tier = Tier::create($tierAttributes);
            $tier->associateDeal($deal);
        }

        // Return success message
        return response()->json([
            'message' => 'Updated Deal successfully'
        ], 200);
    }
}
